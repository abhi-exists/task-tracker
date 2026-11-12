<?php
/** Edit a task. */

require __DIR__ . '/../../src/config.php';

$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$task = tasks()->find($id);

if ($task === null) {
    tt_fatal(sprintf('Task %d not found for edit', $id), 'That task does not exist.', 404);
}

$errors     = [];
$formAction = tt_url('tasks/edit.php?id=' . $id);
$page_title = 'Edit task';

if (is_post()) {
    $title       = post_str('title');
    $description = post_str('description');

    if ($title === '') {
        $errors['title'] = 'Title is required.';
        $task['title']       = $title;
        $task['description'] = $description;
    } else {
        tasks()->update($id, $title, $description);
        flash('success', 'Task updated.');
        tt_redirect(tt_url('tasks/index.php'));
    }
}

require TT_ROOT . '/templates/header.php';
require TT_ROOT . '/templates/task_form.php';
require TT_ROOT . '/templates/footer.php';
