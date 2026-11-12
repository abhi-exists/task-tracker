<?php
/** Landing page: signed-in users get the task list, everyone else gets login. */

require __DIR__ . '/../src/config.php';

if (auth_user() === null) {
    tt_redirect(tt_url('login.php'));
}

tt_redirect(tt_url('tasks/index.php'));
