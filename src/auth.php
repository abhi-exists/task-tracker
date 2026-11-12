<?php
/**
 * Session-based authentication. Passwords are only ever compared with
 * password_verify() against the stored hash - the hash never leaves PHP and
 * failures are always reported with the same generic message so the login form
 * cannot be used to discover which email addresses exist.
 */

declare(strict_types=1);

/**
 * A real bcrypt hash of a throwaway string. When an email is not found we still
 * run password_verify() against this so a wrong email and a wrong password take
 * roughly the same amount of time (no user-enumeration timing oracle).
 */
const AUTH_DUMMY_HASH = '$2y$10$bCOC0e.f6s3oIIeeaKAeL.XmcojET2LnufX0anPq049jSCp9JZN2G';

/** The signed-in user row, or null. */
function auth_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
    if ($id <= 0) {
        return $user = null;
    }

    // Re-read from the database rather than trusting the session copy, so a
    // deleted account or a changed role takes effect on the next request.
    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row === false) {
        auth_logout();
        return $user = null;
    }

    return $user = $row;
}

function auth_id(): int
{
    return (int)(auth_user()['id'] ?? 0);
}

function auth_is_admin(): bool
{
    return (auth_user()['role'] ?? '') === 'admin';
}

/**
 * Check credentials and, on success, start an authenticated session.
 * Returns true/false only - the caller shows one generic error message.
 */
function auth_attempt(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => mb_strtolower(trim($email))]);
    $row = $stmt->fetch();

    if ($row === false) {
        password_verify($password, AUTH_DUMMY_HASH);
        return false;
    }

    if (!password_verify($password, (string)$row['password_hash'])) {
        return false;
    }

    // New session id for the authenticated session; the anonymous one is deleted.
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['id'];

    // The pre-login CSRF token must not survive into the authenticated session.
    unset($_SESSION['_csrf']);

    return true;
}

/**
 * Discard the session data and issue a fresh (empty) session id.
 * regenerate_id(true) deletes the old session file, so the previous id can no
 * longer be replayed, while the flash queue still works for the redirect.
 */
function auth_logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/** Redirect anonymous visitors to the login page, remembering where they wanted to go. */
function auth_require_login(): array
{
    $user = auth_user();
    if ($user !== null) {
        return $user;
    }

    flash('info', 'Please sign in to continue.');
    tt_redirect(tt_url('login.php') . '?next=' . rawurlencode(current_target()));
}

/** Redirect a signed-in non-admin away from admin-only screens. */
function auth_require_admin(): array
{
    $user = auth_require_login();
    if ($user['role'] !== 'admin') {
        tt_fatal(
            sprintf('User %d (role %s) tried an admin-only screen', $user['id'], $user['role']),
            'Your account is not allowed to do that.',
            403
        );
    }
    return $user;
}

/**
 * Create a normal user. The role is never taken from request data - everyone
 * who registers through this form becomes a 'user'; the sample admin is created
 * by sql/database.sql.
 *
 * @return array{0:array<string,string>,1:?int} [errors, new user id]
 */
function auth_register(string $name, string $email, string $password, string $passwordConfirm): array
{
    $errors = [];
    $email  = mb_strtolower(trim($email));

    if ($name === '' || mb_strlen($name) > 100) {
        $errors['name'] = 'Please enter a name between 1 and 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (mb_strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (mb_strlen($password) > 200) {
        // bcrypt only considers the first 72 bytes; refuse longer input rather
        // than silently truncating what the user thinks is their password.
        $errors['password'] = 'Password must be 200 characters or fewer.';
    }
    if ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }
    if ($errors !== []) {
        return [$errors, null];
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password_hash, role, created_at)
             VALUES (:name, :email, :hash, :role, NOW())'
        );
        $stmt->execute([
            ':name'  => $name,
            ':email' => $email,
            ':hash'  => password_hash($password, PASSWORD_DEFAULT),
            ':role'  => 'user',
        ]);

        return [[], (int)db()->lastInsertId()];
    } catch (PDOException $e) {
        // 23000 = duplicate key. The UNIQUE index on email is the real guard
        // against two accounts racing for the same address.
        if ($e->getCode() === '23000') {
            return [['email' => 'That email address is already registered.'], null];
        }
        throw $e;
    }
}

/** Local, scheme-free redirect target (never an absolute URL to another host). */
function current_target(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    return tt_safe_target($uri, tt_url('tasks/index.php'));
}
