-- ============================================================================
-- Task Tracker - schema
-- Level 2: status becomes an ENUM, priority and due date are added, and indexes
-- back the search / filter / ordering queries.
--
-- Import with:
--   mysql -u root -p < sql/database.sql
-- or paste the whole file into phpMyAdmin / MySQL Workbench and run it.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS task_tracker
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE task_tracker;

CREATE TABLE IF NOT EXISTS tasks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(150) NOT NULL,
    description TEXT         NULL,
    status      ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
    priority    ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
    due_date    DATE         NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tasks_status (status),
    KEY idx_tasks_priority (priority),
    KEY idx_tasks_due_date (due_date),
    KEY idx_tasks_created_at (created_at)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Sample rows: enough to show the status filter, the priority filter and a
-- second page of results (PER_PAGE is 10, so there are 12 tasks here).
INSERT INTO tasks (title, description, status, priority, due_date, created_at) VALUES
    ('Write project proposal', 'Draft the Q3 proposal and send it to the team for review.', 'Pending', 'High', DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY)),
    ('Fix login redirect', 'Users land on a 404 after signing in from the dashboard link.', 'In Progress', 'High', DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)),
    ('Update dependency versions', 'Bump the front-end packages and re-run the smoke test.', 'Pending', 'Medium', NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
    ('Review quarterly budget', 'Compare actuals against forecast and note variances over 5%.', 'Completed', 'Medium', DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY)),
    ('Refactor report export', 'The CSV export duplicates headers when the date filter is empty.', 'Pending', 'Low', DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
    ('Write unit tests for the payment module', 'Cover the refund path, which has no assertions today.', 'Pending', 'High', NULL, DATE_SUB(NOW(), INTERVAL 7 DAY)),
    ('Clean up the staging database', 'Remove accounts created by last month''s load test.', 'In Progress', 'Low', DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
    ('Document the deployment steps', 'A fresh machine should be able to follow the README without asking questions.', 'Pending', 'Medium', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
    ('Replace the logo', 'The current PNG looks blurry on high-density screens.', 'Completed', 'Low', DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
    ('Investigate slow dashboard query', 'The overview takes 4 seconds for accounts with more than 1000 rows.', 'In Progress', 'High', DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
    ('Prepare the release notes', 'Summarise the changes since 1.4 and list known issues.', 'Pending', 'Medium', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
    ('Archive last year''s invoices', 'Move them to cold storage and keep the index up to date.', 'Completed', 'Low', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY));
