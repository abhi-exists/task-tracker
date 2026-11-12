<?php
/**
 * COPY THIS FILE TO config.php AND FILL IN YOUR OWN CREDENTIALS:
 *
 *     cp config.sample.php config.php      (Windows: copy config.sample.php config.php)
 *
 * config.php is git-ignored, so real passwords never end up in the repository.
 * The values below are the usual XAMPP defaults.
 */

return [
    'app' => [
        'name'     => 'Task Tracker',
        'timezone' => 'UTC',
        // true shows raw PHP errors on screen. Development only - leave false
        // so users only ever see the friendly error page.
        'debug'    => false,
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,            // XAMPP: 3306 | MAMP: 8889
        'name'    => 'task_tracker',
        'user'    => 'root',
        'pass'    => '',              // XAMPP default: empty
        'charset' => 'utf8mb4',
    ],
];
