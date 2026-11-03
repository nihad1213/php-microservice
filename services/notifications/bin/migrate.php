<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use Core\Config\Env;
use Core\Database\Database;

Env::load(__DIR__ . '/../.env');

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        name VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = array_column(Database::fetchAll('SELECT name FROM migrations'), 'name');

$files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files);

$count = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (in_array($name, $applied, true)) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Cannot read {$name}\n");
        exit(1);
    }

    $pdo->exec($sql);
    Database::execute('INSERT INTO migrations (name) VALUES (?)', [$name]);

    echo "Applied: {$name}\n";
    $count++;
}

echo $count === 0 ? "Nothing to migrate.\n" : "Done. {$count} migration(s) applied.\n";
