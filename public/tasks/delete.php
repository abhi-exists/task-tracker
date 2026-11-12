<?php
/**
 * Delete a task. POST only, CSRF-checked, and the id is verified against the
 * owner (or any task for an admin) inside the DELETE statement itself.
 */

require __DIR__ . '/../../src/config.php';

$actor = auth_require_login();

csrf_require_post();

$id = post_int('id');

// 404 when the id is unknown, 403 when it belongs to somebody else and the
// actor is not an admin. Nothing is deleted before that check passes.
require_task($id, $actor, false);

if (tasks()->delete($id, $actor)) {
    flash('success', sprintf('Task #%d deleted.', $id));
} else {
    flash('warning', 'That task could not be deleted.');
}

tt_redirect(list_url(next_query()));
