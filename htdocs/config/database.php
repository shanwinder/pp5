<?php
declare(strict_types=1);

$localPath = __DIR__ . '/local.php';
$local = is_file($localPath) ? require $localPath : [];

return [
    'host' => $local['database']['host'] ?? getenv('DB_HOST') ?: 'localhost',
    'port' => (int) ($local['database']['port'] ?? getenv('DB_PORT') ?: 3306),
    'database' => $local['database']['database'] ?? getenv('DB_DATABASE') ?: 'pp5',
    'username' => $local['database']['username'] ?? getenv('DB_USERNAME') ?: '',
    'password' => $local['database']['password'] ?? getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
