# Notes

## Levels completed

All three levels are implemented, and each was committed separately, in order:

| Commit | Level | State |
|--------|-------|-------|
| `Level 1: basic task management (add, list, edit, delete)` | 1 | done |
| `Level 2: status workflow, search, filters, validation and pagination` | 2 | done |
| `Level 3: accounts, roles, ownership and hardening` | 3 | done |

Level 1 - schema and `tasks` table, add, list, edit, delete behind a
confirmation step, flash messages after each action.

Level 2 - status as a real MySQL ENUM with an inline dropdown on the list,
search by title and filtering by status and priority at the same time,
validation on both the client and the server with per-field messages and the
user's input preserved. Also the bonus items: dashboard summary cards,
pagination at 10 per page that respects the active search and filters, and
optional due date and priority on tasks.

Level 3 - `users` table with a cascading `tasks.user_id` foreign key,
registration, login and logout, roles, ownership enforced inside the SQL, CSRF
on every state-changing form, output escaping through one `e()` helper, and
generic error pages with the detail going to `error_log()`.

`sql/database.sql` is the complete final schema; `sql/migrations/` shows how a
Level 1 or Level 2 database is upgraded without dropping data. Both paths were
run against a live MySQL instance (see `docs/TESTING.md`, case L3-38).

A judgement call worth stating: the brief says an admin may view all tasks and
delete any task but may not *edit another user's task content*. **Changing
status was treated as editing content**, so status changes are owner-only for
admins too, and the list shows a read-only badge in that case. If the intended
reading is that admins may also reassign status, that is a two-line change in
`TaskRepository::updateStatus()` plus the button rendering in
`public/tasks/index.php`.

## How this was built: what I did, and what I used AI and other tools for

### What I did myself

- Read the brief, set the scope, and decided to build it as three separate
  commits, one per level, in order.
- Made the design decisions, including the interpretation of the admin rules
  (status change counts as editing content, so it is owner-only).
- Reviewed the generated code at each level, with the most attention on the
  authorization rules in `src/TaskRepository.php`, since they are the security
  boundary.
- Read the failing test cases and decided which fixes were needed (see the next
  section for the main example).
- Looked through the 17 screenshots by eye and checked the responsive layout.
- Kept `config.php` and any database dump out of version control.

### What I used AI for

I used an AI coding assistant, working from the command line, for the following:

- Writing first drafts of the application code (PHP, PDO, MySQL queries and the
  page templates).
- Writing the SQL schema and the migration files.
- Running `php -l` and MySQL checks and the curl test harness against a live
  server, then revising the code after the failures they reported.
- Drafting the documentation: this file, the README and `docs/TESTING.md`.

All AI output was treated as a draft. It was reviewed, tested and corrected
before being committed, and several of the fixes below came from that review.

### Other tools

| Tool | Used for |
|------|----------|
| PHP (`php -l`, built-in `php -S` server) | Syntax checks and local development server |
| MySQL 8.0.45 | Running the schema and both migration paths for real |
| curl + `mysql` shell script (`docs/verify.sh`) | 143 automated checks of requests and database state |
| Headless Chrome over CDP | 48 browser checks, JS error detection, and the 17 screenshots |
| Bootstrap (via CDN, with SRI) | Page styling |
| Git | One commit per level |

## One concrete change made after reviewing the generated code

The most consequential one came from a failing test case, and it was a real
security defect rather than cosmetics.

`public/tasks/edit.php` originally picked the task id like this:

```php
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);   // before
```

On a POST the form sends the id twice - once in the query string of the action
URL, once as a hidden field. With `??`, the **query string won**, while the rest
of the request (and the reviewer's mental model) assumed the body was in
charge. So a POST addressed to `edit.php?id=12` - Alice's own task, which
passes the ownership check - but carrying `id=17` (Bob's task) in the body was
accepted as a legitimate edit *of Alice's task 12*, with the body id silently
ignored. Nothing of Bob's leaked, but a request that looked like an edit of
task 17 was answered with a 302 success while quietly rewriting task 12, and
the audit trail would say the wrong thing. It also meant two code paths
disagreed about which row they were talking about, which is exactly how later
bugs get introduced.

The fix makes exactly one source authoritative per request and refuses the
ambiguous case outright:

```php
$id = is_post() ? post_int('id') : (int)get_str('id');

if (is_post() && isset($_GET['id']) && $_GET['id'] !== '' && (int)$_GET['id'] !== $id) {
    tt_fatal(
        sprintf('edit.php id mismatch: GET %s vs POST %d by actor %d', $_GET['id'], $id, $actor['id']),
        'This form does not match the task it was opened for. Please reload the page.',
        400
    );
}
```

Re-tested: that request now answers 400 and leaves both rows untouched
(`docs/TESTING.md`, case L3-16).

Other review-driven fixes of the same kind, all listed with how they were found
in `docs/TESTING.md` section 6:

- **LIKE wildcard escaping** - searching `%` used to match every task because
  the term was wrapped in `%...%` unescaped; `\`, `%` and `_` are now escaped
  before the value becomes a bound parameter.
- **`PDO::MYSQL_ATTR_FOUND_ROWS`** - saving a task without changing anything
  was reported as "that task no longer exists", because MySQL counts *changed*
  rows and `rowCount() > 0` was used as an existence test.
- **Method guards** - a `PUT` to `create.php` was being served the blank form
  as if it were a GET; every endpoint now declares the verbs it implements and
  answers 405 with an `Allow:` header otherwise.
- **Logout** was reachable by GET (a CSRF-style annoyance attack) and is now
  POST-only with a token, like every other state change.
- **Bootstrap ordering, and a path leak** - the failure paths in the README
  ("you must create config.php yourself") were only tested after the first
  documentation pass. Two problems appeared: nothing at all was shown for a
  missing/unreachable database (the test itself had been short-circuiting on an
  anonymous redirect), and a **syntax error in the hand-written `config.php`
  printed PHP's raw parse error, with the project's full filesystem path**,
  because `src/config.php` switched `display_errors` off only *after* loading
  that file - on this machine `php.ini` sets it to `On`. All error handling is
  now configured before any user file is read, with production-safe defaults,
  and the `debug` flag is applied afterwards. `docs/TESTING.md` cases F-01 to
  F-05 cover it.
- A parse-breaking stray label in `src/config.php` caught by the first
  `php -l` pass.

## Known limitations

*Security*

- No rate limiting, lockout or CAPTCHA on the login form. bcrypt makes each
  guess expensive, but nothing slows down a determined offline-style attacker;
  a production deployment would want fail2ban or an application-level counter.
- No HTTPS enforcement inside the app. The `Secure` cookie flag is set only when
  the request already arrives over TLS, so the web server must be configured for
  HTTPS and HTTP-to-HTTPS redirection.
- One CSRF token per session rather than per form, and it is not rotated after
  use. Combined with `SameSite=Lax` and `hash_equals()` checks this is adequate
  for this app, but it is not BREACH/horn-style per-request tokening.
- No password reset, no email verification, no "remember me", no second factor.
- Passwords are capped at 200 characters because bcrypt silently ignores
  everything past 72 bytes; the alternative (pre-hashing) was not implemented.
- Password policy is length-only: no complexity rules, no breached-password
  check, no rehash-on-login when `PASSWORD_DEFAULT` changes algorithm.
- Session data is stored in PHP's default on-disk session files. On a shared
  host those are readable by other accounts on some configurations; use a
  private save_path, and remember that `php -S` is a development server only.
- No security headers beyond what Bootstrap/SRI and the escaping give you: no
  CSP, no `X-Frame-Options`, no HSTS. A CSP would restrict the CDN scripts and
  the small inline event-free markup; not attempted here.
- `.htaccess` deny files only protect anything on Apache with `AllowOverride`
  enabled. The real control is the `public/` docroot.

*Function and data*

- Search only covers `title`, case-insensitively via the collation; `LIKE
  '%term%'` cannot use an index, so this stops scaling on very large tables
  (a FULLTEXT index would be the next step).
- No sorting or paging controls: page size is fixed at 10 and ordering is
  always newest-first. No filter by owner, date range or due date, and no
  "overdue only" view even though overdue rows are highlighted.
- No bulk actions, no soft delete or undo, no activity history, no attachments,
  no editing of a task's owner, and no admin UI for managing users (roles are
  changed directly in the database).
- Deleting a user cascades to their tasks permanently - there is no archive.
- Due dates are compared against the server's timezone/date (`date('Y-m-d')`),
  and there is no per-user timezone setting.
- No API, no JSON endpoints, no CSRF token endpoint; everything is a form POST.
- Only English UI text; no translation layer.

*Quality and operations*

- The automated checks are a shell script of curl + `mysql` assertions
  (`docs/verify.sh`, 143 checks, all passing on the last run) plus a headless
  Chrome session over CDP (48 checks, no uncaught JS errors) rather than a
  PHPUnit suite inside the project; there is no CI, no code-coverage measurement
  and no static analysis (Psalm/PHPStan) run. The browser driver is not shipped
  because it hard-codes this machine's Chrome path.
- `docs/screenshots/` holds the 17 real captures, produced by that browser run
  and reviewed by eye; the responsive check is an emulated 390x844 viewport, not
  a physical phone, and no screen-reader or keyboard-only pass was done.
- Styling depends on a Bootstrap CDN, so an air-gapped machine sees an
  unstyled but fully working page; vendoring the CSS/JS locally would fix it.
- Tested against MySQL 8.0.45 only. The schema should work on MariaDB 10.3+
  and MySQL 5.7+, but that was not verified here.

## Review focus for anyone taking this over

- The authorization rules in `src/TaskRepository.php` (they are the security
  boundary).
- The interpretation of what an admin may change.
- The two files that must never be committed with real values: `config.php`
  and any database dump.
