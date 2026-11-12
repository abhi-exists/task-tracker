-- ============================================================================
-- Level 2 migration: apply on top of the Level 1 schema.
--   mysql -u root -p task_tracker < sql/migrations/002_level2_status_search.sql
--
-- A fresh install does not need this file - sql/database.sql already contains
-- the final schema. It is here so an existing Level 1 database can be upgraded
-- without dropping data.
-- ============================================================================

USE task_tracker;

ALTER TABLE tasks
    MODIFY status ENUM('Pending', 'In Progress', 'Completed') NOT NULL DEFAULT 'Pending',
    ADD COLUMN priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium' AFTER status,
    ADD COLUMN due_date DATE NULL AFTER priority,
    ADD KEY idx_tasks_status (status),
    ADD KEY idx_tasks_priority (priority),
    ADD KEY idx_tasks_due_date (due_date),
    ADD KEY idx_tasks_created_at (created_at);
