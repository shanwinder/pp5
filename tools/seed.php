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
    'CREATE TABLE IF NOT EXISTS seed_migrations (
        seed VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT seed FROM seed_migrations')->fetchAll(PDO::FETCH_COLUMN);
$known = array_fill_keys($applied, true);
$files = glob(dirname(__DIR__) . '/database/seeds/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (isset($known[$name])) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read seed {$name}");
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $statement = $pdo->prepare('INSERT INTO seed_migrations (seed) VALUES (:seed)');
        $statement->execute(['seed' => $name]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    echo "Applied {$name}\n";
}
