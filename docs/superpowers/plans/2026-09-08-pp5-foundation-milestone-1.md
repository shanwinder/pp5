# PP5 Foundation Milestone 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a runnable PHP foundation on MAMP that proves routing, PDO/MySQL connectivity, secure login, server-side school assignment, school-context isolation, and a protected school dashboard.

**Architecture:** Use one `htdocs/index.php` front controller with FastRoute. Requests pass through lightweight middleware before controllers; business rules live in services and SQL lives in repositories. Authentication uses PHP sessions, and the active school is resolved from the database after login rather than selected or trusted from browser input.

**Tech Stack:** PHP 8.2-compatible, Composer, FastRoute 1.3.x, PDO, MySQL 8 on MAMP, MariaDB-compatible SQL, PHPUnit 11, PHP Session.

**Spec:** `docs/superpowers/specs/2026-09-08-pp5-technical-architecture-v1.2-design.md`

## Global Constraints

- Production target: InfinityFree free hosting.
- Do not use Laravel.
- No SSH, Node.js server, Redis, queue workers, cron jobs, database triggers or microservices.
- Local DB: MySQL 8 through MAMP; SQL must remain MariaDB-compatible.
- Normal users do not choose their school after login.
- `school_id` used for authorization comes from authenticated server-side context.
- A normal user may have at most one ACTIVE school membership in MVP.
- School-scoped reads/writes must enforce tenant isolation.
- Use PDO prepared statements.
- Use `password_hash()` / `password_verify()`.
- State-changing forms use CSRF protection.
- Database uses InnoDB and utf8mb4.
- Controllers stay thin; business rules live in services; SQL lives in repositories.
- Use TDD for each behavior.
- Commit after each independently passing task.

---

## File Map

```text
project-root/
├── docs/superpowers/
│   ├── specs/2026-09-08-pp5-technical-architecture-v1.2-design.md
│   └── plans/2026-09-08-pp5-foundation-milestone-1.md
├── htdocs/
│   ├── .htaccess
│   ├── index.php
│   ├── composer.json
│   ├── app/
│   │   ├── Application.php
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   └── DashboardController.php
│   │   ├── Http/
│   │   │   ├── Request.php
│   │   │   ├── Response.php
│   │   │   └── Session.php
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php
│   │   │   └── SchoolContextMiddleware.php
│   │   ├── Repositories/
│   │   │   ├── UserRepository.php
│   │   │   ├── SchoolRepository.php
│   │   │   └── SchoolMembershipRepository.php
│   │   ├── Services/AuthenticationService.php
│   │   └── Support/
│   │       ├── Csrf.php
│   │       ├── Database.php
│   │       └── View.php
│   ├── config/
│   │   ├── app.php
│   │   ├── database.php
│   │   ├── local.example.php
│   │   └── local.php
│   ├── routes/web.php
│   ├── views/
│   │   ├── auth/login.php
│   │   ├── dashboard/index.php
│   │   └── errors/
│   │       ├── 403.php
│   │       └── 404.php
│   └── vendor/
├── database/
│   ├── migrations/
│   └── seeds/
├── tests/
│   ├── bootstrap.php
│   ├── Unit/
│   └── Feature/
├── tools/
│   ├── migrate.php
│   └── create-system-admin.php
├── phpunit.xml
├── README.md
└── .gitignore
```

---

### Task 1: Bootstrap Composer, Front Controller and Routing

**Files:**
- Create: `htdocs/composer.json`
- Create: `htdocs/index.php`
- Create: `htdocs/.htaccess`
- Create: `htdocs/app/Application.php`
- Create: `htdocs/app/Http/Request.php`
- Create: `htdocs/app/Http/Response.php`
- Create: `htdocs/routes/web.php`
- Create: `tests/bootstrap.php`
- Create: `tests/Feature/FrontControllerTest.php`
- Create: `phpunit.xml`
- Create: `.gitignore`

**Interfaces:**
- Produces `App\Application::handle(Request $request): Response`
- Produces `Request::fromGlobals(): Request`
- Produces `Response::send(): void`
- Later tasks add protected handlers and middleware.

- [ ] **Step 1: Create Composer configuration**

`htdocs/composer.json`:

```json
{
  "name": "pp5/multi-school",
  "description": "Multi-school PP5 web application",
  "type": "project",
  "require": {
    "php": ">=8.2",
    "nikic/fast-route": "^1.3"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

Run:

```bash
cd htdocs
composer install
composer dump-autoload
cd ..
```

Expected: `htdocs/vendor/autoload.php` exists.

- [ ] **Step 2: Write the failing route test**

`tests/bootstrap.php`:

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/htdocs/vendor/autoload.php';
```

`tests/Feature/FrontControllerTest.php`:

```php
<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use PHPUnit\Framework\TestCase;

final class FrontControllerTest extends TestCase
{
    public function test_unknown_route_returns_404(): void
    {
        $app = new Application();
        $response = $app->handle(
            new Request('GET', '/missing', [], [], [])
        );

        self::assertSame(404, $response->status());
    }

    public function test_root_route_returns_200(): void
    {
        $app = new Application();
        $response = $app->handle(
            new Request('GET', '/', [], [], [])
        );

        self::assertSame(200, $response->status());
        self::assertSame('PP5', $response->body());
    }
}
```

`phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true">
  <testsuites>
    <testsuite name="PP5">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

Run:

```bash
htdocs/vendor/bin/phpunit tests/Feature/FrontControllerTest.php
```

Expected: FAIL because application classes do not exist.

- [ ] **Step 3: Implement Request**

`htdocs/app/Http/Request.php`:

```php
<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $post,
        private array $server
    ) {}

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_SERVER
        );
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }
}
```

- [ ] **Step 4: Implement Response**

`htdocs/app/Http/Response.php`:

```php
<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=UTF-8']
    ) {}

    public function status(): int { return $this->status; }
    public function body(): string { return $this->body; }

    public static function redirect(string $location): self
    {
        return new self('', 302, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
```

- [ ] **Step 5: Implement FastRoute dispatch**

`htdocs/routes/web.php`:

```php
<?php
declare(strict_types=1);

use FastRoute\RouteCollector;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/', static fn (): string => 'PP5');
};
```

`htdocs/app/Application.php`:

```php
<?php
declare(strict_types=1);

namespace App;

use App\Http\Request;
use App\Http\Response;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

final class Application
{
    public function handle(Request $request): Response
    {
        $routes = require dirname(__DIR__) . '/routes/web.php';
        $dispatcher = simpleDispatcher($routes);
        $routeInfo = $dispatcher->dispatch($request->method(), $request->path());

        if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
            return new Response('Not Found', 404);
        }

        if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return new Response('Method Not Allowed', 405);
        }

        $handler = $routeInfo[1];
        $result = $handler($routeInfo[2]);

        return $result instanceof Response
            ? $result
            : new Response((string) $result);
    }
}
```

`htdocs/index.php`:

```php
<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;

require __DIR__ . '/vendor/autoload.php';

session_start();

(new Application())
    ->handle(Request::fromGlobals())
    ->send();
```

- [ ] **Step 6: Protect private web paths**

`htdocs/.htaccess`:

```apache
Options -Indexes
RewriteEngine On

RewriteRule ^(?:app|config|routes|views|storage|vendor)(?:/|$) - [F,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

`.gitignore`:

```gitignore
htdocs/vendor/
htdocs/config/local.php
htdocs/storage/logs/*
htdocs/storage/temp/*
.DS_Store
```

- [ ] **Step 7: Verify and commit**

Run:

```bash
htdocs/vendor/bin/phpunit
```

Expected: 2 tests PASS.

Commit:

```bash
git add .
git commit -m "feat: bootstrap pp5 front controller"
```

---

### Task 2: Add Environment Config and PDO Connection

**Files:**
- Create: `htdocs/config/app.php`
- Create: `htdocs/config/database.php`
- Create: `htdocs/config/local.example.php`
- Local-only: `htdocs/config/local.php`
- Create: `htdocs/app/Support/Database.php`
- Create: `tests/Unit/DatabaseConfigTest.php`

**Interfaces:**
- Produces `Database::dsn(array $config): string`
- Produces `Database::connect(array $config): PDO`

- [ ] **Step 1: Write failing DSN test**

`tests/Unit/DatabaseConfigTest.php`:

```php
<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    public function test_mysql_dsn_uses_utf8mb4(): void
    {
        $config = [
            'host' => 'localhost',
            'port' => 8889,
            'database' => 'pp5_test',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8mb4',
        ];

        self::assertSame(
            'mysql:host=localhost;port=8889;dbname=pp5_test;charset=utf8mb4',
            Database::dsn($config)
        );
    }
}
```

Run:

```bash
htdocs/vendor/bin/phpunit tests/Unit/DatabaseConfigTest.php
```

Expected: FAIL.

- [ ] **Step 2: Implement PDO helper**

`htdocs/app/Support/Database.php`:

```php
<?php
declare(strict_types=1);

namespace App\Support;

use PDO;

final class Database
{
    public static function dsn(array $config): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
    }

    public static function connect(array $config): PDO
    {
        return new PDO(
            self::dsn($config),
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
```

- [ ] **Step 3: Add local/production config loading**

`htdocs/config/local.example.php`:

```php
<?php
declare(strict_types=1);

return [
    'database' => [
        'host' => 'localhost',
        'port' => 8889,
        'database' => 'pp5',
        'username' => 'root',
        'password' => 'root',
    ],
];
```

`htdocs/config/database.php`:

```php
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
```

`htdocs/config/app.php`:

```php
<?php
declare(strict_types=1);

return [
    'name' => 'ระบบ ปพ.5',
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
];
```

Copy `local.example.php` to `local.php` and set the actual MAMP port/credentials.

- [ ] **Step 4: Verify tests and actual MAMP DB connection**

Create `pp5` and `pp5_test` in phpMyAdmin.

Run:

```bash
htdocs/vendor/bin/phpunit tests/Unit/DatabaseConfigTest.php
php -r '$c=require "htdocs/config/database.php"; require "htdocs/vendor/autoload.php"; $pdo=App\Support\Database::connect($c); echo $pdo->query("SELECT 1")->fetchColumn();'
```

Expected: test PASS and command outputs `1`.

- [ ] **Step 5: Commit**

```bash
git add htdocs/config htdocs/app/Support tests/Unit/DatabaseConfigTest.php
git commit -m "feat: add mysql configuration and pdo connection"
```

---

### Task 3: Create Foundation Multi-School Schema

**Files:**
- Create migrations under `database/migrations/`
- Create: `tools/migrate.php`
- Create: `tests/Feature/FoundationSchemaTest.php`

**Interfaces:**
- Produces `schools`, `users`, `school_memberships`, `roles`, `permissions`, `role_permissions`, `user_role_assignments`, `audit_logs`.
- `school_memberships` is authoritative for the normal user's assigned school.

- [ ] **Step 1: Write failing schema test**

`tests/Feature/FoundationSchemaTest.php`:

```php
<?php
declare(strict_types=1);

use App\Support\Database;
use PHPUnit\Framework\TestCase;

final class FoundationSchemaTest extends TestCase
{
    public function test_foundation_tables_exist(): void
    {
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $pdo = Database::connect($config);

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ([
            'schools',
            'users',
            'school_memberships',
            'roles',
            'permissions',
            'role_permissions',
            'user_role_assignments',
            'audit_logs',
        ] as $table) {
            self::assertContains($table, $tables);
        }
    }
}
```

Expected initially: FAIL.

- [ ] **Step 2: Create schools/users/memberships migration**

Create `database/migrations/20260908_001_foundation_identity.sql`:

```sql
CREATE TABLE schools (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_code VARCHAR(30) NOT NULL,
    name_th VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schools_code (school_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE school_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    school_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_membership_user_school (user_id, school_id),
    KEY idx_membership_user_status (user_id, status),
    KEY idx_membership_school_status (school_id, status),
    CONSTRAINT fk_membership_user
      FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_membership_school
      FOREIGN KEY (school_id) REFERENCES schools(id)
      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_membership_creator
      FOREIGN KEY (created_by) REFERENCES users(id)
      ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

The “maximum one ACTIVE school membership” rule is enforced in PHP transaction logic to remain portable across MySQL/MariaDB.

- [ ] **Step 3: Create roles/permissions migration**

Create `database/migrations/20260908_002_foundation_authorization.sql`:

```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    name_th VARCHAR(120) NOT NULL,
    scope_type VARCHAR(20) NOT NULL DEFAULT 'SCHOOL',
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    name_th VARCHAR(190) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permission_role
      FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_role_permission_permission
      FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_role_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    school_id BIGINT UNSIGNED NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    assigned_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assignment_user_school (user_id, school_id, status),
    CONSTRAINT fk_assignment_user
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignment_school
      FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignment_role
      FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignment_assigner
      FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`academic_year_id` is intentionally not a foreign key yet; the academic-year subsystem is a later milestone.

- [ ] **Step 4: Create audit log migration**

Create `database/migrations/20260908_003_foundation_audit.sql`:

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_value LONGTEXT NULL,
    new_value LONGTEXT NULL,
    reason VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_school_time (school_id, created_at),
    KEY idx_audit_user_time (user_id, created_at),
    CONSTRAINT fk_audit_school
      FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE RESTRICT,
    CONSTRAINT fk_audit_user
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 5: Implement local migration runner**

`tools/migrate.php`:

```php
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
```

Note: MySQL/MariaDB DDL can implicitly commit; therefore the migration runner does not pretend the entire DDL file is transactionally rollback-safe.

- [ ] **Step 6: Run migration and schema test**

Run against a clean `pp5_test` database:

```bash
php tools/migrate.php --database=pp5_test
htdocs/vendor/bin/phpunit tests/Feature/FoundationSchemaTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database tools tests/Feature/FoundationSchemaTest.php
git commit -m "feat: add multi-school foundation schema"
```

---

### Task 4: Add Session and CSRF Foundations

**Files:**
- Create: `htdocs/app/Http/Session.php`
- Create: `htdocs/app/Support/Csrf.php`
- Modify: `htdocs/index.php`
- Create: `tests/Unit/CsrfTest.php`

**Interfaces:**
- `Session::get()`, `put()`, `forget()`, `clear()`, `regenerate()`
- `Csrf::token(Session $session): string`
- `Csrf::verify(Session $session, ?string $token): bool`

- [ ] **Step 1: Write failing CSRF test**

`tests/Unit/CsrfTest.php`:

```php
<?php
declare(strict_types=1);

use App\Http\Session;
use App\Support\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_token_round_trip(): void
    {
        $session = new Session();
        $csrf = new Csrf();

        $token = $csrf->token($session);

        self::assertTrue($csrf->verify($session, $token));
        self::assertFalse($csrf->verify($session, 'wrong'));
    }
}
```

Run and confirm FAIL.

- [ ] **Step 2: Implement Session**

`htdocs/app/Http/Session.php`:

```php
<?php
declare(strict_types=1);

namespace App\Http;

final class Session
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function clear(): void
    {
        $_SESSION = [];
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
```

- [ ] **Step 3: Implement CSRF**

`htdocs/app/Support/Csrf.php`:

```php
<?php
declare(strict_types=1);

namespace App\Support;

use App\Http\Session;

final class Csrf
{
    private const KEY = 'csrf_token';

    public function token(Session $session): string
    {
        $current = $session->get(self::KEY);

        if (is_string($current) && $current !== '') {
            return $current;
        }

        $token = bin2hex(random_bytes(32));
        $session->put(self::KEY, $token);

        return $token;
    }

    public function verify(Session $session, ?string $token): bool
    {
        $current = $session->get(self::KEY);

        return is_string($current)
            && is_string($token)
            && $token !== ''
            && hash_equals($current, $token);
    }
}
```

- [ ] **Step 4: Harden cookie settings**

Update `htdocs/index.php` before `session_start()`:

```php
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
    'path' => '/',
]);

session_start();
```

- [ ] **Step 5: Verify and commit**

```bash
htdocs/vendor/bin/phpunit tests/Unit/CsrfTest.php
git add htdocs/app/Http/Session.php htdocs/app/Support/Csrf.php htdocs/index.php tests/Unit/CsrfTest.php
git commit -m "feat: add session and csrf security"
```

---

### Task 5: Implement Authentication and Assigned-School Resolution

**Files:**
- Create: `htdocs/app/Repositories/UserRepository.php`
- Create: `htdocs/app/Repositories/SchoolMembershipRepository.php`
- Create: `htdocs/app/Services/AuthenticationService.php`
- Create: `tests/Feature/AuthenticationTest.php`

**Interfaces:**
- `UserRepository::findActiveByUsername(string $username): ?array`
- `SchoolMembershipRepository::findActiveForUser(int $userId): array`
- `AuthenticationService::attempt(string $username, string $password): array`
- Success result returns `user_id`, `school_id`, `school_membership_id`, `display_name`.
- No membership or multiple ACTIVE memberships is a denied state, not a school-selection flow.

- [ ] **Step 1: Write failing authentication tests**

Use `pp5_test` and wrap each test in a transaction. Cover:
1. correct password + one ACTIVE membership succeeds;
2. wrong password fails;
3. no ACTIVE membership fails;
4. two ACTIVE memberships fail.

Representative assertion:

```php
$result = $service->attempt('teacher1', 'correct-password');

self::assertSame($schoolId, $result['school_id']);
self::assertSame($membershipId, $result['school_membership_id']);
```

Run:

```bash
htdocs/vendor/bin/phpunit tests/Feature/AuthenticationTest.php
```

Expected: FAIL.

- [ ] **Step 2: Implement UserRepository**

`htdocs/app/Repositories/UserRepository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findActiveByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, password_hash, display_name
             FROM users
             WHERE username = :username
               AND status = "ACTIVE"
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function recordLogin(int $userId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);
    }
}
```

- [ ] **Step 3: Implement SchoolMembershipRepository**

`htdocs/app/Repositories/SchoolMembershipRepository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SchoolMembershipRepository
{
    public function __construct(private PDO $pdo) {}

    public function findActiveForUser(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT sm.id, sm.user_id, sm.school_id
             FROM school_memberships sm
             INNER JOIN schools s ON s.id = sm.school_id
             WHERE sm.user_id = :user_id
               AND sm.status = "ACTIVE"
               AND s.status = "ACTIVE"
             ORDER BY sm.id'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function isActiveMembership(
        int $membershipId,
        int $userId,
        int $schoolId
    ): bool {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM school_memberships sm
             INNER JOIN schools s ON s.id = sm.school_id
             WHERE sm.id = :membership_id
               AND sm.user_id = :user_id
               AND sm.school_id = :school_id
               AND sm.status = "ACTIVE"
               AND s.status = "ACTIVE"'
        );

        $statement->execute([
            'membership_id' => $membershipId,
            'user_id' => $userId,
            'school_id' => $schoolId,
        ]);

        return (int) $statement->fetchColumn() === 1;
    }
}
```

- [ ] **Step 4: Implement AuthenticationService**

`htdocs/app/Services/AuthenticationService.php`:

```php
<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SchoolMembershipRepository;
use App\Repositories\UserRepository;
use DomainException;

final class AuthenticationService
{
    public function __construct(
        private UserRepository $users,
        private SchoolMembershipRepository $memberships
    ) {}

    public function attempt(string $username, string $password): array
    {
        $user = $this->users->findActiveByUsername(trim($username));

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new DomainException('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        }

        $memberships = $this->memberships->findActiveForUser((int) $user['id']);

        if (count($memberships) === 0) {
            throw new DomainException(
                'บัญชีนี้ยังไม่ได้รับสิทธิ์เข้าใช้งานโรงเรียน กรุณาติดต่อผู้ดูแลระบบ'
            );
        }

        if (count($memberships) > 1) {
            throw new DomainException(
                'บัญชีนี้มีโรงเรียนที่ใช้งานมากกว่าหนึ่งแห่ง กรุณาติดต่อผู้ดูแลระบบ'
            );
        }

        $membership = $memberships[0];
        $this->users->recordLogin((int) $user['id']);

        return [
            'user_id' => (int) $user['id'],
            'school_id' => (int) $membership['school_id'],
            'school_membership_id' => (int) $membership['id'],
            'display_name' => (string) $user['display_name'],
        ];
    }
}
```

- [ ] **Step 5: Run tests and commit**

```bash
htdocs/vendor/bin/phpunit tests/Feature/AuthenticationTest.php
git add htdocs/app/Repositories htdocs/app/Services tests/Feature/AuthenticationTest.php
git commit -m "feat: authenticate user within assigned school"
```

Expected: all authentication cases PASS.

---

### Task 6: Add Login HTTP Flow and School-Context Middleware

**Files:**
- Create: `htdocs/app/Support/View.php`
- Create: `htdocs/app/Controllers/AuthController.php`
- Create: `htdocs/app/Middleware/AuthMiddleware.php`
- Create: `htdocs/app/Middleware/SchoolContextMiddleware.php`
- Create: `htdocs/app/Repositories/SchoolRepository.php`
- Create: `htdocs/views/auth/login.php`
- Create: `htdocs/views/errors/403.php`
- Modify: `htdocs/Application.php` path if needed: actual file is `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/SchoolIsolationTest.php`

**Interfaces:**
- `GET /login`
- `POST /login`
- `POST /logout`
- Protected routes execute `AuthMiddleware → SchoolContextMiddleware → handler`.
- Middleware rechecks the ACTIVE membership against DB, so suspension takes effect on the next protected request.

- [ ] **Step 1: Write failing school-isolation tests**

Create two schools A/B and a user assigned only to A. With the real test DB, verify:

```text
valid A session → allowed
session school_id manually changed to B → 403
membership changed to SUSPENDED → 403
school changed to SUSPENDED → 403
```

Run:

```bash
htdocs/vendor/bin/phpunit tests/Feature/SchoolIsolationTest.php
```

Expected: FAIL.

- [ ] **Step 2: Implement SchoolRepository**

`htdocs/app/Repositories/SchoolRepository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SchoolRepository
{
    public function __construct(private PDO $pdo) {}

    public function findActiveById(int $schoolId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, school_code, name_th
             FROM schools
             WHERE id = :id AND status = "ACTIVE"
             LIMIT 1'
        );
        $statement->execute(['id' => $schoolId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
```

- [ ] **Step 3: Implement middleware**

`htdocs/app/Middleware/AuthMiddleware.php`:

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;

final class AuthMiddleware
{
    public function __construct(private Session $session) {}

    public function handle(Request $request, callable $next): Response
    {
        if (!is_int($this->session->get('user_id'))) {
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
```

`htdocs/app/Middleware/SchoolContextMiddleware.php`:

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Repositories\SchoolMembershipRepository;
use App\Repositories\SchoolRepository;

final class SchoolContextMiddleware
{
    public function __construct(
        private Session $session,
        private SchoolMembershipRepository $memberships,
        private SchoolRepository $schools
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $userId = $this->session->get('user_id');
        $schoolId = $this->session->get('school_id');
        $membershipId = $this->session->get('school_membership_id');

        if (!is_int($userId) || !is_int($schoolId) || !is_int($membershipId)) {
            return Response::redirect('/login');
        }

        if (!$this->memberships->isActiveMembership(
            $membershipId,
            $userId,
            $schoolId
        )) {
            return new Response('Forbidden', 403);
        }

        if ($this->schools->findActiveById($schoolId) === null) {
            return new Response('Forbidden', 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Implement View helper and login form**

`htdocs/app/Support/View.php`:

```php
<?php
declare(strict_types=1);

namespace App\Support;

final class View
{
    public static function render(string $template, array $data = []): string
    {
        $path = dirname(__DIR__, 2) . '/views/' . $template . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}
```

`htdocs/views/auth/login.php`:

```php
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบ ปพ.5</title>
</head>
<body>
<main>
  <h1>เข้าสู่ระบบ ปพ.5</h1>

  <?php if (is_string($error) && $error !== ''): ?>
    <div role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" action="/login">
    <input type="hidden" name="_token"
      value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <label>ชื่อผู้ใช้
      <input type="text" name="username" autocomplete="username" required>
    </label>

    <label>รหัสผ่าน
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <button type="submit">เข้าสู่ระบบ</button>
  </form>
</main>
</body>
</html>
```

- [ ] **Step 5: Implement AuthController**

`htdocs/app/Controllers/AuthController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Services\AuthenticationService;
use App\Support\Csrf;
use App\Support\View;
use DomainException;

final class AuthController
{
    public function __construct(
        private AuthenticationService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function showLogin(): Response
    {
        return new Response(View::render('auth/login', [
            'csrfToken' => $this->csrf->token($this->session),
            'error' => null,
        ]));
    }

    public function login(Request $request): Response
    {
        if (!$this->csrf->verify($this->session, $request->post('_token'))) {
            return new Response('CSRF token mismatch', 419);
        }

        try {
            $result = $this->auth->attempt(
                (string) $request->post('username', ''),
                (string) $request->post('password', '')
            );
        } catch (DomainException $e) {
            return new Response(View::render('auth/login', [
                'csrfToken' => $this->csrf->token($this->session),
                'error' => $e->getMessage(),
            ]), 422);
        }

        $this->session->regenerate();
        $this->session->put('user_id', $result['user_id']);
        $this->session->put('school_id', $result['school_id']);
        $this->session->put(
            'school_membership_id',
            $result['school_membership_id']
        );
        $this->session->put('display_name', $result['display_name']);
        $this->session->put('last_activity', time());

        return Response::redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        if (!$this->csrf->verify($this->session, $request->post('_token'))) {
            return new Response('CSRF token mismatch', 419);
        }

        $this->session->clear();
        $this->session->regenerate();

        return Response::redirect('/login');
    }
}
```

- [ ] **Step 6: Wire dependencies explicitly in Application**

Construct these objects in `Application`:

```text
PDO
→ UserRepository
→ SchoolMembershipRepository
→ SchoolRepository
→ AuthenticationService
→ Session
→ Csrf
→ AuthController
→ AuthMiddleware
→ SchoolContextMiddleware
```

Register public routes:

```text
GET  /login
POST /login
POST /logout
```

and make protected route execution compose middleware explicitly.

Do not add a dependency-injection container in this milestone.

- [ ] **Step 7: Run isolation/auth tests and commit**

```bash
htdocs/vendor/bin/phpunit
git add htdocs/app htdocs/views htdocs/routes tests
git commit -m "feat: add secure login and school context middleware"
```

Expected: authentication/CSRF/isolation tests all PASS.

---

### Task 7: Add Protected Assigned-School Dashboard and Milestone Hardening

**Files:**
- Create: `htdocs/app/Controllers/DashboardController.php`
- Create: `htdocs/views/dashboard/index.php`
- Create: `htdocs/views/errors/403.php`
- Create: `htdocs/views/errors/404.php`
- Modify: `htdocs/app/Application.php`
- Modify: `htdocs/routes/web.php`
- Create: `tests/Feature/DashboardAccessTest.php`
- Create: `README.md`

**Interfaces:**
- `GET /dashboard` is protected.
- Dashboard displays the school resolved from DB/session.
- There is no school selector.
- Guest is redirected to `/login`.

- [ ] **Step 1: Write failing dashboard tests**

Verify:
- guest `/dashboard` → 302 `/login`;
- authenticated user → 200;
- assigned school name appears;
- HTML does not contain `name="school_id"` or “เลือกโรงเรียน”.

Run and confirm FAIL.

- [ ] **Step 2: Implement DashboardController**

`htdocs/app/Controllers/DashboardController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Http\Session;
use App\Repositories\SchoolRepository;
use App\Support\Csrf;
use App\Support\View;

final class DashboardController
{
    public function __construct(
        private Session $session,
        private SchoolRepository $schools,
        private Csrf $csrf
    ) {}

    public function index(): Response
    {
        $school = $this->schools->findActiveById(
            (int) $this->session->get('school_id')
        );

        if ($school === null) {
            return new Response('Forbidden', 403);
        }

        return new Response(View::render('dashboard/index', [
            'school' => $school,
            'displayName' => (string) $this->session->get('display_name', ''),
            'csrfToken' => $this->csrf->token($this->session),
        ]));
    }
}
```

- [ ] **Step 3: Create dashboard view**

`htdocs/views/dashboard/index.php`:

```php
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แดชบอร์ด — ระบบ ปพ.5</title>
</head>
<body>
<header>
  <strong><?= htmlspecialchars($school['name_th'], ENT_QUOTES, 'UTF-8') ?></strong>
  <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>

  <form method="post" action="/logout">
    <input type="hidden" name="_token"
      value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">ออกจากระบบ</button>
  </form>
</header>

<main>
  <h1>แดชบอร์ด</h1>
  <p>Foundation ของระบบพร้อมใช้งาน</p>
</main>
</body>
</html>
```

There must be no school dropdown or `school_id` form control.

- [ ] **Step 4: Register protected dashboard route**

Execution order:

```text
GET /dashboard
→ AuthMiddleware
→ SchoolContextMiddleware
→ DashboardController::index()
```

- [ ] **Step 5: Add friendly 403/404 views**

`htdocs/views/errors/403.php`:

```php
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>ไม่มีสิทธิ์เข้าถึง</title></head>
<body>
<main>
  <h1>ไม่มีสิทธิ์เข้าถึง</h1>
  <p>คุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้</p>
</main>
</body>
</html>
```

`htdocs/views/errors/404.php`:

```php
<!doctype html>
<html lang="th">
<head><meta charset="utf-8"><title>ไม่พบหน้า</title></head>
<body>
<main>
  <h1>ไม่พบหน้า</h1>
  <p>ไม่พบหน้าที่ต้องการ</p>
</main>
</body>
</html>
```

Make `Application` render the 404 view for unknown routes, and make tenant/security denial paths render the 403 view. Production responses must not expose SQL errors or stack traces.

- [ ] **Step 6: Run full automated suite**

```bash
htdocs/vendor/bin/phpunit
```

Expected: zero failures and zero errors.

- [ ] **Step 7: Perform MAMP smoke test**

On a clean local DB:

1. Run migrations.
2. Insert one school A and one school B.
3. Create one normal user.
4. Assign that user ACTIVE membership only to school A.
5. Login.
6. Confirm automatic redirect to school A dashboard.
7. Confirm no school chooser appears.
8. Manually tamper with session/request identifiers toward school B and confirm access is denied.
9. Suspend the school A membership in phpMyAdmin.
10. Refresh `/dashboard` and confirm protected access stops.
11. Reactivate membership and login again.
12. Logout and confirm `/dashboard` redirects to `/login`.
13. Directly request `/config/database.php`, `/app/Application.php`, `/views/dashboard/index.php`, and `/vendor/autoload.php`; expect HTTP 403.

- [ ] **Step 8: Document local setup**

`README.md` must state this exact sequence:

```text
1. Start MAMP Apache/MySQL.
2. Create databases `pp5` and `pp5_test`.
3. Copy `htdocs/config/local.example.php` to `htdocs/config/local.php`.
4. Enter MAMP MySQL port/credentials.
5. Run `cd htdocs && composer install && cd ..`.
6. Run `php tools/migrate.php`.
7. Run the seed SQL when role/permission seed is added.
8. Run `htdocs/vendor/bin/phpunit`.
9. Open the configured MAMP site.
```

- [ ] **Step 9: Final commit**

```bash
git add .
git commit -m "chore: verify pp5 multi-school foundation"
```

---

## Milestone Acceptance Criteria

Milestone 1 is complete only when:

- The application boots through one front controller.
- FastRoute works.
- MAMP MySQL connects through PDO.
- Foundation schema is reproducible from migration files.
- Authentication uses PHP password APIs.
- CSRF protection rejects bad tokens.
- Successful normal-user login derives school from ACTIVE DB membership.
- There is no school selector for normal users.
- No ACTIVE membership means no school-app access.
- Multiple ACTIVE memberships are treated as invalid account state.
- Session/request tampering cannot cross the school boundary.
- Suspending membership blocks the next protected request.
- Dashboard displays only the assigned school.
- Private directories cannot be read directly over HTTP.
- PHPUnit suite passes.
- No Laravel, Node, Redis, cron or DB trigger is required.

## Explicitly Deferred

Do not add these during Milestone 1:

- School Admin user-management UI
- academic years
- staff profiles
- classrooms
- students/DMC import
- subject catalog
- gradebook
- attendance
- evaluations
- activities
- annual results/promotion
- reports/PDF
- HTMX data-entry flows
- advanced role scopes

The next plan should be **School Administration Milestone 2**: create school lifecycle, appoint School Admin, School Admin creates/suspends users, assigns memberships and roles, and enforces school-scoped administration.
