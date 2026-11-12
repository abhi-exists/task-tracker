# Screenshots to capture

No screenshots are included: this deliverable was built and verified in a
terminal-only environment (PHP built-in server + curl), with no browser
available to render the pages. The list below is exactly what should be
captured, so the folder can be filled in by running the app once.

**Before starting:** import `sql/database.sql`, sign in as `alice@example.com`
(`Alice@1234`) for shots 1-8 and as `admin@example.com` (`Admin@1234`) for the
admin shot. Use a 1440x900 desktop window, and one 390x844 mobile capture for
the responsive check. Browser zoom 100%.

| File name | Page | What must be visible |
|-----------|------|----------------------|
| `01-login.png` | `/login.php` | Sign-in card with email + password fields, the "Register" link, no navbar user area |
| `02-dashboard.png` | `/tasks/index.php` as Alice | Navbar with Alice's name and the `user` badge, the four summary cards (Total / Pending / In Progress / Completed), the search + status + priority filter panel, and the task table with status dropdowns, priority badges, due dates and Edit/Delete buttons |
| `03-add-form-empty.png` | `/tasks/create.php` | Blank form: required-title asterisk, status select defaulting to *Pending*, priority defaulting to *Medium*, empty optional due date, character counters at 0/150 and 0/2000 |
| `04-add-success.png` | `/tasks/index.php` immediately after saving a new task | Green "Task added." flash alert above the table, with the new row visible |
| `05-edit-form.png` | `/tasks/edit.php?id=<one of Alice's tasks>` | Heading "Edit task #N", the row's existing values filled in, counters reflecting the current length, Cancel link |
| `06-validation-error.png` | `/tasks/create.php` after submitting a 160-character title | Red bordered title field, message "Title must be 150 characters or fewer (currently 160).", the over-length counter in red, **and the submitted text still in the field** |
| `07-search-filter.png` | `/tasks/index.php?q=report&status=Pending` | Search box containing `report`, status select showing *Pending*, the "Showing 1-2 of 2 tasks" caption, the "Clear all filters" link, and the matching rows only |
| `08-pagination.png` | `/tasks/index.php?page=2` (Alice needs 11+ rows; otherwise use the admin account) | "Showing 11-…" caption with page 2 highlighted in the pagination control |
| `09-delete-confirm.png` | `/tasks/index.php`, after clicking Delete on a row | The Bootstrap modal "Please confirm" naming the task, with Cancel and "Yes, delete" buttons |
| `10-admin-view.png` | `/tasks/index.php` as the admin | "All tasks" heading, admin note under it, the extra **Owner** column showing names such as "Alice Example", `admin` badge in the navbar, and a foreign task's status rendered as a read-only badge with no Edit button |
| `11-403-denied.png` | `/tasks/edit.php?id=<a task owned by Bob>` while signed in as Alice | The 403 page: big "403", "Only the owner of a task can change its contents or status.", and the "Back to Task Tracker" button - and nothing else, no error detail |
| `12-404-not-found.png` | `/tasks/edit.php?id=999999` as Alice | 404 page with "That task does not exist." |
| `13-logout-or-anon-redirect.png` | `/tasks/index.php` with no session (or the page you land on after signing out) | The login page reached by redirect, optionally with the "Please sign in to continue." flash still showing |
| `14-mobile-dashboard.png` | `/tasks/index.php` as Alice at 390x844 | Cards stacked two per row, the table horizontally scrollable, navbar actions still reachable |

Suggested order to make them in one sitting: 1, 2, 3, 4, 5, 6, 7, 8, 9 (cancel,
don't delete), 10, 11, 12, 14, then 13 last because it signs you out.

Shots 11 and 12 need a second account: keep Alice's session in one browser
profile and note Bob's task ids from an admin session first.
