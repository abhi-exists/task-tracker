<?php
/**
 * Sign in. The page itself is public; every task screen is not.
 * Failures always show the same generic message so the form cannot be used to
 * probe which email addresses exist.
 */

require __DIR__ . '/../src/config.php';

require_methods(['GET', 'POST']);

if (auth_user() !== null) {
    tt_redirect(tt_url('tasks/index.php'));
}

$email    = '';
$error    = '';
$next     = tt_safe_target($_GET['next'] ?? $_POST['next'] ?? '', tt_url('tasks/index.php'));
$attempted = false;

if (is_post()) {
    csrf_require();
    $attempted = true;
    $email     = post_str('email');
    $password  = (string)($_POST['password'] ?? '');

    if (auth_attempt($email, $password)) {
        flash('success', 'Welcome back.');
        tt_redirect($next);
    }

    // Deliberately vague: never say whether the email or the password was wrong.
    $error = 'Invalid email or password.';
    unset($_POST['password']);
}

$page_title = 'Sign in';
require TT_ROOT . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-3">Sign in</h1>

                <?php if ($attempted && $error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(tt_url('login.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               required maxlength="190" autocomplete="username" value="<?= e($email) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               required autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Sign in</button>
                </form>

                <p class="mt-3 mb-0 small text-muted">
                    No account yet? <a href="<?= e(tt_url('register.php')) ?>">Register</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require TT_ROOT . '/templates/footer.php'; ?>
