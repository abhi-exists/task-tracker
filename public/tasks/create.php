<?php
/** Add a task to the signed-in user's own list. */

require __DIR__ . '/../../src/config.php';

$actor = auth_require_login();
require_methods(['GET', 'POST']);

$task       = null;
$errors     = [];
$formAction = tt_url('tasks/create.php');
$page_title = 'Add task';

if (is_post()) {
    csrf_require();

    $task   = read_task_fields();
    $errors = validate_task($task['title'], $task['description'], $task['status'], $task['priority'], $task['due_date']);

    if ($errors === []) {
        tasks()->create(
            $task['title'],
            $task['description'],
            $task['status'] !== '' ? $task['status'] : 'Pending',
            $task['priority'] !== '' ? $task['priority'] : 'Medium',
            $task['due_date'] !== '' ? $task['due_date'] : null,
            $actor
        );
        flash('success', 'Task added.');
        tt_redirect(list_url(next_query()));
    }
    // On failure the form is re-rendered below with the submitted values kept.
} else {
    $task = ['status' => 'Pending', 'priority' => 'Medium', 'due_date' => '', 'title' => '', 'description' => ''];
}

require TT_ROOT . '/templates/header.php';
require TT_ROOT . '/templates/task_form.php';
require TT_ROOT . '/templates/footer.php';
