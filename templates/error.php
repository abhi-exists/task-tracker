<?php
/**
 * Last-resort error page. Self-contained on purpose: it is rendered when the
 * rest of the app has failed, so it must not depend on helpers or the database.
 * Shows a generic message only - the detail went to the server log.
 *
 * @var string $fatal_message
 * @var int    $fatal_status
 */
$message = $fatal_message ?? 'Something went wrong on our side. Please try again.';
$status  = $fatal_status ?? 500;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8') ?> - Task Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<div class="container" style="max-width: 640px;">
    <div class="card shadow-sm mt-5">
        <div class="card-body text-center p-5">
            <div class="display-4 text-primary fw-bold mb-3"><?= (int)$status ?></div>
            <h1 class="h4 mb-3">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="text-muted mb-4">If you keep seeing this page, check the web server error log.</p>
            <a class="btn btn-primary" href="./index.php">Back to Task Tracker</a>
        </div>
    </div>
</div>
</body>
</html>
