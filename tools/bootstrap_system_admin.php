<?php
declare(strict_types=1);

namespace PP5\Bootstrap;

use App\Repositories\RoleAssignmentRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use DomainException;
use PDO;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once dirname(__DIR__) . '/htdocs/vendor/autoload.php';

/** Parse only the documented --name=value interface; never echo argument values. */
function parseArguments(#[\SensitiveParameter] array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (!preg_match('/^--(username|display-name|password|email|database)=(.*)$/sD', $argument, $matches)
            || array_key_exists($matches[1], $options)) {
            throw new DomainException('Unsupported, malformed, or repeated argument.');
        }
        $options[$matches[1]] = $matches[2];
    }
    return validateOptions($options);
}

function validateOptions(#[\SensitiveParameter] array $options): array
{
    foreach (['username', 'display-name', 'password'] as $required) {
        if (!isset($options[$required]) || !is_string($options[$required])) {
            throw new DomainException('Required options: --username, --display-name, --password.');
        }
    }
    $options['username'] = trim($options['username']);
    if (strlen($options['username']) < 3 || strlen($options['username']) > 100
        || !preg_match('/^[A-Za-z0-9._-]+$/D', $options['username'])) {
        throw new DomainException('Username must contain 3–100 letters, digits, dots, underscores, or hyphens.');
    }
    $options['display-name'] = trim($options['display-name']);
    if (mb_strlen($options['display-name'], 'UTF-8') < 1 || mb_strlen($options['display-name'], 'UTF-8') > 190) {
        throw new DomainException('Display name must contain 1–190 characters.');
    }
    $options['email'] = isset($options['email']) ? trim($options['email']) : null;
    $options['email'] = $options['email'] === '' ? null : $options['email'];
    if ($options['email'] !== null && filter_var($options['email'], FILTER_VALIDATE_EMAIL) === false) {
        throw new DomainException('Provide a valid email address.');
    }
    if (strlen($options['password']) < 12) {
        throw new DomainException('Password must contain at least 12 characters.');
    }
    if (isset($options['database']) && !preg_match('/^[A-Za-z0-9_]+$/D', $options['database'])) {
        throw new DomainException('Provide a valid database name.');
    }
    return $options;
}

/** Atomically create a new global administrator; existing accounts are never promoted. */
function createAdministrator(PDO $pdo, #[\SensitiveParameter] array $options): string
{
    $options = validateOptions($options);
    $pdo->beginTransaction();
    try {
        $role = (new RoleRepository($pdo))->findActiveByCode('SYSTEM_ADMIN');
        if ($role === null || $role['scope_type'] !== 'SYSTEM') {
            throw new DomainException('Seed an ACTIVE SYSTEM_ADMIN role with SYSTEM scope before bootstrap.');
        }
        $userId = (new UserRepository($pdo))->create(
            $options['username'], $options['email'], password_hash($options['password'], PASSWORD_DEFAULT), $options['display-name']
        );
        (new RoleAssignmentRepository($pdo))->assignSystemRole($userId, (int) $role['id'], null);
        $pdo->commit();
        return $options['username'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof DomainException) {
            throw $exception;
        }
        throw new DomainException('Unable to create SYSTEM_ADMIN. Check database setup and try again.');
    }
}

function main(#[\SensitiveParameter] array $arguments): int
{
    try {
        $options = parseArguments($arguments);
        $config = require dirname(__DIR__) . '/htdocs/config/database.php';
        if (isset($options['database'])) {
            $config['database'] = $options['database'];
        }
        $username = createAdministrator(Database::connect($config), $options);
        fwrite(STDOUT, 'Created SYSTEM_ADMIN user: ' . $username . "\n");
        return 0;
    } catch (DomainException $exception) {
        fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n");
    } catch (Throwable) {
        fwrite(STDERR, "Error: Unable to bootstrap SYSTEM_ADMIN. Check database setup and try again.\n");
    }
    return 1;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main(array_slice($argv, 1)));
}
