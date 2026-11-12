<?php
/**
 * Change a single task's status from the list page. POST only, and the status
 * value is checked against the whitelist before it reaches the database.
 */

require __DIR__ . '/../../src/config.php';

if (!is_post()) {
    header('Allow: POST');
    tt_fatal(
        sprintf('Non-POST request to status.php from %s', $_SERVER['REMOTE_ADDR'] ?? '?'),
        'Changing a status requires a POST request.',
        405
    );
}

$id     = post_int('id');
$status = post_str('status');

if (!in_array($status, TaskRepository::STATUSES, true)) {
    flash('danger', 'Please choose a valid status.');
    tt_redirect(list_url(next_query()));
}

if ($id > 0 && tasks()->updateStatus($id, $status)) {
    flash('success', sprintf('Task #%d is now "%s".', $id, $status));
} else {
    flash('warning', 'That task no longer exists.');
}

tt_redirect(list_url(next_query()));
