-- ============================================================================
-- Task Tracker - schema
-- Level 1: the tasks table.
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
    status      VARCHAR(20)  NOT NULL DEFAULT 'Pending',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Sample rows so the list page has something to show.
INSERT INTO tasks (title, description, status) VALUES
    ('Write project proposal', 'Draft the Q3 proposal and send it to the team for review.', 'Pending'),
    ('Fix login redirect', 'Users land on a 404 after signing in from the dashboard link.', 'In Progress'),
    ('Update dependency versions', 'Bump the front-end packages and re-run the smoke test.', 'Pending'),
    ('Review quarterly budget', 'Compare actuals against forecast and note variances over 5%.', 'Completed'),
    ('Refactor report export', 'The CSV export duplicates headers when the date filter is empty.', 'Pending');
