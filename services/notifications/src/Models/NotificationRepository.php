<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Database;

class NotificationRepository
{
    private const COLUMNS = 'id, type, message, data, is_read, created_at';

    public function create(int $userId, string $type, string $message, array $data): int
    {
        Database::execute(
            'INSERT INTO notifications (user_id, type, message, data) VALUES (?, ?, ?, ?)',
            [$userId, $type, $message, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );

        return (int) Database::lastInsertId();
    }

    public function paginate(int $userId, bool $unreadOnly, int $page, int $limit): array
    {
        $where = $unreadOnly ? 'user_id = ? AND is_read = 0' : 'user_id = ?';

        $total = (int) Database::fetchOne("SELECT COUNT(*) AS total FROM notifications WHERE {$where}", [$userId])['total'];

        $offset = ($page - 1) * $limit;

        $rows = Database::fetchAll(
            'SELECT ' . self::COLUMNS . " FROM notifications WHERE {$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
            [$userId]
        );

        return [array_map([$this, 'present'], $rows), $total];
    }

    public function unreadCount(int $userId): int
    {
        return (int) Database::fetchOne(
            'SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        )['total'];
    }

    public function find(int $userId, int $id): ?array
    {
        $row = Database::fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM notifications WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        return $row === null ? null : $this->present($row);
    }

    public function markRead(int $userId, int $id): void
    {
        Database::execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    private function present(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['is_read'] = (bool) $row['is_read'];
        $row['data'] = $row['data'] === null ? null : json_decode($row['data'], true);

        return $row;
    }
}