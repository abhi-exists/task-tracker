<?php
/**
 * All task SQL lives here. Every statement that receives input is a prepared
 * statement with named placeholders - user input is never concatenated into SQL.
 * LIMIT/OFFSET are bound as integers too.
 */

declare(strict_types=1);

final class TaskRepository
{
    /** Allowed status values - mirrored by the MySQL ENUM column. */
    public const STATUSES = ['Pending', 'In Progress', 'Completed'];

    /** Allowed priority values - mirrored by the MySQL ENUM column. */
    public const PRIORITIES = ['Low', 'Medium', 'High'];

    /** Tasks shown per page. */
    public const PER_PAGE = 10;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array{q?:string,status?:string,priority?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function search(array $filters = [], int $page = 1, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $limit  = max(1, min(100, $perPage));
        $offset = max(0, ($page - 1) * $limit);

        $sql = 'SELECT id, title, description, status, priority, due_date, created_at
                  FROM tasks' . $where . '
              ORDER BY created_at DESC, id DESC
                 LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Number of tasks matching the same filters (used for pagination). */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM tasks' . $where);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /** Totals per status for the dashboard cards, plus the overall total. */
    public function statusCounts(array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) AS total FROM tasks' . $where . ' GROUP BY status'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $counts = array_fill_keys(self::STATUSES, 0);
        $total  = 0;
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int)$row['total'];
            $total                 += (int)$row['total'];
        }
        $counts['total'] = $total;

        return $counts;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, description, status, priority, due_date, created_at
               FROM tasks
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Insert a task and return its new id. */
    public function create(string $title, string $description, string $status = 'Pending',
                           string $priority = 'Medium', ?string $dueDate = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (title, description, status, priority, due_date, created_at)
             VALUES (:title, :description, :status, :priority, :due_date, NOW())'
        );
        $stmt->execute([
            ':title'       => $title,
            ':description' => $description,
            ':status'      => $status,
            ':priority'    => $priority,
            ':due_date'    => $dueDate,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /** Update the editable fields of a task. */
    public function update(int $id, string $title, string $description, string $status,
                           string $priority, ?string $dueDate): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks
                SET title       = :title,
                    description = :description,
                    status      = :status,
                    priority    = :priority,
                    due_date    = :due_date
              WHERE id = :id'
        );
        $stmt->execute([
            ':title'       => $title,
            ':description' => $description,
            ':status'      => $status,
            ':priority'    => $priority,
            ':due_date'    => $dueDate,
            ':id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Status-only change from the list page dropdown. */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE tasks SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Shared WHERE clause for search()/count()/statusCounts(). Values are only
     * ever referenced through placeholders, never interpolated.
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function buildWhere(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['q'])) {
            // Neutralise LIKE wildcards so "100%" searches for a literal "100%".
            $params[':q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']) . '%';
            $where[]      = 'title LIKE :q';
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $params[':status'] = $filters['status'];
            $where[]           = 'status = :status';
        }

        if (!empty($filters['priority']) && in_array($filters['priority'], self::PRIORITIES, true)) {
            $params[':priority'] = $filters['priority'];
            $where[]             = 'priority = :priority';
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
    }
}
