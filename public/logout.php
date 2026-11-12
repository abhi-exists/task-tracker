<?php
/**
 * Sign out. POST only, with a CSRF token, so no link, crawler or cross-site
 * request can end somebody's session.
 */

require __DIR__ . '/../src/config.php';

csrf_require_post();

auth_logout();
flash('info', 'You have been signed out.');

tt_redirect(tt_url('login.php'));
