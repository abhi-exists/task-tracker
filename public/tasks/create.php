<?php
/** Add a task. */

require __DIR__ . '/../../src/config.php';

$task       = null;
$errors     = [];
$formAction = tt_url('tasks/create.php');
$page_title = 'Add task';

if (is_post()) {
    $title       = post_str('title');
    $description = post_str('description');

    if ($title === '') {
        $errors['title'] = 'Title is required.';
        $task = ['title' => $title, 'description' => $description];
    } else {
        tasks()->create($title, $description);
        flash('success', 'Task added.');
        tt_redirect(tt_url('tasks/index.php'));
    }
}

require TT_ROOT . '/templates/header.php';
require TT_ROOT . '/templates/task_form.php';
require TT_ROOT . '/templates/footer.php';
