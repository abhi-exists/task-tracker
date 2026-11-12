<?php
/**
 * Create an account. Everyone who registers becomes a normal user - the role is
 * never read from the request, so a crafted "role=admin" field has no effect.
 */

require __DIR__ . '/../src/config.php';

require_methods(['GET', 'POST']);

if (auth_user() !== null) {
    tt_redirect(tt_url('tasks/index.php'));
}

$values = ['name' => '', 'email' => ''];
$errors = [];

if (is_post()) {
    csrf_require();

    $values['name']  = post_str('name');
    $values['email'] = post_str('email');
    $password        = (string)($_POST['password'] ?? '');
    $confirm         = (string)($_POST['password_confirm'] ?? '');

    $result = auth_register($values['name'], $values['email'], $password, $confirm);
    $errors = $result[0];

    if ($errors === []) {
        // Log the new account straight in: a verified insert plus a fresh
        // session id from auth_attempt().
        auth_attempt($values['email'], $password);
        flash('success', 'Account created. You can start adding tasks.');
        tt_redirect(tt_url('tasks/index.php'));
    }
}

$page_title = 'Create account';
require TT_ROOT . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Create an account</h1>

                <form method="post" action="<?= e(tt_url('register.php')) ?>" data-validate="register" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                               id="name" name="name" required maxlength="100"
                               autocomplete="name" value="<?= e($values['name']) ?>">
                        <div class="invalid-feedback <?= isset($errors['name']) ? '' : 'd-none' ?>" data-error-for="name">
                            <?= e($errors['name'] ?? 'Please enter your name.') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               id="email" name="email" required maxlength="190"
                               autocomplete="username" value="<?= e($values['email']) ?>">
                        <div class="invalid-feedback <?= isset($errors['email']) ? '' : 'd-none' ?>" data-error-for="email">
                            <?= e($errors['email'] ?? 'Please enter a valid email address.') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               id="password" name="password" required minlength="8" maxlength="200"
                               autocomplete="new-password">
                        <div class="invalid-feedback <?= isset($errors['password']) ? '' : 'd-none' ?>" data-error-for="password">
                            <?= e($errors['password'] ?? 'Password must be at least 8 characters.') ?>
                        </div>
                        <div class="form-text">At least 8 characters.</div>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirm password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                               id="password_confirm" name="password_confirm" required maxlength="200"
                               autocomplete="new-password">
                        <div class="invalid-feedback <?= isset($errors['password_confirm']) ? '' : 'd-none' ?>" data-error-for="password_confirm">
                            <?= e($errors['password_confirm'] ?? 'The two passwords do not match.') ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Create account</button>
                    <a class="btn btn-link" href="<?= e(tt_url('login.php')) ?>">I already have an account</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require TT_ROOT . '/templates/footer.php'; ?>
