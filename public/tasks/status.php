<?php
/**
 * Change a single task's status from the list page. POST + CSRF + owner-only:
 * the UPDATE carries "AND user_id = :uid", so a forged id in the body changes
 * nothing and answers 403.
 */

require __DIR__ . '/../../src/config.php';

$actor = auth_require_login();

csrf_require_post();

$id     = post_int('id');
$status = post_str('status');

if (!in_array($status, TaskRepository::STATUSES, true)) {
    flash('danger', 'Please choose a valid status.');
    tt_redirect(list_url(next_query()));
}

// Refuses ids that are not this user's task before any write happens.
require_task($id, $actor, true);

tasks()->updateStatus($id, $status, $actor);
flash('success', sprintf('Task #%d is now "%s".', $id, $status));

tt_redirect(list_url(next_query()));
