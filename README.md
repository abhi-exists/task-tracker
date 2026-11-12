# Task Tracker

A small task-management web application written in core PHP 8 with a MySQL
database. No frameworks, no Composer dependencies: just PHP, PDO and MySQL,
served as plain `.php` files with Bootstrap 5 from a CDN.

The app was built in three levels, one commit each:

| Level | What it adds |
|-------|--------------|
| 1 | Database and `tasks` table, add / list / edit / delete with a confirmation step, flash messages after every action |
| 2 | Status as a real ENUM with inline changes, search by title, filter by status and priority, validation on both sides, due date, priority, summary cards, pagination |
| 3 | Accounts, roles and permissions, CSRF protection, output escaping, safe error handling |

---

## 1. Requirements

- **PHP 8.0 or newer** with these extensions: `pdo_mysql`, `session` (all enabled
  by default in XAMPP). Checked with `php -m`.
- **MySQL 5.7+ / 8.x or MariaDB 10.3+** (the schema uses `ENUM`, `utf8mb4`,
  InnoDB and a cascading foreign key).
- A browser. Bootstrap 5 and its icons come from a CDN, so the page styling
  needs internet access; every feature works without it, just unstyled.
- Optionally `curl`, if you want to re-run the checks in `docs/TESTING.md`.

No Composer install step exists.

---

## 2. Setup

### 2.1 Create the database and import the SQL

Option A - command line, from the project folder:

```bash
mysql -u root -p < sql/database.sql
```

Option B - phpMyAdmin: click **Import**, choose `sql/database.sql`, press **Go**.

Option C - MySQL Workbench: open `sql/database.sql`, run the whole script.

`sql/database.sql` is complete and repeatable: it creates `task_tracker`, the
`users` and `tasks` tables with their indexes, foreign key and sample data.
Re-importing it **drops and recreates those two tables**, so it doubles as the
"reset to sample data" step.

If you are upgrading a database you already have data in (rather than starting
fresh), apply the migrations instead - they never drop a table:

```bash
mysql -u root -p task_tracker < sql/migrations/002_level2_status_search.sql
mysql -u root -p task_tracker < sql/migrations/003_level3_auth.sql
```

### 2.2 Configure the application

```bash
cp config.sample.php config.php        # Windows: copy config.sample.php config.php
```

Then edit `config.php`:

```php
'db' => [
    'host'    => '127.0.0.1',
    'port'    => 3306,          // XAMPP: 3306, MAMP: 8889
    'name'    => 'task_tracker',
    'user'    => 'root',
    'pass'    => '',            // your MySQL password
    'charset' => 'utf8mb4',
],
```

`config.php` is listed in `.gitignore`, so real credentials never get committed.
Only `config.sample.php` - which holds placeholders - is in the repository.

### 2.3 Run it

**XAMPP:** put the project in `htdocs` (for example `htdocs/task-tracker`) and
start Apache and MySQL. Then open

```
http://localhost/task-tracker/public/
```

The **web root must be the `public` folder**. `src/`, `sql/`, `config.php` and
`docs/` sit outside it on purpose. If the whole project folder is published by
accident, the `.htaccess` files inside those directories deny requests on
Apache, but the correct fix is to point the vhost at `public/`.

**PHP's built-in server** (simplest, no Apache needed):

```bash
php -S localhost:8000 -t public
```

then open <http://localhost:8000/>.

---

## 3. Test logins

These accounts are created by `sql/database.sql` and are **for local testing
only** - change or delete them before using the app anywhere real.

| Role  | Email               | Password     |
|-------|---------------------|--------------|
| admin | `admin@example.com` | `Admin@1234` |
| user  | `alice@example.com` | `Alice@1234` |
| user  | `bob@example.com`   | `Bob@1234`   |

Passwords in the database are bcrypt hashes (`$2y$10$...`, produced by
`password_hash()`); the plain values above appear only in this README and in
`sql/database.sql`'s comment. Alice owns five tasks, Bob five, the admin four,
so ownership and the admin view are visible straight away.

You can also register a new account - it always gets the `user` role.

---

## 4. Roles and permissions

| Action | Logged out | `user` | `admin` |
|--------|-----------|--------|---------|
| See the task list | redirected to `login.php` | own tasks only | every user's tasks, with an **Owner** column |
| Summary cards, search, filters, pagination | - | counted over own tasks | counted over all tasks |
| Add a task | - | yes, becomes the owner | yes, becomes the owner |
| Edit title / description / status / priority / due date | - | own tasks only | **own tasks only** |
| Change status from the list dropdown | - | own tasks only | **own tasks only** |
| Delete a task | - | own tasks only | any task |
| Register / sign in / sign out | yes | yes | yes |

The admin difference is deliberate: an admin may **see everything and delete
anything**, but may **not rewrite another user's task** - that keeps the
accountability of ownership. When an admin opens a task owned by somebody else,
the status cell is shown as a read-only badge and the Edit button is absent.

Every one of those rules is enforced on the server, in SQL. The buttons merely
reflect it: hiding a link is not a permission check.

---

## 5. Security measures

**SQL injection**
- One PDO connection, created with `ATTR_ERRMODE => ERRMODE_EXCEPTION` and
  `ATTR_EMULATE_PREPARES => false`, so MySQL itself receives the parameterised
  queries.
- Every statement that accepts a value is a prepared statement with named
  placeholders. There is no string concatenation of request data into SQL
  anywhere - `TaskRepository::buildWhere()` assembles a WHERE clause only from
  fixed, whitelisted fragments and returns the values separately as parameters.
- Even `LIMIT`/`OFFSET` are bound (`PDO::PARAM_INT`) after being clamped.
- Filter values (`status`, `priority`) must be in a PHP whitelist before they
  are used at all; page numbers are clamped to the real range.
- `%` and `_` in a search term are escaped, so searching `100%` does not turn
  into a match-everything wildcard.

**Passwords**
- `password_hash($p, PASSWORD_DEFAULT)` on registration, `password_verify()` on
  login. The stored value is a 60-character bcrypt hash; the plain password is
  never saved, logged or echoed.
- Passwords longer than 200 characters are refused rather than silently
  truncated by bcrypt's 72-byte limit.
- Login failures always answer *"Invalid email or password."* A wrong email and
  a wrong password also take a similar amount of time (an unknown account still
  pays for one `password_verify()` against a dummy hash), so the form cannot be
  used to enumerate users.

**Sessions**
- Cookie is `HttpOnly`, `SameSite=Lax`, `Secure` whenever the request is HTTPS,
  path `/`, and expires with the browser.
- `session.use_strict_mode = 1`: a session id supplied by a client that the
  server never issued is rejected.
- `session_regenerate_id(true)` on every successful login, so a session id
  fixed by an attacker before login cannot be replayed afterwards. The old CSRF
  token is discarded at the same moment.
- Logout is POST + CSRF, and clears the data then issues a fresh id.
- The user row is re-read from the database on each request, so deleting or
  demoting an account takes effect without waiting for the session to expire.

**Authorization**
- Ownership lives in the query: `WHERE id = :id AND user_id = :uid` for edits
  and status changes, and in the scope of every list, count and summary.
- Changing an id in the URL, in a hidden field or in a hand-made POST therefore
  matches no rows. An unknown id answers **404**, an id that belongs to someone
  else answers **403**.
- If a request arrives with two different ids (one in the query string, one in
  the body) it is refused with **400** instead of validating one task and
  writing to the other.
- `role` is never read from request data; registration can only create `user`.

**CSRF**
- One random 32-byte token per session (`random_bytes`), emitted as a hidden
  input in every state-changing form - add, edit, delete, status, login,
  register, logout.
- Verified on POST with `hash_equals()` before any database write; a mismatch
  stops the request with a 400 page.

**Output and errors**
- Every dynamic value reaches the browser through `e()`, i.e.
  `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`. That covers titles,
  descriptions, names, emails, query strings echoed back into the search box
  and titles embedded in the JavaScript delete confirmation.
- `display_errors` is off. A custom exception/error handler plus a shutdown
  handler write the class, message and location with `error_log()` and show one
  generic, friendly page - never a stack trace, SQL text, table name, file path
  or credential.
- Deleting is POST-only (`405` with an `Allow: POST` header for anything else);
  pages that only read refuse PUT/PATCH/DELETE for the same reason.
- `?next=` redirect targets are restricted to root-relative local paths, so the
  login redirect cannot be turned into an open redirect.

---

## 6. Project layout

```
config.sample.php   committed template; copy to config.php (git-ignored)
public/             the web root
  index.php         landing: routes to login or the task list
  login.php register.php logout.php
  tasks/            index.php (list/search/filter/pagination/cards)
                    create.php  edit.php  delete.php (POST)  status.php (POST)
  assets/           css/style.css, js/app.js
src/
  config.php        bootstrap: config, error handling, session hardening
  db.php            PDO factory (prepared statements, no emulation)
  helpers.php       e(), URLs, redirects, flash, request access, validation
  csrf.php          token generation and verification
  auth.php          register / login / logout / require_login / require_admin
  TaskRepository.php  every task query, including the ownership rules
templates/          header.php, footer.php, task_form.php, error.php
sql/                database.sql (complete) + migrations/002, 003
docs/               TESTING.md, NOTES.md, verify.sh, screenshots/SCREENSHOTS.md
```

---

## 7. Database backup and restore

Backup (from a terminal; on XAMPP use `C:\xampp\mysql\bin\mysqldump`):

```bash
mysqldump -u root -p --default-character-set=utf8mb4 \
    --single-transaction --routines --triggers \
    task_tracker > backup-task-tracker-$(date +%F).sql
```

Add `-h 127.0.0.1 -P 3306` if the server is not local, and
`--add-drop-database --databases task_tracker` to include the
`CREATE DATABASE` statement.

Restore into an existing (or newly created) database:

```bash
mysql -u root -p task_tracker < backup-task-tracker-2026-11-12.sql
```

Restore the whole database including its creation:

```bash
mysql -u root -p < backup-task-tracker-2026-11-12.sql
```

Notes:
- `--single-transaction` keeps InnoDB consistent without locking the tables.
- A backup contains the bcrypt **hashes**, which are worth protecting: keep the
  file out of the repository and out of a public web folder.
- To get the demo data back after experimenting, re-import `sql/database.sql` -
  that drops and recreates both tables.

Verify a restore quickly:

```bash
mysql -u root -p -e "USE task_tracker; SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM tasks;"
```

---

## 8. Checking it yourself

`docs/TESTING.md` lists the manual cases with the results obtained on PHP
8.3.36 + MySQL 8.0.45.

There is also a script that repeats all of them: `docs/verify.sh` drives the
running app with curl (one cookie jar per identity) and confirms each result
with a direct `mysql` query - 143 checks covering anonymous access, session
cookies, login, CSRF, cross-user authorization, registration rules, admin
limits, search/filter/pagination, validation, CRUD, logout, error hygiene, the
foreign-key cascade and the `config.php` failure paths.

It **drops and re-imports the `users` and `tasks` tables**, so it refuses to run
at all unless you say so explicitly:

```bash
php -S 127.0.0.1:8000 -t public          # in one terminal
I_WIPE_THE_DATABASE=yes bash docs/verify.sh
```

Use against a testing database only. If your MySQL is not on the default port
or the client is not in `PATH`, pass it: `DB_PORT=3306 MYSQL_BIN=/path/to/mysql
I_WIPE_THE_DATABASE=yes bash docs/verify.sh`. Exit code 0 means every check
passed; each one prints a `PASS`/`FAIL` line.

The security-critical checks are also quick to repeat by hand:

1. Sign in as Alice, open a task of your own, note its id, then change the id in
   the URL to one of Bob's tasks - you get a 403 page, not Bob's data.
2. Delete a task, then reload the page and copy the delete form's `action` into
   the address bar - a GET is refused with 405.
3. Submit the same form without the `_csrf` hidden field (or with it altered) -
   400, and nothing changed.
4. Save a task whose title is 160 characters long - rejected on the server,
   again, even if the browser's own limits are bypassed.
5. Search for `100%` or `' OR 1=1--` - treated as literal text.
