<?php
/**
 * CSRF protection. One random token per session, embedded in every form that
 * changes state and compared with hash_equals() on POST.
 *
 * The SameSite=Lax session cookie already blocks cross-site POSTs; this token
 * is the second layer, and it also catches a request that somehow arrives
 * without the expected origin.
 */

declare(strict_types=1);

/** The session token, created on first use. */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

/** Hidden input for forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">' . "\n";
}

/**
 * Stop the request unless the submitted token matches the session token.
 * Called at the top of every POST handler, before anything is written.
 */
function csrf_require(): void
{
    $sent  = $_POST['_csrf'] ?? '';
    $known = $_SESSION['_csrf'] ?? '';

    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        tt_fatal(
            sprintf(
                'CSRF check failed for %s from %s',
                $_SERVER['SCRIPT_NAME'] ?? '?',
                $_SERVER['REMOTE_ADDR'] ?? '?'
            ),
            'Your form had expired or was not loaded from this site. Please reload the page and try again.',
            400
        );
    }
}

/**
 * Guard a state-changing request: it must be a POST and must carry a valid
 * token. GET requests to these pages are rejected so a crawler, a prefetch or
 * an <img src> can never mutate data.
 */
function csrf_require_post(): void
{
    if (!is_post()) {
        header('Allow: POST');
        tt_fatal(
            sprintf('Non-POST request to %s from %s', $_SERVER['SCRIPT_NAME'] ?? '?', $_SERVER['REMOTE_ADDR'] ?? '?'),
            'This action requires a form submission (POST).',
            405
        );
    }
    csrf_require();
}
