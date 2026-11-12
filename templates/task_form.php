<?php
/**
 * Add/edit form, shared by tasks/create.php and tasks/edit.php.
 * The HTML5 attributes below mirror the server rules in validate_task(); the
 * browser checks them first, PHP always checks again.
 *
 * @var array|null $task        existing task (edit) or null (add)
 * @var string     $formAction  where the form posts to
 * @var array      $errors      field => message
 */
$isEdit = !empty($task['id']);

$title       = $task['title'] ?? '';
$description = $task['description'] ?? '';
$status      = $task['status'] ?? 'Pending';
$priority    = $task['priority'] ?? 'Medium';
$dueDate     = $task['due_date'] ?? '';
?>
<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h4 card-title mb-3">
            <?= $isEdit ? 'Edit task #' . e($task['id']) : 'Add a task' ?>
        </h1>

        <form method="post" action="<?= e($formAction) ?>" data-validate="task" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e(next_query()) ?>">

            <div class="mb-3">
                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text"
                       class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                       id="title" name="title" required maxlength="150" autofocus
                       value="<?= e($title) ?>">
                <div class="invalid-feedback <?= isset($errors['title']) ? '' : 'd-none' ?>" data-error-for="title">
                    <?= e($errors['title'] ?? 'Title is required.') ?>
                </div>
                <div class="form-text"><span data-counter-for="title"><?= e(mb_strlen((string)$title)) ?></span>/150 characters</div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                          id="description" name="description" rows="6"
                          maxlength="2000"><?= e($description) ?></textarea>
                <div class="invalid-feedback <?= isset($errors['description']) ? '' : 'd-none' ?>" data-error-for="description">
                    <?= e($errors['description'] ?? 'Description is too long.') ?>
                </div>
                <div class="form-text"><span data-counter-for="description"><?= e(mb_strlen((string)$description)) ?></span>/2000 characters</div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>"
                            id="status" name="status">
                        <?php foreach (TaskRepository::STATUSES as $option): ?>
                            <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback <?= isset($errors['status']) ? '' : ' d-none' ?>" data-error-for="status">
                        <?= e($errors['status'] ?? 'Please choose a valid status.') ?>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-select <?= isset($errors['priority']) ? 'is-invalid' : '' ?>"
                            id="priority" name="priority">
                        <?php foreach (TaskRepository::PRIORITIES as $option): ?>
                            <option value="<?= e($option) ?>" <?= $priority === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback <?= isset($errors['priority']) ? '' : ' d-none' ?>" data-error-for="priority">
                        <?= e($errors['priority'] ?? 'Please choose a valid priority.') ?>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <label for="due_date" class="form-label">Due date <span class="text-muted">(optional)</span></label>
                    <input type="date" class="form-control <?= isset($errors['due_date']) ? 'is-invalid' : '' ?>"
                           id="due_date" name="due_date" value="<?= e($dueDate) ?>">
                    <div class="invalid-feedback <?= isset($errors['due_date']) ? '' : ' d-none' ?>" data-error-for="due_date">
                        <?= e($errors['due_date'] ?? 'Please enter a valid due date.') ?>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Add task' ?></button>
                <a class="btn btn-link" href="<?= e(tt_url('tasks/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
