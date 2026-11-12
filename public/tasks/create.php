<?php
/** Add a task. */

require __DIR__ . '/../../src/config.php';

$task       = null;
$errors     = [];
$formAction = tt_url('tasks/create.php');
$page_title = 'Add task';

if (is_post()) {
    $task   = read_task_fields();
    $errors = validate_task($task['title'], $task['description'], $task['status'], $task['priority'], $task['due_date']);

    if ($errors === []) {
        tasks()->create(
            $task['title'],
            $task['description'],
            $task['status'] !== '' ? $task['status'] : 'Pending',
            $task['priority'] !== '' ? $task['priority'] : 'Medium',
            $task['due_date'] !== '' ? $task['due_date'] : null
        );
        flash('success', 'Task added.');
        tt_redirect(list_url(next_query()));
    }
    // On failure the form is re-rendered below with the submitted values kept.
}

require TT_ROOT . '/templates/header.php';
require TT_ROOT . '/templates/task_form.php';
require TT_ROOT . '/templates/footer.php';
