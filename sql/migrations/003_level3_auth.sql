-- ============================================================================
-- Level 3 migration: apply on top of the Level 2 schema.
--   mysql -u root -p task_tracker < sql/migrations/003_level3_auth.sql
--
-- A fresh install does not need this file - sql/database.sql already contains
-- the final schema. This upgrades an existing database without losing tasks:
-- existing rows are handed to the new admin account, then the column becomes
-- NOT NULL and the foreign key is added.
--
-- Sample accounts (see README.md): admin@example.com / Admin@1234,
-- alice@example.com / Alice@1234, bob@example.com / Bob@1234
-- ============================================================================

USE task_tracker;

CREATE TABLE IF NOT EXISTS users (
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

INSERT INTO users (id, name, email, password_hash, role) VALUES
    (1, 'Alex Admin',    'admin@example.com', '$2y$10$6IW0LPPY5J68pYK2xn8D2.Nb4259YdVhOKvrCLK4obrfEAg86Bdme', 'admin'),
    (2, 'Alice Example', 'alice@example.com', '$2y$10$SBphuBs4ovcWRLTlkI7OPueNmWO4GjV360gHKvNNZkhmzckHr45.C', 'user'),
    (3, 'Bob Example',   'bob@example.com',   '$2y$10$fS9/nzcxXLM.4QppXdpOhOP6PK2jHALD7VYxNoBryT5W929vUTC2m', 'user')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 1. Add the column as nullable so existing rows can be migrated.
ALTER TABLE tasks
    ADD COLUMN user_id INT UNSIGNED NULL AFTER id;

-- 2. Hand every pre-Level-3 task to the admin account.
UPDATE tasks SET user_id = 1 WHERE user_id IS NULL;

-- 3. Now it can become NOT NULL, indexed and referenced.
ALTER TABLE tasks
    MODIFY user_id INT UNSIGNED NOT NULL,
    ADD KEY idx_tasks_user_id (user_id);

ALTER TABLE tasks
    ADD CONSTRAINT fk_tasks_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE;

-- 4. A couple of tasks for each sample user, so ownership is visible straight away.
INSERT INTO tasks (user_id, title, description, status, priority, due_date) VALUES
    (2, 'Prepare the sprint demo', 'Show the search and filter work to the team.', 'Pending', 'Medium', DATE_ADD(CURDATE(), INTERVAL 2 DAY)),
    (2, 'Triage the new bug reports', 'Tag duplicates and set priorities.', 'In Progress', 'Low', NULL),
    (3, 'Book the meeting room', 'Friday 3pm, needs a projector.', 'Completed', 'Low', NULL),
    (3, 'Test the password reset email', 'Check the link works in the spam folder too.', 'Pending', 'High', DATE_ADD(CURDATE(), INTERVAL 1 DAY));
