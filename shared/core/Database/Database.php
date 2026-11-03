<?php
declare(strict_types=1);

namespace Core\Database;

use PDO;
use PDOStatement;
use RuntimeException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $name = self::env('DB_NAME');
        if ($name === '') {
            throw new RuntimeException('DB_NAME is not set');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            self::env('DB_HOST', 'localhost'),
            self::env('DB_PORT', '3306'),
            $name
        );

        self::$pdo = new PDO($dsn, self::env('DB_USER'), self::env('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return $value === false ? $default : (string) $value;
    }
}
