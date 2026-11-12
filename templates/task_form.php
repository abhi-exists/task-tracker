<?php
/**
 * Add/edit form, shared by tasks/create.php and tasks/edit.php.
 *
 * @var array|null $task        existing task (edit) or null (add)
 * @var string     $formAction  where the form posts to
 * @var array      $errors      field => message
 */
$isEdit = !empty($task['id']);
?>
<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h4 card-title mb-3">
            <?= $isEdit ? 'Edit task #' . e($task['id']) : 'Add a task' ?>
        </h1>

        <form method="post" action="<?= e($formAction) ?>">
            <div class="mb-3">
                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text"
                       class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                       id="title" name="title" required autofocus
                       value="<?= e($task['title'] ?? '') ?>">
                <?php if (isset($errors['title'])): ?>
                    <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                          id="description" name="description" rows="6"><?= e($task['description'] ?? '') ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback"><?= e($errors['description']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Add task' ?></button>
            <a class="btn btn-link" href="<?= e(tt_url('tasks/index.php')) ?>">Cancel</a>
        </form>
    </div>
</div>
