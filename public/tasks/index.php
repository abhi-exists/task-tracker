<?php
/** Task list. */

require __DIR__ . '/../../src/config.php';

$page_title = 'Tasks';
$rows       = tasks()->all();

require TT_ROOT . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Tasks</h1>
    <a class="btn btn-primary" href="<?= e(tt_url('tasks/create.php')) ?>">Add task</a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th scope="col" style="width:4rem">#</th>
                <th scope="col">Title</th>
                <th scope="col">Description</th>
                <th scope="col" style="width:9rem">Created</th>
                <th scope="col" style="width:10rem" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                        No tasks yet. <a href="<?= e(tt_url('tasks/create.php')) ?>">Add your first one</a>.
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-muted"><?= (int)$row['id'] ?></td>
                    <td class="fw-semibold"><?= e($row['title']) ?></td>
                    <td class="text-muted"><?= nl2br(e($row['description'])) ?></td>
                    <td class="text-muted small"><?= e(date('d M Y', strtotime((string)$row['created_at']))) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(tt_url('tasks/edit.php?id=' . (int)$row['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(tt_url('tasks/delete.php')) ?>" class="d-inline"
                              data-confirm="Delete &quot;<?= e($row['title']) ?>&quot;? This cannot be undone.">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require TT_ROOT . '/templates/footer.php'; ?>
