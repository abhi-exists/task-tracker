<?php
/**
 * Application bootstrap: configuration, error handling and session hardening.
 * Every entry point under /public starts by requiring this file.
 */

declare(strict_types=1);

define('TT_ROOT', dirname(__DIR__));

/**
 * Render the friendly error page and stop. The detailed message is only ever
 * written to the server log - never sent to the browser.
 */
function tt_fatal(string $logMessage, string $friendly = 'Something went wrong on our side. Please try again.', int $status = 500): void
{
    error_log('[task-tracker] ' . $logMessage);

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $fatal_message = $friendly;
    $fatal_status  = $status;
    require TT_ROOT . '/templates/error.php';
    exit;
}

// ---------------------------------------------------------------- config ----
$configFile = TT_ROOT . '/config.php';
if (!is_readable($configFile)) {
    tt_fatal(
        'config.php is missing',
        'Configuration is missing. Copy config.sample.php to config.php and set your database credentials.',
        503
    );
}

$config = require $configFile;
if (!is_array($config) || empty($config['db']['name'])) {
    tt_fatal('config.php does not return a valid configuration array', 'The application is misconfigured.');
}

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

// ------------------------------------------------------------ error mode ----
// Errors are logged server-side and shown as a generic page. Raw messages can
// leak table names, file paths or DSN details, so they never reach the client.
$debug   = (bool)($config['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    global $debug;
    // Log class + message + location; the browser gets a generic page.
    if ($debug) {
        tt_fatal(
            sprintf('%s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()),
            sprintf('Error: %s', $e->getMessage()),
            500
        );
    }
    tt_fatal(
        sprintf('%s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()),
        'Something went wrong while handling your request. The problem has been logged.',
        500
    );
});

register_shutdown_function(static function (): void {
    $last = error_get_last();
    if ($last === null || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    tt_fatal(sprintf('Fatal: %s in %s:%d', $last['message'], $last['file'], $last['line']));
});

// ---------------------------------------------------------------- session ----
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,                 // expires when the browser closes
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,          // cookie only over HTTPS when served over HTTPS
        'httponly' => true,              // not readable by JavaScript
        'samesite' => 'Lax',             // blocks cross-site POSTs carrying the session
    ]);

    ini_set('session.use_strict_mode', '1');   // reject session ids we did not issue
    ini_set('session.use_only_cookies', '1');
    session_name('TTSESSID');
    session_start();
}

require TT_ROOT . '/src/helpers.php';
require TT_ROOT . '/src/db.php';
require TT_ROOT . '/src/TaskRepository.php';
