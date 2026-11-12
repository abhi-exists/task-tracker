<?php
/**
 * Small shared helpers: escaping, URLs, redirects and flash messages.
 * Everything that outputs text goes through e().
 */

declare(strict_types=1);

/** HTML-escape. The single output path for every dynamic value in templates. */
function e(mixed $value): string
{
    if ($value === null || $value === false) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Path of the app root relative to the web server, e.g. "" or "/task/public". */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    // Pages under /public/tasks must resolve against /public, not /public/tasks.
    if (substr($dir, -6) === '/tasks') {
        $dir = substr($dir, 0, -6);
    }
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $dir = '';
    }

    return $base = rtrim($dir, '/');
}

/** Absolute URL (from the web root) for an app-relative path. */
function tt_url(string $path): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Redirect and stop. */
function tt_redirect(string $path, int $status = 302): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Location: ' . $path);
    }
    exit;
}

// ------------------------------------------------------------ flash --------

/** Queue a one-message alert for the next request. */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Drain queued messages (called once by the header template). */
function flash_pull(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

// ------------------------------------------------------------ request ------

/** Trimmed string from $_POST. */
function post_str(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Integer from $_POST; anything non-numeric becomes 0 (and then fails lookup). */
function post_int(string $key): int
{
    $value = $_POST[$key] ?? '';
    return is_numeric($value) ? (int)$value : 0;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
