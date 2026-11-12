# Task Tracker

A small, secure task-management web app written in **core PHP 8** and **MySQL**.
No frameworks, no Composer: just PHP, PDO and MySQL, served as plain `.php` files with Bootstrap 5 from a CDN.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B%20%7C%208.x-4479A1?logo=mysql&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-10.3%2B-003545?logo=mariadb&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)
![Dependencies](https://img.shields.io/badge/Composer-none-success)

## Table of contents

- [Features](#features)
- [Quick start](#quick-start)
- [Requirements](#requirements)
- [Setup](#setup)
- [Test logins](#test-logins)
- [Roles and permissions](#roles-and-permissions)
- [Security measures](#security-measures)
- [Project layout](#project-layout)
- [Database backup and restore](#database-backup-and-restore)
- [Testing](#testing)

## Features

The app was built in three levels, one commit each:

| Level | What it adds |
|:-----:|--------------|
| **1** | Database and `tasks` table; add / list / edit / delete with a confirmation step; flash messages after every action |
| **2** | Status as a real `ENUM` with inline changes; search by title; filter by status and priority; validation on both client and server; due date; priority; summary cards; pagination |
| **3** | Accounts, roles and permissions; CSRF protection; output escaping; safe error handling |

## Quick start

```bash
# 1. Create the database, tables and sample data
mysql -u root -p < sql/database.sql

# 2. Configure the app
cp config.sample.php config.php        # Windows: copy config.sample.php config.php
#    ...then edit config.php with your MySQL credentials

# 3. Run it
php -S localhost:8000 -t public
```

Open <http://localhost:8000/> and sign in with a [test login](#test-logins).

## Requirements

- **PHP 8.0 or newer** with the `pdo_mysql` and `session` extensions (both enabled by default in XAMPP). Check with `php -m`.
- **MySQL 5.7+ / 8.x** or **MariaDB 10.3+**. The schema uses `ENUM`, `utf8mb4`, InnoDB and a cascading foreign key.
- A browser. Bootstrap 5 and its icons load from a CDN, so styling needs internet access. Every feature still works without it, just unstyled.
- Optionally `curl`, to re-run the checks in `docs/TESTING.md`.

There is no Composer install step.

## Setup

### 1. Create the database and import the SQL

Choose one:

| Option | How |
|--------|-----|
| **Command line** | `mysql -u root -p < sql/database.sql` (from the project folder) |
| **phpMyAdmin** | Click **Import**, choose `sql/database.sql`, press **Go** |
| **MySQL Workbench** | Open `sql/database.sql` and run the whole script |

`sql/database.sql` is complete and repeatable. It creates `task_tracker`, the `users` and `tasks` tables with their indexes and foreign key, and the sample data.

> [!WARNING]
> Re-importing `sql/database.sql` **drops and recreates the `users` and `tasks` tables**. That makes it a handy "reset to sample data" step, but don't run it on data you want to keep.

<details>
<summary><strong>Upgrading an existing database (keeps your data)</strong></summary>

Apply the migrations instead. They never drop a table:

```bash
mysql -u root -p task_tracker < sql/migrations/002_level2_status_search.sql
mysql -u root -p task_tracker < sql/migrations/003_level3_auth.sql
```

</details>

### 2. Configure the application

```bash
cp config.sample.php config.php        # Windows: copy config.sample.php config.php
```

Edit `config.php`:

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

`config.php` is listed in `.gitignore`, so real credentials are never committed. Only `config.sample.php`, which holds placeholders, is in the repository.

### 3. Run it

**PHP's built-in server** (simplest, no Apache needed):

```bash
php -S localhost:8000 -t public
```

Then open <http://localhost:8000/>.

**XAMPP:** put the project in `htdocs` (for example `htdocs/task-tracker`), start Apache and MySQL, then open:

```
http://localhost/task-tracker/public/
```

> [!IMPORTANT]
> The **web root must be the `public` folder**. `src/`, `sql/`, `config.php` and `docs/` sit outside it on purpose. If the whole project folder is published by accident, the `.htaccess` files inside those directories deny requests on Apache, but the correct fix is to point the vhost at `public/`.

## Test logins

Created by `sql/database.sql`.

> [!CAUTION]
> These accounts are **for local testing only**. Change or delete them before using the app anywhere real.

| Role  | Email               | Password     |
|-------|---------------------|--------------|
| admin | `admin@example.com` | `Admin@1234` |
| user  | `alice@example.com` | `Alice@1234` |
| user  | `bob@example.com`   | `Bob@1234`   |

Passwords are stored as bcrypt hashes (`$2y$10$...`, from `password_hash()`). The plain values appear only here and in a comment in `sql/database.sql`.

Alice owns five tasks, Bob five and the admin four, so ownership and the admin view are visible straight away. You can also register a new account; it always gets the `user` role.

## Roles and permissions

| Action | Logged out | `user` | `admin` |
|--------|:----------:|:------:|:-------:|
| See the task list | redirected to `login.php` | own tasks only | every user's tasks, with an **Owner** column |
| Summary cards, search, filters, pagination | – | over own tasks | over all tasks |
| Add a task | – | yes (becomes owner) | yes (becomes owner) |
| Edit title / description / status / priority / due date | – | own tasks only | **own tasks only** |
| Change status from the list dropdown | – | own tasks only | **own tasks only** |
| Delete a task | – | own tasks only | **any task** |
| Register / sign in / sign out | yes | yes | yes |

The admin difference is deliberate: an admin may **see everything and delete anything**, but may **not rewrite another user's task**, which preserves the accountability of ownership. When an admin views a task owned by somebody else, the status shows as a read-only badge and the Edit button is absent.

Every rule is enforced **on the server, in SQL**. The buttons merely reflect it: hiding a link is not a permission check.

## Security measures

<details open>
<summary><strong>SQL injection</strong></summary>

- One PDO connection with `ATTR_ERRMODE => ERRMODE_EXCEPTION` and `ATTR_EMULATE_PREPARES => false`, so MySQL itself receives the parameterised queries.
- Every statement that accepts a value is a prepared statement with named placeholders. Request data is never concatenated into SQL. `TaskRepository::buildWhere()` builds a `WHERE` clause only from fixed, whitelisted fragments and returns the values separately as parameters.
- Even `LIMIT`/`OFFSET` are bound (`PDO::PARAM_INT`) after being clamped.
- Filter values (`status`, `priority`) must be in a PHP whitelist before use; page numbers are clamped to the real range.
- `%` and `_` in a search term are escaped, so searching `100%` is not a match-everything wildcard.

</details>

<details open>
<summary><strong>Passwords</strong></summary>

- `password_hash($p, PASSWORD_DEFAULT)` on registration, `password_verify()` on login. The stored value is a 60-character bcrypt hash; the plain password is never saved, logged or echoed.
- Passwords longer than 200 characters are refused rather than silently truncated by bcrypt's 72-byte limit.
- Login failures always answer *"Invalid email or password."* An unknown account still pays for one `password_verify()` against a dummy hash, so response times are similar and the form can't be used to enumerate users.

</details>

<details open>
<summary><strong>Sessions</strong></summary>

- Cookie is `HttpOnly`, `SameSite=Lax`, `Secure` whenever the request is HTTPS, path `/`, and expires with the browser.
- `session.use_strict_mode = 1`: a session id supplied by a client that the server never issued is rejected.
- `session_regenerate_id(true)` on every successful login, so a session id fixed by an attacker beforehand can't be replayed. The old CSRF token is discarded at the same moment.
- Logout is POST + CSRF, clears the data, then issues a fresh id.
- The user row is re-read from the database on each request, so deleting or demoting an account takes effect immediately.

</details>

<details open>
<summary><strong>Authorization</strong></summary>

- Ownership lives in the query: `WHERE id = :id AND user_id = :uid` for edits and status changes, and in the scope of every list, count and summary.
- Changing an id in the URL, a hidden field or a hand-made POST therefore matches no rows. An unknown id answers **404**; an id that belongs to someone else answers **403**.
- A request carrying two different ids (one in the query string, one in the body) is refused with **400**, instead of validating one task and writing to the other.
- `role` is never read from request data; registration can only create `user`.

</details>

<details open>
<summary><strong>CSRF</strong></summary>

- One random 32-byte token per session (`random_bytes`), emitted as a hidden input in every state-changing form: add, edit, delete, status, login, register, logout.
- Verified on POST with `hash_equals()` before any database write. A mismatch stops the request with a 400 page.

</details>

<details open>
<summary><strong>Output and errors</strong></summary>

- Every dynamic value reaches the browser through `e()`, i.e. `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`. That covers titles, descriptions, names, emails, search terms echoed back into the search box, and titles embedded in the JavaScript delete confirmation.
- `display_errors` is off. A custom exception/error handler plus a shutdown handler log the class, message and location with `error_log()` and show one generic, friendly page: never a stack trace, SQL text, table name, file path or credential.
- Deleting is POST-only (`405` with an `Allow: POST` header otherwise); read-only pages refuse PUT/PATCH/DELETE for the same reason.
- `?next=` redirect targets are restricted to root-relative local paths, so the login redirect can't be used as an open redirect.

</details>

## Project layout

```
config.sample.php     committed template; copy to config.php (git-ignored)
public/               the web root
├── index.php         landing: routes to login or the task list
├── login.php  register.php  logout.php
├── tasks/            index.php (list / search / filter / pagination / cards)
│                     create.php  edit.php  delete.php (POST)  status.php (POST)
└── assets/           css/style.css, js/app.js
src/
├── config.php        bootstrap: config, error handling, session hardening
├── db.php            PDO factory (prepared statements, no emulation)
├── helpers.php       e(), URLs, redirects, flash, request access, validation
├── csrf.php          token generation and verification
├── auth.php          register / login / logout / require_login / require_admin
└── TaskRepository.php  every task query, including the ownership rules
templates/            header.php, footer.php, task_form.php, error.php
sql/                  database.sql (complete) + migrations/002, 003
docs/                 TESTING.md, NOTES.md, verify.sh, screenshots/SCREENSHOTS.md
```

## Database backup and restore

**Backup** (on XAMPP use `C:\xampp\mysql\bin\mysqldump`):

```bash
mysqldump -u root -p --default-character-set=utf8mb4 \
    --single-transaction --routines --triggers \
    task_tracker > backup-task-tracker-$(date +%F).sql
```

Add `-h 127.0.0.1 -P 3306` if the server isn't local, and `--add-drop-database --databases task_tracker` to include the `CREATE DATABASE` statement.

**Restore** into an existing (or newly created) database:

```bash
mysql -u root -p task_tracker < backup-task-tracker-2026-11-12.sql
```

**Restore everything**, including database creation (needs a backup made with `--databases`):

```bash
mysql -u root -p < backup-task-tracker-2026-11-12.sql
```

**Verify a restore:**

```bash
mysql -u root -p -e "USE task_tracker; SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM tasks;"
```

Notes:

- `--single-transaction` keeps InnoDB consistent without locking the tables.
- A backup contains the bcrypt **hashes**. Keep the file out of the repository and out of any public web folder.
- To get the demo data back after experimenting, re-import `sql/database.sql`.

## Testing

`docs/TESTING.md` lists the manual cases with results obtained on **PHP 8.3.36 + MySQL 8.0.45**.

`docs/verify.sh` repeats all of them: it drives the running app with curl (one cookie jar per identity) and confirms each result with a direct `mysql` query. It runs **143 checks** covering anonymous access, session cookies, login, CSRF, cross-user authorization, registration rules, admin limits, search/filter/pagination, validation, CRUD, logout, error hygiene, the foreign-key cascade and the `config.php` failure paths.

> [!WARNING]
> The script **drops and re-imports the `users` and `tasks` tables**, so it refuses to run unless you say so explicitly. Use it against a testing database only.

```bash
php -S 127.0.0.1:8000 -t public          # in one terminal
I_WIPE_THE_DATABASE=yes bash docs/verify.sh
```

If MySQL isn't on the default port or the client isn't in `PATH`:

```bash
DB_PORT=3306 MYSQL_BIN=/path/to/mysql I_WIPE_THE_DATABASE=yes bash docs/verify.sh
```

Exit code `0` means every check passed; each check prints a `PASS` or `FAIL` line.

### Quick manual security checks

1. Sign in as Alice, open one of your tasks, note its id, then change the id in the URL to one of Bob's tasks. You get a **403** page, not Bob's data.
2. Delete a task, reload, and paste the delete form's `action` into the address bar. A GET is refused with **405**.
3. Submit a form without the `_csrf` hidden field (or with it altered). **400**, and nothing changes.
4. Save a task with a 160-character title. It is rejected on the server even if the browser's own limits are bypassed.
5. Search for `100%` or `' OR 1=1--`. Both are treated as literal text.
