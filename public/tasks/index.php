<?php
/** Task list: summary cards, search, status/priority filters and pagination. */

require __DIR__ . '/../../src/config.php';

$filters = [
    'q'        => get_str('q'),
    'status'   => in_array(get_str('status'), TaskRepository::STATUSES, true) ? get_str('status') : '',
    'priority' => in_array(get_str('priority'), TaskRepository::PRIORITIES, true) ? get_str('priority') : '',
];

$repo  = tasks();
$total = $repo->count($filters);

$pages     = max(1, (int)ceil($total / TaskRepository::PER_PAGE));
$page      = page_from_request($pages);
$rows      = $repo->search($filters, $page);
$counts    = $repo->statusCounts($filters);
$firstSeen = $total === 0 ? 0 : (($page - 1) * TaskRepository::PER_PAGE) + 1;
$lastSeen  = min($total, $page * TaskRepository::PER_PAGE);

$cards = [
    ['label' => 'Total',         'value' => $counts['total'],       'class' => 'primary',   'filter' => ''],
    ['label' => 'Pending',       'value' => $counts['Pending'],     'class' => 'secondary', 'filter' => 'Pending'],
    ['label' => 'In Progress',   'value' => $counts['In Progress'], 'class' => 'warning',   'filter' => 'In Progress'],
    ['label' => 'Completed',     'value' => $counts['Completed'],   'class' => 'success',   'filter' => 'Completed'],
];

$page_title = 'Tasks';
$next       = next_query();   // carried into every POST so filters survive a save

require TT_ROOT . '/templates/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Tasks</h1>
    <a class="btn btn-primary" href="<?= e(tt_url('tasks/create.php')) ?>">Add task</a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as $card): ?>
        <div class="col-6 col-lg-3">
            <a class="card shadow-sm h-100 text-decoration-none tt-card-link border-start border-4 border-<?= e($card['class']) ?>"
               href="<?= e(qs_url($card['filter'] === '' ? ['status' => null, 'page' => null] : ['status' => $card['filter'], 'page' => null])) ?>">
                <div class="card-body">
                    <div class="text-muted small text-uppercase"><?= e($card['label']) ?></div>
                    <div class="fs-2 fw-bold text-<?= e($card['class']) ?>"><?= (int)$card['value'] ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(tt_url('tasks/index.php')) ?>" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label small text-muted mb-1">Search by title</label>
                <input type="search" class="form-control" id="q" name="q" maxlength="150"
                       placeholder="e.g. budget" value="<?= e($filters['q']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label small text-muted mb-1">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (TaskRepository::STATUSES as $status): ?>
                        <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="priority" class="form-label small text-muted mb-1">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All priorities</option>
                    <?php foreach (TaskRepository::PRIORITIES as $priority): ?>
                        <option value="<?= e($priority) ?>" <?= $filters['priority'] === $priority ? 'selected' : '' ?>>
                            <?= e($priority) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
        <?php if ($filters !== ['q' => '', 'status' => '', 'priority' => '']): ?>
            <div class="mt-2 small">
                <a href="<?= e(qs_url(['q' => null, 'status' => null, 'priority' => null, 'page' => null])) ?>">Clear all filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <caption class="caption-top text-muted small ps-3 pt-2">
                <?= $total === 0
                    ? 'No tasks match your search.'
                    : e(sprintf('Showing %d-%d of %d task%s', $firstSeen, $lastSeen, $total, $total === 1 ? '' : 's')) ?>
            </caption>
            <thead class="table-light">
            <tr>
                <th scope="col" style="width:3.5rem">#</th>
                <th scope="col">Title</th>
                <th scope="col">Description</th>
                <th scope="col" style="width:12rem">Status</th>
                <th scope="col" style="width:7rem">Priority</th>
                <th scope="col" style="width:8rem">Due</th>
                <th scope="col" style="width:10rem" class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <?php if ($total === 0 && $filters !== ['q' => '', 'status' => '', 'priority' => '']): ?>
                            Nothing found. <a href="<?= e(tt_url('tasks/index.php')) ?>">Show all tasks</a>.
                        <?php else: ?>
                            No tasks yet. <a href="<?= e(tt_url('tasks/create.php')) ?>">Add your first one</a>.
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php $overdue = $row['due_date'] && $row['status'] !== 'Completed' && $row['due_date'] < date('Y-m-d'); ?>
                <tr>
                    <td class="text-muted"><?= (int)$row['id'] ?></td>
                    <td class="fw-semibold"><?= e($row['title']) ?></td>
                    <td class="text-muted task-description"><?= nl2br(e($row['description'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(tt_url('tasks/status.php')) ?>"
                              class="d-flex gap-1 align-items-center">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="next" value="<?= e($next) ?>">
                            <label class="visually-hidden" for="status-<?= (int)$row['id'] ?>">Status for task <?= (int)$row['id'] ?></label>
                            <select name="status" id="status-<?= (int)$row['id'] ?>" class="form-select form-select-sm"
                                    data-autosubmit aria-label="Status">
                                <?php foreach (TaskRepository::STATUSES as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>>
                                        <?= e($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" data-no-js>Set</button>
                        </form>
                    </td>
                    <td>
                        <span class="badge text-bg-<?= e(priority_badge((string)$row['priority'])) ?>">
                            <?= e($row['priority']) ?>
                        </span>
                    </td>
                    <td class="small <?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= $row['due_date'] ? e(date('d M Y', strtotime((string)$row['due_date']))) : '-' ?>
                        <?php if ($overdue): ?><span class="d-block">(overdue)</span><?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(edit_url((int)$row['id'])) ?>">Edit</a>
                        <form method="post" action="<?= e(tt_url('tasks/delete.php')) ?>" class="d-inline"
                              data-confirm="Delete &quot;<?= e($row['title']) ?>&quot;? This cannot be undone.">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="next" value="<?= e($next) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="px-3 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2" aria-label="Task pages">
            <?php if ($page > 1): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(qs_url(['page' => $page - 1])) ?>">Previous</a>
            <?php else: ?>
                <span class="btn btn-sm btn-outline-secondary disabled">Previous</span>
            <?php endif; ?>

            <ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(qs_url(['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>

            <?php if ($page < $pages): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(qs_url(['page' => $page + 1])) ?>">Next</a>
            <?php else: ?>
                <span class="btn btn-sm btn-outline-secondary disabled">Next</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>

<?php require TT_ROOT . '/templates/footer.php'; ?>
