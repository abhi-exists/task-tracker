-- ============================================================================
-- Task Tracker - complete schema + sample data (Levels 1-3)
--
-- Import (choose one):
--   mysql -u root -p < sql/database.sql
--   mysql -h 127.0.0.1 -P 3306 -u root -p < sql/database.sql
--   phpMyAdmin / MySQL Workbench: paste this whole file and run it.
--
-- The file is repeatable: it drops and recreates the two tables, so importing
-- it again resets the sample data. The DROPs only ever touch task_tracker.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS task_tracker
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE task_tracker;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- users
--
-- password_hash holds a bcrypt hash produced by PHP's password_hash() with
-- PASSWORD_DEFAULT. It is CHAR(60) because that is exactly bcrypt's output
-- length; it grows to 255 if the algorithm is ever changed.
--
-- Test accounts (plain passwords are documented in README.md, and these
-- accounts are for local testing only - never use them on a real server):
--   admin@example.com  Admin@1234   role=admin
--   alice@example.com  Alice@1234   role=user
--   bob@example.com    Bob@1234     role=user
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)    NOT NULL,
    email         VARCHAR(190)    NOT NULL,
    password_hash CHAR(60)        NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- tasks
--
-- user_id is NOT NULL with ON DELETE CASCADE: every task belongs to somebody,
-- and deleting a user removes their tasks instead of orphaning them.
-- ----------------------------------------------------------------------------
CREATE TABLE tasks (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    title       VARCHAR(150)    NOT NULL,
    description TEXT            NULL,
    status      ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
    priority    ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
    due_date    DATE            NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tasks_user_id (user_id),
    KEY idx_tasks_status (status),
    KEY idx_tasks_priority (priority),
    KEY idx_tasks_due_date (due_date),
    KEY idx_tasks_created_at (created_at),
    CONSTRAINT fk_tasks_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Sample data
-- ----------------------------------------------------------------------------
INSERT INTO users (id, name, email, password_hash, role) VALUES
    (1, 'Alex Admin',  'admin@example.com', '$2y$10$6IW0LPPY5J68pYK2xn8D2.Nb4259YdVhOKvrCLK4obrfEAg86Bdme', 'admin'),
    (2, 'Alice Example', 'alice@example.com', '$2y$10$SBphuBs4ovcWRLTlkI7OPueNmWO4GjV360gHKvNNZkhmzckHr45.C', 'user'),
    (3, 'Bob Example',   'bob@example.com',   '$2y$10$fS9/nzcxXLM.4QppXdpOhOP6PK2jHALD7VYxNoBryT5W929vUTC2m', 'user');

-- Alex Admin (user_id 1)
INSERT INTO tasks (user_id, title, description, status, priority, due_date, created_at) VALUES
    (1, 'Approve the monthly report', 'Sign off the finance summary before it is published.', 'Pending', 'High', DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY)),
    (1, 'Rotate the database password', 'Scheduled quarterly rotation; update config.php afterwards.', 'In Progress', 'High', DATE_ADD(CURDATE(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
    (1, 'Review open support tickets', 'Check that nothing older than a week is still unanswered.', 'Pending', 'Medium', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (1, 'Clean up stale accounts', 'Remove test accounts left over from last month''s load run.', 'Completed', 'Low', DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY));

-- Alice Example (user_id 2)
INSERT INTO tasks (user_id, title, description, status, priority, due_date, created_at) VALUES
    (2, 'Write project proposal', 'Draft the Q3 proposal and send it to the team for review.', 'Pending', 'High', DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
    (2, 'Fix login redirect', 'Users land on a 404 after signing in from the dashboard link.', 'In Progress', 'High', DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
    (2, 'Update dependency versions', 'Bump the front-end packages and re-run the smoke test.', 'Pending', 'Medium', NULL, DATE_SUB(NOW(), INTERVAL 8 DAY)),
    (2, 'Review quarterly budget', 'Compare actuals against forecast and note variances over 5%.', 'Completed', 'Medium', DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
    (2, 'Refactor report export', 'The CSV export duplicates headers when the date filter is empty.', 'Pending', 'Low', DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Bob Example (user_id 3)
INSERT INTO tasks (user_id, title, description, status, priority, due_date, created_at) VALUES
    (3, 'Write unit tests for the payment module', 'Cover the refund path, which has no assertions today.', 'Pending', 'High', NULL, DATE_SUB(NOW(), INTERVAL 7 DAY)),
    (3, 'Clean up the staging database', 'Remove accounts created by last month''s load test.', 'In Progress', 'Low', DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
    (3, 'Document the deployment steps', 'A fresh machine should be able to follow the README without asking questions.', 'Pending', 'Medium', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
    (3, 'Replace the logo', 'The current PNG looks blurry on high-density screens.', 'Completed', 'Low', DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (3, 'Investigate slow dashboard query', 'The overview takes 4 seconds for accounts with more than 1000 rows.', 'In Progress', 'High', DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));
