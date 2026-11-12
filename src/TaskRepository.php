<?php
/**
 * All task SQL lives here, including the authorization rules.
 *
 * Two invariants:
 *   - every statement that receives input is a prepared statement with named
 *     placeholders; nothing is ever concatenated into SQL;
 *   - ownership is enforced in the WHERE clause of the query itself
 *     (WHERE id = :id AND user_id = :uid), so a tampered id in a URL or a POST
 *     body matches no row instead of touching somebody else's data.
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
     * Tasks the actor may see: their own, or everybody's when the actor is an
     * admin (admins also get the owner columns).
     *
     * @param array{q?:string,status?:string,priority?:string} $filters
     * @param array{id:int,role:string} $actor
     * @return array<int,array<string,mixed>>
     */
    public function search(array $filters, array $actor, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = $this->buildWhere($filters, $actor);

        $limit  = max(1, min(100, $perPage));
        $offset = max(0, ($page - 1) * $limit);

        $sql = 'SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at,
                       t.user_id, u.name AS owner_name, u.email AS owner_email
                  FROM tasks t
             LEFT JOIN users u ON u.id = t.user_id' . $where . '
              ORDER BY t.created_at DESC, t.id DESC
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

    /** Number of tasks matching the same filters and scope (used for pagination). */
    public function count(array $filters, array $actor): int
    {
        [$where, $params] = $this->buildWhere($filters, $actor);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM tasks t' . $where);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /** Totals per status for the dashboard cards, within the actor's scope. */
    public function statusCounts(array $filters, array $actor): array
    {
        [$where, $params] = $this->buildWhere($filters, $actor);

        $stmt = $this->db->prepare(
            'SELECT t.status, COUNT(*) AS total FROM tasks t' . $where . ' GROUP BY t.status'
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

    /**
     * Fetch one task within the actor's scope.
     * A normal user only ever gets their own row; an admin gets any row.
     * Returns null when the task exists but belongs to someone else, or does
     * not exist - the caller uses exists() to decide between 403 and 404.
     *
     * @param array{id:int,role:string} $actor
     */
    public function find(int $id, array $actor): ?array
    {
        if ($actor['role'] === 'admin') {
            $sql = 'SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at,
                           t.user_id, u.name AS owner_name, u.email AS owner_email
                      FROM tasks t
                 LEFT JOIN users u ON u.id = t.user_id
                     WHERE t.id = :id
                     LIMIT 1';
            $params = [':id' => $id];
        } else {
            // The ownership rule is part of the query, not a check afterwards.
            $sql = 'SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at,
                           t.user_id, u.name AS owner_name, u.email AS owner_email
                      FROM tasks t
                 LEFT JOIN users u ON u.id = t.user_id
                     WHERE t.id = :id
                       AND t.user_id = :uid
                     LIMIT 1';
            $params = [':id' => $id, ':uid' => (int)$actor['id']];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Does this id exist at all? Used only to choose 404 vs 403 for the page. */
    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM tasks WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    /** Insert a task owned by the actor and return its new id. */
    public function create(string $title, string $description, string $status, string $priority,
                           ?string $dueDate, array $actor): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (user_id, title, description, status, priority, due_date, created_at)
             VALUES (:user_id, :title, :description, :status, :priority, :due_date, NOW())'
        );
        $stmt->execute([
            ':user_id'     => (int)$actor['id'],
            ':title'       => $title,
            ':description' => $description,
            ':status'      => $status,
            ':priority'    => $priority,
            ':due_date'    => $dueDate,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update the editable fields. Always owner-only, admins included: an admin
     * may view and delete anyone's task but must not rewrite somebody else's.
     */
    public function update(int $id, string $title, string $description, string $status,
                           string $priority, ?string $dueDate, array $actor): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks
                SET title       = :title,
                    description = :description,
                    status      = :status,
                    priority    = :priority,
                    due_date    = :due_date
              WHERE id = :id
                AND user_id = :uid'
        );
        $stmt->execute([
            ':title'       => $title,
            ':description' => $description,
            ':status'      => $status,
            ':priority'    => $priority,
            ':due_date'    => $dueDate,
            ':id'          => $id,
            ':uid'         => (int)$actor['id'],
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Status-only change from the list page dropdown; owner-only as well. */
    public function updateStatus(int $id, string $status, array $actor): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks SET status = :status WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':status' => $status, ':id' => $id, ':uid' => (int)$actor['id']]);

        return $stmt->rowCount() > 0;
    }

    /** Delete: the owner may delete their own task, an admin may delete any. */
    public function delete(int $id, array $actor): bool
    {
        if ($actor['role'] === 'admin') {
            $stmt   = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
            $params = [':id' => $id];
        } else {
            $stmt   = $this->db->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :uid');
            $params = [':id' => $id, ':uid' => (int)$actor['id']];
        }
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Shared WHERE clause for search()/count()/statusCounts(). Values are only
     * ever referenced through placeholders, never interpolated. The actor's
     * scope is added here, so a list, its count and its cards can never
     * disagree about whose tasks are being shown.
     *
     * @param array{q?:string,status?:string,priority?:string} $filters
     * @param array{id:int,role:string} $actor
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters, array $actor): array
    {
        $where  = [];
        $params = [];

        if ($actor['role'] !== 'admin') {
            $params[':actor_uid'] = (int)$actor['id'];
            $where[]              = 't.user_id = :actor_uid';
        }

        if (!empty($filters['q'])) {
            // Neutralise LIKE wildcards so searching for "100%" looks for a
            // literal "100%" instead of matching every row.
            $params[':q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']) . '%';
            $where[]      = 't.title LIKE :q';
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $params[':status'] = $filters['status'];
            $where[]           = 't.status = :status';
        }

        if (!empty($filters['priority']) && in_array($filters['priority'], self::PRIORITIES, true)) {
            $params[':priority'] = $filters['priority'];
            $where[]             = 't.priority = :priority';
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
    }
}

/** Is this task the actor's own? Drives which action buttons are rendered. */
function task_owned_by(array $task, array $actor): bool
{
    return (int)$task['user_id'] === (int)$actor['id'];
}
