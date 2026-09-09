<?php
declare(strict_types=1);

use App\Support\Database;

require dirname(__DIR__) . '/htdocs/vendor/autoload.php';

$config = require dirname(__DIR__) . '/htdocs/config/database.php';

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--database=')) {
        $config['database'] = substr($argument, strlen('--database='));
    }
}

$pdo = Database::connect($config);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query(
    'SELECT migration FROM schema_migrations'
)->fetchAll(PDO::FETCH_COLUMN);

$known = array_fill_keys($applied, true);
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file);

    if (isset($known[$name])) {
        continue;
    }

    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException("Cannot read migration {$name}");
    }

    $pdo->exec($sql);

    $statement = $pdo->prepare(
        'INSERT INTO schema_migrations (migration) VALUES (:migration)'
    );
    $statement->execute(['migration' => $name]);

    echo "Applied {$name}\n";
}
