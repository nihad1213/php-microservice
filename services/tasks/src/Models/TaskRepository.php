<?php
declare(strict_types=1);

namespace App\Models;

use Core\Database\Database;

class TaskRepository
{
    private const COLUMNS = 'id, title, description, status, due_date, created_at, updated_at';
    private const EDITABLE = ['title', 'description', 'status', 'due_date'];

    public function paginate(int $userId, ?string $status, int $page, int $limit): array
    {
        $where = 'user_id = ?';
        $params = [$userId];

        if ($status !== null) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }

        $total = (int) Database::fetchOne("SELECT COUNT(*) AS total FROM tasks WHERE {$where}", $params)['total'];

        $offset = ($page - 1) * $limit;

        $rows = Database::fetchAll(
            'SELECT ' . self::COLUMNS . " FROM tasks WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        return [array_map([$this, 'cast'], $rows), $total];
    }

    public function find(int $userId, int $id): ?array
    {
        $task = Database::fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM tasks WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        return $task === null ? null : $this->cast($task);
    }

    public function create(int $userId, array $data): int
    {
        $fields = array_intersect_key($data, array_flip(self::EDITABLE));
        $columns = array_merge(['user_id'], array_keys($fields));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        Database::execute(
            'INSERT INTO tasks (' . implode(', ', $columns) . ") VALUES ({$placeholders})",
            array_merge([$userId], array_values($fields))
        );

        return (int) Database::lastInsertId();
    }

    public function update(int $userId, int $id, array $data): void
    {
        $fields = array_intersect_key($data, array_flip(self::EDITABLE));

        if ($fields === []) {
            return;
        }

        $assignments = implode(', ', array_map(static fn (string $column): string => "{$column} = ?", array_keys($fields)));

        Database::execute(
            "UPDATE tasks SET {$assignments} WHERE id = ? AND user_id = ?",
            array_merge(array_values($fields), [$id, $userId])
        );
    }

    public function delete(int $userId, int $id): bool
    {
        return Database::execute('DELETE FROM tasks WHERE id = ? AND user_id = ?', [$id, $userId]) > 0;
    }

    private function cast(array $task): array
    {
        $task['id'] = (int) $task['id'];

        return $task;
    }
}
