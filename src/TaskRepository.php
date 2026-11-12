<?php
/**
 * All task SQL lives here. Every statement is a prepared statement with named
 * placeholders; user input is never concatenated into SQL.
 */

declare(strict_types=1);

final class TaskRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Every task, newest first. */
    public function all(): array
    {
        $sql = 'SELECT id, title, description, status, created_at
                  FROM tasks
              ORDER BY created_at DESC, id DESC';

        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, description, status, created_at
               FROM tasks
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Insert a task and return its new id. */
    public function create(string $title, string $description): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (title, description, status, created_at)
             VALUES (:title, :description, :status, NOW())'
        );
        $stmt->execute([
            ':title'       => $title,
            ':description' => $description,
            ':status'      => 'Pending',
        ]);

        return (int)$this->db->lastInsertId();
    }

    /** Update the text of a task. Returns true when a row actually changed. */
    public function update(int $id, string $title, string $description): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks
                SET title = :title,
                    description = :description
              WHERE id = :id'
        );
        $stmt->execute([
            ':title'       => $title,
            ':description' => $description,
            ':id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
