<?php
/** Landing page - the task list is the dashboard. */

require __DIR__ . '/../src/config.php';

tt_redirect(tt_url('tasks/index.php'));
