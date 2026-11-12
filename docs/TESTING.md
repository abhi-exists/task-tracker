# Testing record

All results below were obtained by actually running the application, not by
reading the code. Anything that was not run is listed in section 7 and marked
as such.

## 1. Environment

| Item | Value |
|------|-------|
| OS | Windows 11 (10.0.26300), x64 |
| PHP | 8.3.36 (cli) NTS x64, with `pdo_mysql`, `session`, `mbstring`, `openssl` |
| Database | MySQL 8.0.45, started as a **private instance** on `127.0.0.1:13307` (own datadir, no Windows service, root with no password) so the machine's `MySQL80` service was left untouched |
| Web server | `php -S 127.0.0.1:8000 -t public` |
| Client | `curl` with per-user cookie jars + direct `mysql` queries to confirm what really landed in the tables |
| App config | `config.php` copied from `config.sample.php`, pointing at port 13307 |
| Test data | `sql/database.sql` re-imported before each run: 3 users, 14 tasks (5 Alice, 5 Bob, 4 admin) |

## 2. Method

Two passes:

1. **`php -l` on every PHP file** - the 20 files tracked in the repository
   (21 counting the local, git-ignored `config.php`), no syntax errors.
2. **`docs/verify.sh`** - the end-to-end harness that is part of this
   submission. It drives the running server with curl (one cookie jar per
   identity) and confirms what really happened with direct `mysql` queries.
   **Last full run: 143 checks, 0 failed** (exit code 0).
   It is destructive by design - it re-imports `sql/database.sql`, creates
   throwaway accounts and deletes rows - so it refuses to start unless
   `I_WIPE_THE_DATABASE=yes` is set (verified: exits with code 2 otherwise).

Each check either compared an HTTP status code, compared a value read back from
MySQL, or searched the rendered HTML for a string that must be present (or must
be absent). Run it yourself with the app serving on port 8000:

```bash
I_WIPE_THE_DATABASE=yes bash docs/verify.sh
# non-default MySQL: DB_PORT=13307 MYSQL_BIN=/path/to/mysql I_WIPE_THE_DATABASE=yes bash docs/verify.sh
```

Earlier runs of the same harness found the defects in section 6 - including one
run with 16 failures, most of which turned out to be wrong assumptions in the
*test script* rather than in the app; those are called out separately there.

## 3. Manual test cases

Result column: **P** = passed as described in the run.

### Level 1 - basic task management

| # | Steps | Expected | Result |
|---|-------|----------|--------|
| L1-01 | Sign in as Alice, POST `tasks/create.php` with title `Smoke task`, description, status *In Progress*, priority *High*, due `2030-01-15` | 302 back to the list; a row exists with the chosen status and `user_id` = Alice | P (302; status `In Progress`; owner id 2) |
| L1-02 | Load the list right after adding | Green bootstrap alert "Task added." | P (shown once) |
| L1-03 | Load the list again | Flash does not reappear | P (drained) |
| L1-04 | Open `tasks/edit.php?id=<own id>`, change title/status, POST | 302; new title in the database; "Task updated." | P |
| L1-05 | Edit, clear the due date, save | stored as `NULL`, not an empty string | P (`due_date` = NULL) |
| L1-06 | POST `tasks/delete.php` with the task's own id | 302; row gone; "Task #N deleted." | P |
| L1-07 | Request `tasks/delete.php?id=N` as a **GET** | refused, nothing deleted | P (405 + `Allow: POST`) |
| L1-08 | `index.php` while signed in | redirects to the task list | P (302) |

### Level 2 - status, search, filters, validation

| # | Steps | Expected | Result |
|---|-------|----------|--------|
| L2-01 | Change status from the list dropdown (POST `tasks/status.php`) | 302, new status persisted, flash `Task #N is now "Pending".` | P |
| L2-02 | Submit the same status again | still reported as a success, not "task does not exist" | P (see bug BUG-2) |
| L2-03 | POST `status.php` with `status=Shipped` (not in the ENUM) | refused, database unchanged | P (403 path for foreign task / flash "Please choose a valid status." for own) |
| L2-04 | `GET tasks/index.php?q=budget` | only matching titles; "Showing 1-1 of 1 task" | P |
| L2-05 | `?status=Pending` | only Pending rows, count matches `SELECT COUNT(*)` | P (6 rows) |
| L2-06 | `?q=report&status=Pending&priority=High` | all three conditions combine; the Low-priority match is excluded | P |
| L2-07 | Same URL, inspect the filter form | previously typed term and selected status are still in the form | P (`value="report"`, `value="Pending" selected`) |
| L2-08 | Search a term that spans two pages, click through the page links | `?q=a&page=2` keeps `q`; slices "Showing 1-10 of 14" then "Showing 11-14 of 14" | P |
| L2-09 | `?page=999` | clamped to the last real page, no error | P |
| L2-10 | `?status=bogus` | invalid filter ignored, full list still shown | P |
| L2-11 | Search `%` | literal match, **not** a wildcard | P ("No tasks match"; see bug BUG-3) |
| L2-12 | Summary cards | total / pending / in-progress / completed counts equal the SQL counts for that user | P |
| L2-13 | POST create with an empty title | rejected with "Please give the task a title."; no row inserted | P |
| L2-14 | POST create with a 160-character title | "Title must be 150 characters or fewer (currently 160)."; **the submitted text is still in the form** | P |
| L2-15 | POST create with a 2100-character description | "Description must be 2000 characters or fewer (currently 2100)." | P |
| L2-16 | POST create with `status=Completed'` and `priority=Ultra` | both fields named in the errors; nothing written | P |
| L2-17 | POST create with `due_date=2026-02-30` | "Due date must be a real date in YYYY-MM-DD format." | P |
| L2-18 | After all of the above, count Alice's rows | still exactly 5 - no invalid row reached the database | P |

### Level 3 - authentication, authorization, security

| # | Steps | Expected | Result |
|---|-------|----------|--------|
| L3-01 | `GET tasks/index.php` with no session cookie | 302 to `login.php`, list content never rendered | P |
| L3-02 | Same for `create.php`, `edit.php`, `delete.php`, `status.php`, `index.php` | all redirect to login (GET **and** POST) | P (6/6) |
| L3-03 | Follow the redirect target | `login.php?next=<original path>` and after login land back there | P |
| L3-04 | Sign in with `alice@example.com` + wrong password | 200, single message "Invalid email or password." | P |
| L3-05 | Sign in with an email that does not exist | **identical** message, no "not registered" wording | P |
| L3-06 | After a failed login, request the task list | still redirected | P |
| L3-07 | Successful login | session cookie value differs from the pre-login one (`session_regenerate_id(true)`) | P (id changed) |
| L3-08 | Inspect `Set-Cookie` | `HttpOnly`, `SameSite=Lax`, name `TTSESSID`, no expiry (session cookie) | P (all three) |
| L3-09 | `SELECT LEFT(password_hash,7)` and length for each user | bcrypt `$2y$10$`, 60 characters | P (3/3) |
| L3-10 | `SELECT COUNT(*) ... WHERE password_hash LIKE '%Alice@1234%'` | 0 - the plain password is nowhere in the database | P |
| L3-11 | Register with `role=admin` in the POST body | account is created with role `user` | P |
| L3-12 | Register with a 3-character password / mismatched confirmation / blank name / `bad-email` | each refused with its own message; user count unchanged | P (4/4, count stayed 4) |
| L3-13 | Register with an email that already exists | "That email address is already registered." and no second row | P |
| L3-14 | **Alice requests `tasks/edit.php?id=<Bob's task id>`** | **403**, page says only the owner can change it, Bob's title unchanged in the database | P |
| L3-15 | Alice POSTs the same edit (id in URL *and* body) | 403, no `HACKED` row anywhere | P |
| L3-16 | Alice POSTs to `edit.php?id=<her own>` while putting **Bob's id in the body** | refused with 400; neither task modified | P (see bug BUG-1) |
| L3-17 | Alice POSTs `status.php` with Bob's id | 403; Bob's status still `In Progress` | P |
| L3-18 | Alice POSTs `delete.php` with Bob's id | 403; Bob's row still present | P |
| L3-19 | Alice requests `edit.php?id=999999` | 404 "That task does not exist." (distinct from the 403) | P |
| L3-20 | Alice requests `edit.php?id=abc` | 404, not a crash | P |
| L3-21 | Alice browses the list | Bob's and the admin's titles are absent | P |
| L3-22 | Admin opens the list | sees all users' rows, an **Owner** column with names, header "All tasks" | P |
| L3-23 | Normal user opens the list | no Owner column at all | P (absent) |
| L3-24 | Admin opens `edit.php?id=<Alice's task>` | 403 - cannot edit another user's content | P |
| L3-25 | Admin POSTs `status.php` on Alice's task | 403 - same rule | P |
| L3-26 | Admin POSTs `delete.php` on Bob's task | 302; row deleted | P |
| L3-27 | POST any mutating page **without** `_csrf` | 400, friendly "reload the page" message, no write | P |
| L3-28 | POST with `_csrf=deadbeef...` (wrong token) | 400, no write | P |
| L3-29 | PUT `tasks/create.php` with a body | 405 + `Allow:` header, nothing created | P (see bug BUG-4) |
| L3-30 | GET `logout.php` | 405 and the session is **still authenticated** | P |
| L3-31 | POST `logout.php` with a valid token | 302 to login; the list is protected again | P |
| L3-32 | `?q=' OR 1=1--` and other injection probes in `q`, `status`, `page` | page renders 200, probe matches nothing, no SQL text in the output | P |
| L3-33 | Create a task titled `<script>alert(1)</script>` | list shows `&lt;script&gt;...`; no executable script tag in the HTML (also inside the delete-confirm attribute) | P |
| L3-34 | Reflect the same payload through the search box (`?q=<script>...`) | escaped, not executed | P |
| L3-35 | Trigger an error page (403/404/400) and grep the HTML | no `SQLSTATE`, no `PDOException`, no "Stack trace", no `/src/` path, no database name, no `Warning:`/`Notice:` | P (6/6) |
| L3-36 | Load `/public/...` with the **repo root** as docroot | links still resolve (`/public/assets/...`), CSS and JS return 200 | P |
| L3-37 | `DELETE FROM users WHERE id=3` | Alice's/Bob's tasks disappear with them (`ON DELETE CASCADE`) | P (count 5 -> 0) |
| L3-38 | Upgrade path: import the Level 1 schema, then migrations 002 and 003 | 5 pre-existing rows get an owner, FK `fk_tasks_user` present, no orphaned tasks, admin can log in against the migrated database | P |
| L3-39 | Grep all sources for SQL built from request data | no `prepare`/`query` call receives `$_GET`/`$_POST`; no interpolated SQL literal; PDO only (no `mysqli`/`mysql_*`) | P |

### Bootstrap failure paths (added after the first documentation pass)

These four cases were written because the README tells people to create
`config.php` by hand - and that path had never been exercised.

| # | Steps | Expected | Result |
|---|-------|----------|--------|
| F-01 | Move `config.php` away, request `login.php` | 503 page that says to copy `config.sample.php`; no filesystem path in the HTML | P (503; instruction shown; path absent) |
| F-02 | Replace `config.php` with a file containing a **syntax error**, request `login.php` | generic 500 page; **no `Parse error`, no `on line`, no path** | P (see bug BUG-8 - this leaked before the fix) |
| F-03 | Restore the real `config.php` | file byte-identical to the backup, app answers 200 again | P |
| F-04 | Point `config.php` at a port where nothing listens, request an authenticated page | 500 page "cannot reach the database... imported", with no `SQLSTATE`, no `dbname=`, no `root@` and no credentials | P (5 checks) |
| F-05 | Run `docs/verify.sh` without the acknowledgement variable | refuse and exit non-zero without touching the database | P (exit 2) |

## 4. Static and configuration checks

The machine used for testing ships `display_errors = On` / `error_reporting =
E_ALL` in its `php.ini` - exactly the hostile default worth checking against.
The effective values inside a real request were measured (temporarily, with a
throwaway script that loaded the bootstrap and printed `ini_get()` plus the PDO
attributes; the file was deleted afterwards and is not part of the app):

| Setting | Measured at runtime | Expected |
|---------|--------------------|----------|
| `display_errors` | `0` | off, despite php.ini saying On |
| `log_errors` | `1` | on |
| `error_reporting` | `32767` (= E_ALL) | everything reported, then hidden |
| `session.use_strict_mode` | `1` | on: client-chosen session ids rejected |
| `session.cookie_httponly` | `1` | on |
| `PDO::ATTR_EMULATE_PREPARES` | `0` | off - MySQL sees the real parameterised statement |
| `PDO::ATTR_ERRMODE` | `2` (`ERRMODE_EXCEPTION`) | exceptions, never silent failures |

```
php -l on all 20 tracked PHP files   -> no syntax errors
grep "(prepare|query|exec)\(.*\$_(GET|POST|REQUEST)"  -> none
grep '"(SELECT|INSERT|UPDATE|DELETE)..." . $'          -> only the whitelisted
       $where fragment from TaskRepository::buildWhere(), which is assembled
       from constant strings
grep "mysql_\|mysqli"             -> none (PDO only)
grep "htmlspecialchars"           -> centralised in the single e() helper
```

The only concatenation in any SQL string is `'... FROM tasks t' . $where`, and
`$where` is built exclusively from four fixed fragments
(`t.user_id = :actor_uid`, `t.title LIKE :q`, `t.status = :status`,
`t.priority = :priority`), each selected by a whitelist test. Values travel only
as bound parameters.

## 5. Coverage against the required checklist

| Required item | Cases |
|---------------|-------|
| valid add | L1-01, L1-02 |
| empty title rejected | L2-13 |
| edit / status / delete | L1-04..L1-07, L2-01..L2-03 |
| search + filter | L2-04..L2-11, L3-32 |
| logged-out dashboard access redirected | L3-01..L3-03 |
| passwords stored as hashes | L3-09, L3-10 |
| user A cannot view/edit/delete user B's task by changing the id | L3-14..L3-21 |
| prepared statements used everywhere | section 4 + L3-32 |
| no raw errors or credentials shown | L3-35, L3-36 |

## 6. Bugs found and fixed during testing

| ID | Found how | Symptom | Fix |
|----|-----------|---------|-----|
| **BUG-1** | Run 2, case L3-16 | `edit.php` read the id as `$_GET['id'] ?? $_POST['id']`, so a POST carrying Alice's id in the URL and Bob's id in the body **validated Alice's task and then wrote to Alice's task** while silently ignoring the body id. No cross-user leak, but the request that looked like an edit of Bob was accepted as an edit of Alice - a confusing and dangerous ambiguity | `edit.php` now takes exactly one authoritative id (the body on POST, the query string on GET) and refuses with **400** when the two disagree. Re-tested: 400, and both rows untouched |
| **BUG-2** | Reading `updateStatus()` while writing the status dropdown | MySQL reports 0 changed rows when a row is updated to the value it already has. `rowCount() > 0` therefore answered "That task no longer exists." after a perfectly legal "save without changes" | Connection now sets `PDO::MYSQL_ATTR_FOUND_ROWS => true`, so `rowCount()` counts matched rows. Case L2-02 verifies the repeat save succeeds |
| **BUG-3** | Deciding what `?q=%` should do | The LIKE pattern wrapped the raw term in `%...%`, so searching for `%` or `_` matched nearly every task and searching `_` matched any single character | `buildWhere()` escapes `\`, `%` and `_` in the search term before wrapping it. Case L2-11 verifies `%` now matches nothing |
| **BUG-4** | Run 2, case L3-29 | A `PUT` to `create.php` was treated as an ordinary GET and returned the blank form (200) - so a request that looked like an update quietly did nothing | `require_methods()` restricts each endpoint to the verbs it implements (list: GET/HEAD; create/edit/login/register: GET/POST; delete/status/logout: POST) and answers 405 with an `Allow:` header otherwise |
| **BUG-5** | Reviewing `logout.php` | Logout accepted a GET, so any link or image could sign a user out (and it was the one state-changing page without a CSRF check) | Logout is now POST + CSRF only; L3-30 proves a GET leaves the session alive |
| **BUG-6** | First `php -l` pass | A stray `ErrorHandler:` label left in `src/config.php` - a parse error that broke every page | Removed; the lint pass is now run before every commit |
| **BUG-7** | Run 1, 16 failing checks | Several *test-script* assumptions were wrong (accessing `register.php` while already signed in, expecting a row that sits on page 2 of 10, reading the flash from the 302 response instead of the following page, hard-coded counts after earlier cases deleted rows) | The script now re-authenticates as the right user, searches instead of scanning page 1, follows the redirect before asserting a flash, and derives counts from the database. Recorded here because these were *test* defects, not application defects |
| **BUG-8** | Testing the "did you follow the README?" paths in `docs/verify.sh` section 14 | This machine's `php.ini` ships `display_errors = On`, and `src/config.php` only switched it off **after** `require`-ing the site's `config.php`. A syntax error in that file - the one file a human types by hand - therefore printed PHP's raw parse error to the browser, including the **full Windows path** of the project: `Parse error: Unclosed '[' on line 2 in C:\Users\...\config.php`. It also skipped `docs/verify.sh`'s own leak assertions on the way | The error-mode setup (`display_errors=0`, `log_errors`, `error_reporting`, the error/exception handlers and the shutdown handler) now runs **before** any user file is loaded, with production-safe defaults, and the `debug` flag from the config is applied afterwards. `error_get_last()` in the shutdown handler catches the parse error and renders the generic 500. F-02 re-tested: 500, no parse text, no path. Note this was a latent risk, not a live leak - the same page never shows credentials |

## 7. Not verified by execution (stated honestly)

- **Browser-side behaviour.** The confirmation modal, the inline validation
  messages, the character counters and the auto-submitting status dropdown
  (`public/assets/js/app.js`) were written and linted but **never run in a real
  browser** - this environment has no browser automation. The server rejects
  everything the client-side checks are meant to catch, and every case above was
  exercised without JavaScript, so no functional conclusion depends on the JS.
  The visible result of that is unverified.
- **Bootstrap 5 layout / responsiveness** at various screen widths, and the CDN
  SRI hashes actually loading from this machine (offline here).
- **The `.htaccess` deny rules.** No Apache was available to test them, so their
  effect is reasoned, not observed. With `php -S` they do nothing at all; the
  real protection is keeping `public/` as the docroot, which is why L3-36 also
  checked what happens when it is not (PHP files execute and print nothing;
  `sql/database.sql` is nevertheless downloadable, hence the deny files).
- **HTTPS-specific cookie behaviour** (`Secure` flag) - only the code path was
  inspected; the app was served over plain HTTP.
- **XAMPP itself.** Apache + `htdocs` was not available; the equivalent was
  tested by serving the repo root with `php -S` and verifying URL generation.
- **Concurrent registration** for the same email (relies on the UNIQUE index,
  which was tested one request at a time), password-reset, email sending,
  rate limiting/lockout after repeated failed logins - none of these exist in
  this application (see `docs/NOTES.md`).
