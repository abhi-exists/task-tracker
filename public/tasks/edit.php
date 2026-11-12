<?php
/**
 * Edit a task. Only the owner may reach this page for a task: the id is checked
 * against the session user in the query itself, and an admin editing somebody
 * else's task is refused the same way (admins may view and delete, not rewrite).
 */

require __DIR__ . '/../../src/config.php';

$actor = auth_require_login();
require_methods(['GET', 'POST']);

/**
 * Exactly one id is authoritative per request: the form field on a POST, the
 * query string on a GET. If a POST arrives with both a URL id and a body id and
 * they disagree, the request is refused instead of quietly validating one task
 * and writing to the other.
 */
$id = is_post() ? post_int('id') : (int)get_str('id');

if (is_post() && isset($_GET['id']) && $_GET['id'] !== '' && (int)$_GET['id'] !== $id) {
    tt_fatal(
        sprintf('edit.php id mismatch: GET %s vs POST %d by actor %d', $_GET['id'], $id, $actor['id']),
        'This form does not match the task it was opened for. Please reload the page.',
        400
    );
}

$task = require_task($id, $actor, true);

$errors     = [];
$formAction = tt_url('tasks/edit.php') . '?id=' . $id;
$page_title = 'Edit task';

if (is_post()) {
    csrf_require();

    $submitted = read_task_fields();
    $errors    = validate_task(
        $submitted['title'],
        $submitted['description'],
        $submitted['status'],
        $submitted['priority'],
        $submitted['due_date']
    );

    if ($errors === []) {
        tasks()->update(
            $id,
            $submitted['title'],
            $submitted['description'],
            $submitted['status'] !== '' ? $submitted['status'] : 'Pending',
            $submitted['priority'] !== '' ? $submitted['priority'] : 'Medium',
            $submitted['due_date'] !== '' ? $submitted['due_date'] : null,
            $actor
        );
        flash('success', 'Task updated.');
        tt_redirect(list_url(next_query()));
    }

    // Keep what the user typed so the form can be shown again with the errors.
    $task = array_merge($task, $submitted);
}

require TT_ROOT . '/templates/header.php';
require TT_ROOT . '/templates/task_form.php';
require TT_ROOT . '/templates/footer.php';
