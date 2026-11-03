<?php
declare(strict_types=1);

namespace App\Models;

use Core\Database\Database;

class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $user = Database::fetchOne(
            'SELECT id, email, password_hash, created_at FROM users WHERE email = ?',
            [$email]
        );

        return $user === null ? null : $this->cast($user);
    }

    public function findById(int $id): ?array
    {
        $user = Database::fetchOne('SELECT id, email, created_at FROM users WHERE id = ?', [$id]);

        return $user === null ? null : $this->cast($user);
    }

    public function create(string $email, string $passwordHash): int
    {
        Database::execute(
            'INSERT INTO users (email, password_hash) VALUES (?, ?)',
            [$email, $passwordHash]
        );

        return (int) Database::lastInsertId();
    }

    private function cast(array $user): array
    {
        $user['id'] = (int) $user['id'];

        return $user;
    }
}
