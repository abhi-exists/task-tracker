<?php
/** Edit a task. */

require __DIR__ . '/../../src/config.php';

$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$task = tasks()->find($id);

if ($task === null) {
    tt_fatal(sprintf('Task %d not found for edit', $id), 'That task does not exist.', 404);
}

$errors     = [];
$next       = next_query();
$formAction = tt_url('tasks/edit.php?id=' . $id);
$page_title = 'Edit task';

if (is_post()) {
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
            $submitted['due_date'] !== '' ? $submitted['due_date'] : null
        );
        flash('success', 'Task updated.');
        tt_redirect(list_url($next));
    }

    // Keep what the user typed so the form can be shown again with the errors.
    $task = array_merge($task, $submitted);
}

require TT_ROOT . '/templates/header.php';
require TT_ROOT . '/templates/task_form.php';
require TT_ROOT . '/templates/footer.php';
