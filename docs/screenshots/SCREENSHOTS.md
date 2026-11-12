# Screenshots

These were captured automatically from the running application in headless
Chrome 157 (1440x900 desktop, one 390x844 mobile frame), driven over the DevTools
protocol against `php -S 127.0.0.1:8000 -t public` with `sql/database.sql`
freshly imported. Every image is a real render of the app, not a mockup, and
each was reviewed before committing.

The same session that produced them ran the 48 browser assertions described in
`docs/TESTING.md` section 3b, so each picture is paired with a check of the
behaviour it shows.

| File | Screen | Signed in as | Notes |
|------|--------|--------------|-------|
| `01-login.png` | Sign-in page | anonymous | the public entry point |
| `02-dashboard.png` | Task list / dashboard | Alice | "Welcome back." flash, cards Total 5 / Pending 3 / In Progress 1 / Completed 1, filter panel, status dropdowns, priority badges, due dates, Edit + Delete per row |
| `03-add-form-empty.png` | Add task, untouched | Alice | required marker, defaults Pending / Medium, empty optional due date, counters at 0/150 and 0/2000 |
| `04-add-success.png` | List right after a save | Alice | green "Task added." flash with the new "Screenshot task" row in place |
| `05-edit-form.png` | Edit task | Alice | heading "Edit task #15" with the row's values prefilled |
| `06-validation-error.png` | **Server-side** rejection | Alice | submitted with `form.submit()` so the JavaScript checks are bypassed: red title field, "Title must be 150 characters or fewer (currently 160).", counter in red at 160/150, and the typed text still in the field |
| `07-search-filter.png` | Search + status filter | Alice | `?q=report&status=Pending`, both controls keep their values |
| `08-pagination.png` | Page 2 | admin | remaining rows, page 2 marked active |
| `09-delete-confirm.png` | Delete confirmation modal | Alice | Bootstrap modal naming the task; it was cancelled afterwards, nothing was deleted |
| `10-admin-view.png` | All tasks | admin | Owner column with names and emails, read-only badges on other users' rows, Edit only on the admin's own tasks |
| `11-403-denied.png` | 403 page | admin | "Only the owner of a task can change its contents or status." - no technical detail |
| `12-404-not-found.png` | 404 page | admin | unknown task id |
| `13-logout-or-anon-redirect.png` | Signed out | anonymous | login page reached through the navbar Sign out button; the user area is gone |
| `14-mobile-dashboard.png` | Dashboard at 390x844 | Alice | cards two per row, table wrapper scrolls horizontally |
| `15-validation-client-blocked.png` | **Client-side** rejection | Alice | the same 160-character title stopped by `app.js` before any request leaves the browser |
| `16-register-client-validation.png` | Register form, JS validation | anonymous | blank name, `not-an-email`, a 3-character password and a mismatched confirmation, all four flagged in the page without a request going out |
| `17-register-server-validation.png` | Register form, server rejection | anonymous | the same input submitted with the client checks bypassed - PHP refuses it and keeps the typed email address |

06 and 15 are deliberately a pair: the first proves the server re-validates even
when the browser check is skipped, the second proves the browser check fires
first for real users. 16 and 17 repeat that pair for the register form.

## Re-capturing them

```bash
php -S 127.0.0.1:8000 -t public &
mysql -u root -p < sql/database.sql
"/path/to/chrome.exe" --headless=new --remote-debugging-port=9222 \
    --user-data-dir=/tmp/tt-chrome about:blank &
node path/to/browser-driver.mjs        # the CDP script, kept outside this repo
```

The driver is not part of the deliverable because it hard-codes this machine's
Chrome path and port; the checks it performs are written up in `TESTING.md`.
