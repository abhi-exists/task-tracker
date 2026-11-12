#!/usr/bin/env bash
#
# verify.sh - end-to-end checks for Task Tracker, driven with curl + the mysql
# client. It reproduces every case listed in docs/TESTING.md against a RUNNING
# instance of the app and prints one PASS/FAIL line per check.
#
#   1. import sql/database.sql
#   2. start the app:            php -S 127.0.0.1:8000 -t public
#   3. configure the variables below (or pass them as env vars), then:
#         I_WIPE_THE_DATABASE=yes bash docs/verify.sh
#
# ---------------------------------------------------------------------------
# WARNING - THIS SCRIPT IS DESTRUCTIVE. It re-imports sql/database.sql (which
# drops and recreates the users and tasks tables), creates throwaway accounts,
# deletes tasks and users and edits config.php temporarily. Point it at a
# testing database only. The I_WIPE_THE_DATABASE guard is deliberate.
# ---------------------------------------------------------------------------

# ------------------------------- configuration -----------------------------
B=${B:-http://127.0.0.1:8000}                    # base URL of the running app
APP_DIR=${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}   # project root
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}                          # match config.php
DB_NAME=${DB_NAME:-task_tracker}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}                              # empty on XAMPP by default
MYSQL_BIN=${MYSQL_BIN:-mysql}                     # path to the mysql client
MYSQL_EXTRA=${MYSQL_EXTRA:-}                      # extra flags, e.g. --defaults-file=/tmp/my.cnf
                                                  # (space-free values only; leave empty for XAMPP)
# ---------------------------------------------------------------------------

if [ "${I_WIPE_THE_DATABASE:-}" != "yes" ]; then
    echo "Refusing to run: this script drops and re-imports the '$DB_NAME' tables."
    echo "Re-run with I_WIPE_THE_DATABASE=yes once you are sure it is a test database."
    exit 2
fi

PASS=0; FAIL=0; O="out.html"
JAR_DIR=$(mktemp -d)
trap 'rm -rf "$JAR_DIR"' EXIT

# Split the extra flags into an array so "$MYSQL_BIN" can keep spaces in its path.
read -r -a EXTRA_FLAGS <<< "$MYSQL_EXTRA"

db() { MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" "${EXTRA_FLAGS[@]}" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$@"; }
q()  { MYSQL_PWD="$DB_PASS" "$MYSQL_BIN" "${EXTRA_FLAGS[@]}" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -N -B -e "USE $DB_NAME; $1" 2>/dev/null | tr -d '\r'; }

check() { # check <name> <expected> <actual>
    if [ "$2" = "$3" ]; then printf 'PASS  %-52s [%s]\n' "$1" "$3"; PASS=$((PASS+1));
    else printf 'FAIL  %-52s expected=[%s] got=[%s]\n' "$1" "$2" "$3"; FAIL=$((FAIL+1)); fi
}
contains() { if grep -qF "$2" "$JAR_DIR/$O"; then printf 'PASS  %-52s [found]\n' "$1"; PASS=$((PASS+1));
             else printf 'FAIL  %-52s [missing: %s]\n' "$1" "$2"; FAIL=$((FAIL+1)); fi; }
absent()   { if grep -qF "$2" "$JAR_DIR/$O"; then printf 'FAIL  %-52s [unexpected: %s]\n' "$1" "$2"; FAIL=$((FAIL+1));
             else printf 'PASS  %-52s [absent]\n' "$1"; PASS=$((PASS+1)); fi; }

fetch() { curl -s -o "$JAR_DIR/$O" -w '%{http_code}' -b "$1" -c "$1" "$2"; }
post()  { curl -s -o "$JAR_DIR/$O" -w '%{http_code}' -b "$1" -c "$1" -d "${@:3}" "$2"; }
token() { grep -oE 'name="_csrf" value="[a-f0-9]{64}"' "$JAR_DIR/$O" | head -1 | grep -oE '[a-f0-9]{64}'; }
login() { local jar=$1 email=$2 pass=$3 tk
    fetch "$jar" "$B/login.php" >/dev/null; tk=$(token)
    post "$jar" "$B/login.php" "email=$email&password=$pass&_csrf=$tk" >/dev/null; }

ALICE="$JAR_DIR/alice.txt"; BOB="$JAR_DIR/bob.txt"; ADMIN="$JAR_DIR/admin.txt"; ANON="$JAR_DIR/anon.txt"

echo "##### 0. fresh database #####"
db < "$APP_DIR/sql/database.sql" 2>/dev/null
ALICE_ID=$(q "SELECT id FROM users WHERE email='alice@example.com'")
BOB_ID=$(q "SELECT id FROM users WHERE email='bob@example.com'")
BOB_TASK=$(q "SELECT id FROM tasks WHERE user_id=$BOB_ID ORDER BY created_at DESC LIMIT 1")
ALICE_TASK=$(q "SELECT id FROM tasks WHERE user_id=$ALICE_ID ORDER BY created_at DESC LIMIT 1")
check "sample users imported" "3" "$(q 'SELECT COUNT(*) FROM users')"
check "sample tasks imported" "14" "$(q 'SELECT COUNT(*) FROM tasks')"

echo "##### 1. anonymous access #####"
ST=$(fetch "$ANON" "$B/index.php"); check "GET /index.php -> 302" "302" "$ST"
LOC=$(curl -s -o /dev/null -w '%{redirect_url}' -b "$ANON" -c "$ANON" "$B/index.php")
case "$LOC" in *login.php*) R=yes;; *) R=no;; esac; check "  ... redirect goes to login.php" "yes" "$R"
for p in tasks/index.php tasks/create.php tasks/edit.php tasks/delete.php tasks/status.php; do
    ST=$(fetch "$ANON" "$B/$p"); check "GET /$p blocked while logged out" "302" "$ST"
done
ST=$(post "$ANON" "$B/tasks/delete.php" "id=1"); check "POST /tasks/delete.php blocked while logged out" "302" "$ST"

echo "##### 2. session cookie #####"
HDR=$(curl -sI "$B/login.php" | tr -d '\r')
echo "$HDR" | grep -i 'Set-Cookie' | grep -q 'HttpOnly' && R=yes || R=no; check "session cookie is HttpOnly" "yes" "$R"
echo "$HDR" | grep -i 'Set-Cookie' | grep -q 'SameSite=Lax' && R=yes || R=no; check "session cookie SameSite=Lax" "yes" "$R"
echo "$HDR" | grep -i 'Set-Cookie' | grep -q 'TTSESSID' && R=yes || R=no; check "session cookie named TTSESSID" "yes" "$R"

echo "##### 3. login #####"
fetch "$JAR_DIR/w.txt" "$B/login.php" >/dev/null; TK=$(token)
ST=$(post "$JAR_DIR/w.txt" "$B/login.php" "email=alice@example.com&password=WRONGPASS&_csrf=$TK")
check "wrong password -> 200 (form again)" "200" "$ST"
contains "wrong password -> generic message" "Invalid email or password."
fetch "$JAR_DIR/n.txt" "$B/login.php" >/dev/null; TK=$(token)
post "$JAR_DIR/n.txt" "$B/login.php" "email=nobody-here@example.com&password=whatever&_csrf=$TK" >/dev/null
contains "unknown email -> same generic message" "Invalid email or password."
absent "no 'no such user' style leak" "not registered"
ST=$(fetch "$JAR_DIR/n.txt" "$B/tasks/index.php"); check "failed login did not authenticate" "302" "$ST"

fetch "$JAR_DIR/pre.txt" "$B/login.php" >/dev/null; SID_BEFORE=$(grep TTSESSID "$JAR_DIR/pre.txt" | awk '{print $7}')
login "$ALICE" alice@example.com 'Alice@1234'
ST=$(fetch "$ALICE" "$B/tasks/index.php"); check "alice reaches her list" "200" "$ST"
SID_AFTER=$(grep TTSESSID "$ALICE" | awk '{print $7}')
[ "$SID_BEFORE" != "$SID_AFTER" ] && R=changed || R=same; check "session id regenerated on login" "changed" "$R"
contains "list shows alice's own task" "Write project proposal"
absent "list hides bob's task" "Replace the logo"
absent "list hides admin's task" "Rotate the database password"
check "password is stored as bcrypt" '$2y$10$' "$(q "SELECT LEFT(password_hash,7) FROM users WHERE email='alice@example.com'")"
check "plaintext password is not stored" "0" "$(q "SELECT COUNT(*) FROM users WHERE password_hash LIKE '%Alice@1234%'")"

echo "##### 4. CSRF #####"
ST=$(post "$ALICE" "$B/tasks/delete.php" "id=$ALICE_TASK"); check "POST without token -> 400" "400" "$ST"
ST=$(post "$ALICE" "$B/tasks/delete.php" "id=$ALICE_TASK&_csrf=deadbeefdeadbeef"); check "POST with wrong token -> 400" "400" "$ST"
contains "CSRF failure page is friendly" "reload the page"
ST=$(curl -s -o /dev/null -w '%{http_code}' -b "$ALICE" -c "$ALICE" "$B/tasks/delete.php?id=$ALICE_TASK")
check "GET delete -> 405" "405" "$ST"
ST=$(curl -s -o /dev/null -w '%{http_code}' -b "$ALICE" -c "$ALICE" "$B/tasks/status.php?id=$ALICE_TASK&status=Completed")
check "GET status -> 405" "405" "$ST"
ST=$(curl -s -o /dev/null -w '%{http_code}' -b "$ALICE" -c "$ALICE" -X PUT -d "title=FromPUT" "$B/tasks/create.php")
check "PUT create -> 405" "405" "$ST"
check "PUT created nothing" "0" "$(q "SELECT COUNT(*) FROM tasks WHERE title='FromPUT'")"

echo "##### 5. cross-user authorization #####"
ST=$(fetch "$ALICE" "$B/tasks/edit.php?id=$BOB_TASK"); check "alice GET edit bob's task -> 403" "403" "$ST"
contains "403 page explains why" "Only the owner of a task can change"
ST=$(fetch "$ALICE" "$B/tasks/edit.php?id=999999"); check "alice GET unknown id -> 404" "404" "$ST"
contains "404 page is friendly" "does not exist"
BOB_TITLE_BEFORE=$(q "SELECT title FROM tasks WHERE id=$BOB_TASK")
ALICE_TITLE_BEFORE=$(q "SELECT title FROM tasks WHERE id=$ALICE_TASK")
fetch "$ALICE" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/tasks/edit.php?id=$BOB_TASK" "id=$BOB_TASK&title=HACKED&description=x&status=Pending&priority=Low&due_date=&_csrf=$TK")
check "alice POST edit bob's task (id in both places) -> 403" "403" "$ST"
fetch "$ALICE" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/tasks/edit.php?id=$ALICE_TASK" "id=$BOB_TASK&title=HACKED&description=x&status=Pending&priority=Low&due_date=&_csrf=$TK")
check "URL id and body id disagree -> 400" "400" "$ST"
check "bob's title untouched" "$BOB_TITLE_BEFORE" "$(q "SELECT title FROM tasks WHERE id=$BOB_TASK")"
check "alice's title untouched too" "$ALICE_TITLE_BEFORE" "$(q "SELECT title FROM tasks WHERE id=$ALICE_TASK")"
check "no HACKED row anywhere" "0" "$(q "SELECT COUNT(*) FROM tasks WHERE title='HACKED'")"
fetch "$ALICE" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/tasks/status.php" "id=$BOB_TASK&status=Completed&_csrf=$TK")
check "alice POST status on bob's task -> 403" "403" "$ST"
check "bob's status untouched" "In Progress" "$(q "SELECT status FROM tasks WHERE id=$BOB_TASK")"
fetch "$ALICE" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/tasks/delete.php" "id=$BOB_TASK&_csrf=$TK")
check "alice POST delete bob's task -> 403" "403" "$ST"
check "bob's task still there" "1" "$(q "SELECT COUNT(*) FROM tasks WHERE id=$BOB_TASK")"

echo "##### 6. registration (anonymous) #####"
REG="$JAR_DIR/reg.txt"
ST=$(fetch "$REG" "$B/register.php"); check "register page reachable" "200" "$ST"
TK=$(token)
ST=$(post "$REG" "$B/register.php" "name=Carol Example&email=carol@example.com&password=Carol%401234&password_confirm=Carol%401234&role=admin&_csrf=$TK")
check "register -> 302 (auto sign-in)" "302" "$ST"
check "role=admin in POST ignored, stored as 'user'" "user" "$(q "SELECT role FROM users WHERE email='carol@example.com'")"
check "carol's password hashed" '$2y$10$' "$(q "SELECT LEFT(password_hash,7) FROM users WHERE email='carol@example.com'")"
ST=$(fetch "$REG" "$B/tasks/index.php"); check "carol is signed in after registering" "200" "$ST"
check "carol owns 0 tasks at birth" "0" "$(q "SELECT COUNT(*) FROM tasks WHERE user_id=(SELECT id FROM users WHERE email='carol@example.com')")"
fetch "$ANON" "$B/register.php" >/dev/null; TK=$(token)
post "$ANON" "$B/register.php" "name=Dup Person&email=alice@example.com&password=Duppass123&password_confirm=Duppass123&_csrf=$TK" >/dev/null
contains "duplicate email refused" "already registered"
check "duplicate did not add a user" "4" "$(q 'SELECT COUNT(*) FROM users')"
fetch "$ANON" "$B/register.php" >/dev/null; TK=$(token)
post "$ANON" "$B/register.php" "name=Shorty&email=short@example.com&password=123&password_confirm=123&_csrf=$TK" >/dev/null
contains "password under 8 chars refused" "at least 8 characters"
fetch "$ANON" "$B/register.php" >/dev/null; TK=$(token)
post "$ANON" "$B/register.php" "name=Miss Match&email=mm@example.com&password=Goodpass1&password_confirm=Different2&_csrf=$TK" >/dev/null
contains "password mismatch refused" "do not match"
fetch "$ANON" "$B/register.php" >/dev/null; TK=$(token)
post "$ANON" "$B/register.php" "name=&email=bad-email&password=Goodpass1&password_confirm=Goodpass1&_csrf=$TK" >/dev/null
contains "blank name refused" "between 1 and 100 characters"
contains "invalid email refused" "valid email address"
check "no bad registrations slipped through" "4" "$(q 'SELECT COUNT(*) FROM users')"

echo "##### 7. admin rules #####"
login "$ADMIN" admin@example.com 'Admin@1234'   # registering above auto-signed-in carol
ST=$(fetch "$ADMIN" "$B/tasks/index.php"); check "admin list reachable" "200" "$ST"
contains "admin list counts every user's tasks" "of 14 tasks"
contains "admin list has an Owner column" ">Owner<"
contains "admin list shows an owner's name" "Alice Example"
contains "admin list shows bob's name too" "Bob Example"
ST=$(fetch "$ADMIN" "$B/tasks/index.php?q=proposal"); check "admin search reaches alice's row" "200" "$ST"
contains "admin can find alice's task by search" "Write project proposal"
ST=$(fetch "$ALICE" "$B/tasks/index.php?q=proposal"); contains "alice still sees her own row" "Write project proposal"
ST=$(fetch "$ADMIN" "$B/tasks/edit.php?id=$BOB_TASK"); check "admin cannot edit another user's task -> 403" "403" "$ST"
fetch "$ADMIN" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ADMIN" "$B/tasks/status.php" "id=$BOB_TASK&status=Completed&_csrf=$TK")
check "admin cannot change another user's status -> 403" "403" "$ST"
fetch "$ADMIN" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ADMIN" "$B/tasks/delete.php" "id=$BOB_TASK&_csrf=$TK")
check "admin may delete any task -> 302" "302" "$ST"
check "that task is gone" "0" "$(q "SELECT COUNT(*) FROM tasks WHERE id=$BOB_TASK")"
ST=$(fetch "$ALICE" "$B/tasks/index.php"); check "normal user list reachable" "200" "$ST"
absent "normal user list has no Owner column" ">Owner<"

echo "##### 8. search / filter / pagination #####"
# Filler rows guarantee the search spans two pages; the expected slice labels are
# then derived from the database instead of being hard-coded.
ADMIN_ID=$(q "SELECT id FROM users WHERE email='admin@example.com'")
q "INSERT INTO tasks (user_id,title,description,status,priority,created_at) VALUES
   ($ADMIN_ID,'Pagination filler alpha','x','Pending','Low',NOW()),
   ($ADMIN_ID,'Pagination filler beta','x','Pending','Low',NOW()),
   ($ADMIN_ID,'Pagination filler gamma','x','Pending','Low',NOW()),
   ($ADMIN_ID,'Pagination filler delta','x','Pending','Low',NOW())"
TOTAL=$(q 'SELECT COUNT(*) FROM tasks')
QA=$(q "SELECT COUNT(*) FROM tasks WHERE title LIKE '%a%'")
ST=$(fetch "$ADMIN" "$B/tasks/index.php?q=report&status=Pending&priority=High"); check "combined search+status+priority -> 200" "200" "$ST"
contains "all three conditions applied" "Approve the monthly report"
contains "search box keeps the term" 'value="report"'
contains "status select keeps the value" 'value="Pending" selected'
absent "the Low-priority match is excluded" "Refactor report export"
ST=$(fetch "$ADMIN" "$B/tasks/index.php?q=a"); check "search spanning two pages -> 200" "200" "$ST"
contains "first page slice is correct" "Showing 1-10 of $QA tasks"
contains "page links preserve the search term" 'page=2&amp;q=a'
contains "filter links preserve the search term" 'q=a&amp;status=Completed'
ST=$(fetch "$ADMIN" "$B/tasks/index.php?q=a&page=2"); check "page 2 of a search -> 200" "200" "$ST"
contains "second page slice is correct" "Showing 11-$QA of $QA tasks"
contains "second page keeps the search term" 'value="a"'
ST=$(fetch "$ADMIN" "$B/tasks/index.php?q=%25"); check "literal % search -> 200" "200" "$ST"
contains "% is not treated as a wildcard" "No tasks match"
ST=$(fetch "$ADMIN" "$B/tasks/index.php?page=999"); check "page=999 clamped -> 200" "200" "$ST"
contains "clamped page lands on the real last page" "Showing 11-$TOTAL of $TOTAL tasks"
ST=$(fetch "$ADMIN" "$B/tasks/index.php?status=bogus"); check "invalid status filter ignored -> 200" "200" "$ST"
absent "invalid filter does not narrow the list" "No tasks match"
ST=$(fetch "$ADMIN" "$B/tasks/index.php?q=%27+OR+1%3D1--+)"); check "SQLi probe -> 200 no error" "200" "$ST"
contains "SQLi probe matched nothing" "No tasks match"
absent "no SQL error text shown" "SQLSTATE"
ST=$(fetch "$ADMIN" "$B/tasks/index.php?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E"); check "XSS probe in query -> 200" "200" "$ST"
absent "XSS probe not reflected raw" "<script>alert(1)</script>"

echo "##### 9. validation #####"
vpost() { fetch "$ALICE" "$B/tasks/create.php" >/dev/null; post "$ALICE" "$B/tasks/create.php" "${1}&_csrf=$(token)" >/dev/null; }
vpost "title=&description=&status=Pending&priority=Medium&due_date="
contains "empty title refused" "Please give the task a title"
LONG=$(printf 'a%.0s' $(seq 1 160)); vpost "title=$LONG&description=&status=Pending&priority=Medium&due_date="
contains "160-char title refused" "Title must be 150 characters or fewer (currently 160)"
contains "typed value kept on failure" "value=\"$LONG\""
LONGD=$(printf 'b%.0s' $(seq 1 2100)); vpost "title=ok&description=$LONGD&status=Pending&priority=Medium&due_date="
contains "2100-char description refused" "Description must be 2000 characters or fewer (currently 2100)"
vpost "title=ok&description=&status=Shipped&priority=Medium&due_date="
contains "invalid status refused" "Status must be Pending, In Progress or Completed"
vpost "title=ok&description=&status=Pending&priority=Ultra&due_date="
contains "invalid priority refused" "Priority must be Low, Medium or High"
vpost "title=ok&description=&status=Pending&priority=Low&due_date=2026-02-30"
contains "impossible due date refused" "Due date must be a real date"
check "nothing invalid was written" "5" "$(q "SELECT COUNT(*) FROM tasks WHERE user_id=$ALICE_ID")"

echo "##### 10. happy path CRUD #####"
fetch "$ALICE" "$B/tasks/create.php" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/tasks/create.php" "title=Smoke%20task&description=hello&status=In+Progress&priority=High&due_date=2030-01-15&_csrf=$TK")
check "valid add -> 302" "302" "$ST"
NEW=$(q "SELECT id FROM tasks WHERE title='Smoke task'")
check "added row has chosen status" "In Progress" "$(q "SELECT status FROM tasks WHERE id=$NEW")"
check "added row owned by alice" "$ALICE_ID" "$(q "SELECT user_id FROM tasks WHERE id=$NEW")"
fetch "$ALICE" "$B/tasks/index.php" >/dev/null
contains "flash shown after add" "Task added"
fetch "$ALICE" "$B/tasks/edit.php?id=$NEW" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/tasks/edit.php?id=$NEW" "id=$NEW&title=Smoke%20task%20renamed&description=changed&status=Completed&priority=Low&due_date=&_csrf=$TK")
check "edit own task -> 302" "302" "$ST"
check "edit persisted" "Smoke task renamed" "$(q "SELECT title FROM tasks WHERE id=$NEW")"
check "cleared due date stored as NULL" "NULL" "$(q "SELECT IFNULL(due_date,'NULL') FROM tasks WHERE id=$NEW")"
fetch "$ALICE" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/tasks/status.php" "id=$NEW&status=Pending&_csrf=$TK")
check "status change -> 302" "302" "$ST"
check "status persisted" "Pending" "$(q "SELECT status FROM tasks WHERE id=$NEW")"
fetch "$ALICE" "$B/tasks/index.php" >/dev/null; TK=$(token)
post "$ALICE" "$B/tasks/status.php" "id=$NEW&status=Pending&_csrf=$TK" >/dev/null
fetch "$ALICE" "$B/tasks/index.php" >/dev/null
contains "re-saving the same status still succeeds" 'is now &quot;Pending&quot;'
fetch "$ALICE" "$B/tasks/create.php" >/dev/null; TK=$(token)
post "$ALICE" "$B/tasks/create.php" "title=%3Cscript%3Ealert(1)%3C%2Fscript%3E&description=%22q%22%20%26%20amp&status=Pending&priority=Medium&due_date=&_csrf=$TK" >/dev/null
fetch "$ALICE" "$B/tasks/index.php" >/dev/null
contains "script tag escaped in the list" "&lt;script&gt;alert(1)&lt;/script&gt;"
absent "no executable script emitted" "<script>alert(1)</script>"
fetch "$ALICE" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/tasks/delete.php" "id=$NEW&_csrf=$TK")
check "delete own task -> 302" "302" "$ST"
check "row removed" "0" "$(q "SELECT COUNT(*) FROM tasks WHERE id=$NEW")"

echo "##### 11. logout #####"
ST=$(curl -s -o /dev/null -w '%{http_code}' -b "$ALICE" "$B/logout.php")
check "GET logout refused -> 405" "405" "$ST"
ST=$(fetch "$ALICE" "$B/tasks/index.php"); check "GET logout left alice signed in" "200" "$ST"
fetch "$ALICE" "$B/tasks/index.php" >/dev/null; TK=$(token)
ST=$(post "$ALICE" "$B/logout.php" "_csrf=$TK")
check "logout POST -> 302" "302" "$ST"
ST=$(fetch "$ALICE" "$B/tasks/index.php"); check "after logout the list is protected again" "302" "$ST"

echo "##### 12. error hygiene #####"
login "$ALICE" alice@example.com 'Alice@1234'
fetch "$ALICE" "$B/tasks/edit.php?id=999999" >/dev/null
absent "404 page has no SQL error" "SQLSTATE"
absent "404 page has no exception class" "PDOException"
absent "404 page has no stack trace" "Stack trace"
absent "404 page has no file paths" "/src/"
absent "404 page has no db name" "$DB_NAME"
absent "404 page has no PHP warning" "Warning:"
ST=$(fetch "$ALICE" "$B/tasks/edit.php?id=abc"); check "non-numeric id -> 404 not a crash" "404" "$ST"

echo "##### 13. FK cascade #####"
BEFORE=$(q "SELECT COUNT(*) FROM tasks WHERE user_id=$ALICE_ID")
q "DELETE FROM users WHERE id=$ALICE_ID"
check "deleting a user deletes their tasks" "0" "$(q "SELECT COUNT(*) FROM tasks WHERE user_id=$ALICE_ID")"
[ "${BEFORE:-0}" -gt 0 ] && check "the user had tasks first" "yes" "yes" || check "the user had tasks first" "yes" "no"

echo "##### 14. bootstrap failure paths (config.php is backed up and restored) #####"
CFG="$APP_DIR/config.php"
cp "$CFG" "$JAR_DIR/config.bak"

mv "$CFG" "$JAR_DIR/config.off"
ST=$(curl -s -o "$JAR_DIR/$O" -w '%{http_code}' "$B/login.php")
check "missing config.php -> 503" "503" "$ST"
contains "  tells the operator what to do" "Copy config.sample.php"
absent "  leaks no filesystem path" "$(basename "$(dirname "$APP_DIR")")"
cp "$JAR_DIR/config.off" "$CFG"

printf '<?php\nreturn [ "db" => [ "host" => "127.0.0.1" \n' > "$CFG"
ST=$(curl -s -o "$JAR_DIR/$O" -w '%{http_code}' "$B/login.php")
check "config.php with a syntax error -> 500" "500" "$ST"
absent "  no raw parse error shown" "Parse error"
absent "  no 'on line' detail shown" "on line"
cp "$JAR_DIR/config.bak" "$CFG"
if cmp -s "$JAR_DIR/config.bak" "$CFG"; then R=yes; else R=no; fi
check "  config restored afterwards" "yes" "$R"
ST=$(fetch "$JAR_DIR/cfg.txt" "$B/login.php"); check "app healthy again after restore" "200" "$ST"

# point config.php at a port where nothing listens, then prove the failure is
# reported as a generic page with no connection details
sed -E "s/('port'[[:space:]]*=>[[:space:]]*)[0-9]+/\11/" "$JAR_DIR/config.bak" > "$CFG"
ST=$(curl -s -o "$JAR_DIR/$O" -w '%{http_code}' -b "$ADMIN" "$B/tasks/index.php")
check "database unreachable -> 500, not a raw error" "500" "$ST"
contains "  friendly database message" "cannot reach the database"
absent "  no SQLSTATE shown" "SQLSTATE"
absent "  no DSN shown" "dbname="
absent "  no user name shown" "root@"
cp "$JAR_DIR/config.bak" "$CFG"

echo
echo "================= $PASS passed, $FAIL failed ================="
[ "$FAIL" -eq 0 ]
