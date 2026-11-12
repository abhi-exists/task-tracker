<?php
/**
 * Shared page header. Expects $page_title (string).
 * When a session user exists the navbar shows who is signed in and a POST
 * logout form; anonymous visitors (login/register pages) get the plain header.
 *
 * @var string $page_title
 */
$navUser = auth_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title ?? 'Task Tracker') ?> &middot; Task Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(tt_url('assets/css/style.css')) ?>">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= e(tt_url($navUser ? 'tasks/index.php' : 'login.php')) ?>">Task Tracker</a>

        <?php if ($navUser !== null): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                <a class="btn btn-outline-light btn-sm" href="<?= e(tt_url('tasks/create.php')) ?>">+ New task</a>
                <span class="text-white-50 small">
                    <?= e($navUser['name']) ?>
                    <span class="badge text-bg-<?= $navUser['role'] === 'admin' ? 'warning text-dark' : 'light text-dark' ?>">
                        <?= e($navUser['role']) ?>
                    </span>
                </span>
                <form method="post" action="<?= e(tt_url('logout.php')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light btn-sm">Sign out</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</nav>
<main class="container pb-5">
<?php foreach (flash_pull() as $msg): ?>
    <div class="alert alert-<?= e($msg['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($msg['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
