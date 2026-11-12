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

/** Trimmed string from $_GET. Array values ( ?q[]=x ) fall back to the default. */
function get_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    if (!is_string($value)) {
        return $default;
    }
    $value = trim($value);
    return $value === '' ? $default : $value;
}

/**
 * Link back to the task list keeping the current search/filter/page arguments.
 * Only scalar values are carried over; anything that becomes empty is dropped
 * so "clear filter" links stay short.
 */
function qs_url(array $overrides = [], string $path = 'tasks/index.php'): string
{
    $query = array_merge($_GET, $overrides);
    foreach (array_keys($query) as $key) {
        $query[$key] = is_array($query[$key]) ? '' : (string)$query[$key];
        if ($query[$key] === '') {
            unset($query[$key]);
        }
    }
    ksort($query);
    $url = tt_url($path);

    return $query === [] ? $url : $url . '?' . http_build_query($query);
}

/** 1-based page number, clamped to 1..$totalPages so ?page=9999 is harmless. */
function page_from_request(int $totalPages): int
{
    $page = (int)get_str('page', '1');
    return min(max(1, $page), max(1, $totalPages));
}

/**
 * Query string to return to after a POST, so a status change or a save keeps the
 * user's search/filter/page. The value is parsed and rebuilt from scratch
 * (dropping id/next), so it can never be a URL, a host or a header injection.
 */
function next_query(): string
{
    $raw = is_post()
        ? (string)($_POST['next'] ?? '')
        : (string)($_GET['next'] ?? $_SERVER['QUERY_STRING'] ?? '');

    $parts = [];
    parse_str($raw, $parts);
    unset($parts['id'], $parts['next']);

    $parts = array_filter(
        array_map(static fn($v) => is_array($v) ? '' : (string)$v, $parts),
        static fn(string $v): bool => $v !== ''
    );
    ksort($parts);

    return $parts === [] ? '' : substr(http_build_query($parts), 0, 500);
}

/** URL of the task list, optionally carrying a query string. */
function list_url(string $query = ''): string
{
    return tt_url('tasks/index.php') . ($query === '' ? '' : '?' . $query);
}

/** Edit link that remembers where the user came from (search/page/filters). */
function edit_url(int $id): string
{
    $query = next_query();

    return tt_url('tasks/edit.php') . '?id=' . $id . ($query === '' ? '' : '&next=' . rawurlencode($query));
}


// ---------------------------------------------------------- validation -----

/** Collect the raw (trimmed) task fields from $_POST. */
function read_task_fields(): array
{
    return [
        'title'       => post_str('title'),
        'description' => post_str('description'),
        'status'      => post_str('status'),
        'priority'    => post_str('priority'),
        'due_date'    => post_str('due_date'),
    ];
}

/**
 * Server-side rules for the task form. $status/$priority are checked against
 * the same whitelists the repository and the ENUM columns use, so a hand-made
 * POST can never write a value the schema would not accept.
 *
 * @return array<string,string> field => friendly message
 */
function validate_task(string $title, string $description, string $status, string $priority, string $dueDate): array
{
    $errors = [];

    if ($title === '') {
        $errors['title'] = 'Please give the task a title.';
    } elseif (mb_strlen($title) > 150) {
        $errors['title'] = 'Title must be 150 characters or fewer (currently ' . mb_strlen($title) . ').';
    }

    if (mb_strlen($description) > 2000) {
        $errors['description'] = 'Description must be 2000 characters or fewer (currently ' . mb_strlen($description) . ').';
    }

    if ($status !== '' && !in_array($status, TaskRepository::STATUSES, true)) {
        $errors['status'] = 'Status must be Pending, In Progress or Completed.';
    }

    if ($priority !== '' && !in_array($priority, TaskRepository::PRIORITIES, true)) {
        $errors['priority'] = 'Priority must be Low, Medium or High.';
    }

    if ($dueDate !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $dueDate);
        if (!$date || $date->format('Y-m-d') !== $dueDate) {
            $errors['due_date'] = 'Due date must be a real date in YYYY-MM-DD format.';
        }
    }

    return $errors;
}

/** Bootstrap contextual class for a status value. */
function status_badge(string $status): string
{
    return match ($status) {
        'Completed'    => 'success',
        'In Progress'  => 'warning',
        default        => 'secondary',
    };
}

function priority_badge(string $priority): string
{
    return match ($priority) {
        'High'   => 'danger',
        'Medium' => 'info',
        default  => 'secondary',
    };
}


function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
