<?php
/**
 * Delete a task. POST only: a link or a prefetched GET must never destroy data.
 */

require __DIR__ . '/../../src/config.php';

if (!is_post()) {
    header('Allow: POST');
    tt_fatal(
        sprintf('Non-POST request to delete.php from %s', $_SERVER['REMOTE_ADDR'] ?? '?'),
        'Deleting a task requires a POST request.',
        405
    );
}

$id = post_int('id');

if ($id > 0 && tasks()->delete($id)) {
    flash('success', sprintf('Task #%d deleted.', $id));
} else {
    flash('warning', 'That task no longer exists.');
}

tt_redirect(list_url(next_query()));
