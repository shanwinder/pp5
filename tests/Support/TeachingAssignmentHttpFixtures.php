<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Support\Csrf;
use App\Support\Database;

trait TeachingAssignmentHttpFixtures
{
    private TeachingManagementHttpPDO $pdo;
    private Application $app;
    private array $f;
    private array $users;
    private const PATH = '/academic/teaching-assignments';
    private const HOSTILE = '<script>alert("teaching-xss")</script>';

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new TeachingManagementHttpPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction();
        $this->f = []; $this->users = [];
        $grade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        foreach (['A', 'B'] as $key) {
            $this->f['school' . $key] = $this->insert('schools', ['school_code' => 'teaching-http-' . $key, 'name_th' => $key === 'B' ? 'FOREIGN_SECRET_SCHOOL' : 'โรงเรียนทดสอบ']);
            $this->f['subject' . $key] = $this->insert('subjects', ['school_id' => $this->f['school' . $key], 'code' => $key === 'B' ? 'FOREIGN_SECRET_SUBJECT' : 'SCI', 'name_th' => 'วิชาทดสอบ']);
        }
        foreach (['A' => [2569, 'DRAFT'], 'Next' => [2570, 'ACTIVE'], 'Closed' => [2568, 'CLOSED'], 'B' => [2699, 'DRAFT']] as $key => [$year, $status]) {
            $schoolKey = $key === 'B' ? 'B' : 'A'; $school = $this->f['school' . $schoolKey];
            $yearId = $this->f['year' . $key] = $this->insert('academic_years', ['school_id' => $school, 'year_be' => $year, 'status' => $status]);
            $room = $this->f['room' . $key] = $this->insert('classrooms', ['school_id' => $school, 'academic_year_id' => $yearId, 'grade_level_id' => $grade,
                'code' => $key === 'B' ? 'FOREIGN_SECRET_ROOM' : 'ROOM_' . $key, 'name_th' => 'ห้องทดสอบ']);
            $values = ['school_id' => $school, 'academic_year_id' => $yearId, 'classroom_id' => $room, 'subject_id' => $this->f['subject' . $schoolKey], 'term_no' => 1];
            $this->f['offering' . $key] = $this->insert('subject_offerings', $values);
            if ($key === 'A') { $this->f['offeringInactive'] = $this->insert('subject_offerings', array_replace($values, ['term_no' => 2, 'status' => 'INACTIVE'])); }
        }
        foreach (['SCHOOL_ADMIN', 'ACADEMIC_ADMIN', 'SUBJECT_TEACHER', 'EXECUTIVE', 'HOMEROOM_TEACHER', 'VIEWER', 'SYSTEM_ADMIN'] as $role) {
            $school = $role === 'SYSTEM_ADMIN' ? null : $this->f['schoolA'];
            $this->users[$role] = $this->fixtureUser($role, $role, $school);
        }
        $this->f['teacher'] = $this->users['SUBJECT_TEACHER']['user'];
        $this->f['teacherAssignment'] = $this->users['SUBJECT_TEACHER']['assignment'];
        $foreign = $this->fixtureUser('FOREIGN_SECRET_TEACHER', 'SUBJECT_TEACHER', $this->f['schoolB']);
        $this->f['foreignUser'] = $foreign['user']; $this->f['foreignAssignment'] = $foreign['assignment'];
        $inactiveMembership = $this->fixtureUser('INACTIVE_MEMBERSHIP', 'SUBJECT_TEACHER', $this->f['schoolA']);
        $this->pdo->prepare("UPDATE school_memberships SET status='SUSPENDED' WHERE id=?")->execute([$inactiveMembership['membership']]);
        $inactiveAssignment = $this->fixtureUser('INACTIVE_ASSIGNMENT', 'SUBJECT_TEACHER', $this->f['schoolA']);
        $this->pdo->prepare("UPDATE user_role_assignments SET status='INACTIVE' WHERE id=?")->execute([$inactiveAssignment['assignment']]);
        foreach (['A', 'Next', 'Closed', 'B'] as $key) {
            $this->f['scope' . $key] = $this->insert('permission_scopes', ['school_id' => $this->f[$key === 'B' ? 'schoolB' : 'schoolA'],
                'academic_year_id' => $this->f['year' . $key], 'subject_offering_id' => $this->f['offering' . $key],
                'user_role_assignment_id' => $key === 'B' ? $foreign['assignment'] : $this->f['teacherAssignment'],
                'assigned_by' => $this->users['SCHOOL_ADMIN']['user'], 'status' => $key === 'Closed' ? 'INACTIVE' : 'ACTIVE']);
        }
        // A second eligible assignment lets HTTP create exercise a new pair.
        $second = $this->fixtureUser('SECOND_TEACHER', 'SUBJECT_TEACHER', $this->f['schoolA']);
        $this->f['secondTeacher'] = $second['user']; $this->f['secondAssignment'] = $second['assignment'];
        $this->app = new Application($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null;
            while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
        }
        $_SESSION = [];
    }

    private function fixtureUser(string $name, string $role, ?int $school): array
    {
        $user = $this->insert('users', ['username' => 'teaching-http-' . $name, 'password_hash' => 'private-fixture-hash', 'display_name' => $name]);
        $membership = $school === null ? null : $this->insert('school_memberships', ['school_id' => $school, 'user_id' => $user]);
        $roleId = $this->rows('SELECT id FROM roles WHERE code=?', [$role])[0]['id'];
        $assignment = $this->insert('user_role_assignments', ['user_id' => $user, 'school_id' => $school, 'role_id' => $roleId]);
        return compact('user', 'membership', 'assignment');
    }
    private function login(string $role = 'SCHOOL_ADMIN'): void
    {
        $_SESSION = ['user_id' => $this->users[$role]['user'], 'context_type' => $role === 'SYSTEM_ADMIN' ? 'SYSTEM' : 'SCHOOL'];
        if ($role !== 'SYSTEM_ADMIN') {
            $_SESSION['school_id'] = $this->f['schoolA'];
            $_SESSION['school_membership_id'] = $this->users[$role]['membership'];
        }
        $this->token();
    }
    private function token(): string { return (new Csrf())->token(new Session()); }
    private function payload(array $overrides = []): array
    {
        return array_replace(['_token' => $this->token(), 'user_role_assignment_id' => (string) $this->f['secondAssignment'],
            'subject_offering_id' => (string) $this->f['offeringA'], 'status' => 'INACTIVE'], $overrides);
    }
    private function path(string $path): string { return str_replace('{id}', (string) $this->f['scopeA'], $path); }
    private function request(string $method, string $path = self::PATH, array $post = [], array $query = [], array $server = []): Response
    {
        return $this->app->handle(new Request($method, $path, $query, $post, $server));
    }
    private function rows(string $sql, array $params = []): array { $q = $this->pdo->prepare($sql); $q->execute($params); return $q->fetchAll(); }
    private function row(string $table, int $id): array { return $this->rows("SELECT * FROM {$table} WHERE id=?", [$id])[0]; }
    private function insert(string $table, array $values): int
    {
        $q = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')');
        $q->execute(array_values($values)); return (int) $this->pdo->lastInsertId();
    }
    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['permission_scopes', 'audit_logs', 'gradebook_components', 'gradebook_scores'] as $table) {
            $snapshot[$table] = $this->rows("SELECT * FROM {$table} ORDER BY id");
        }
        return $snapshot;
    }
    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument(); $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML('<?xml encoding="UTF-8">' . $html); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        return new DOMXPath($document);
    }
    private function optionIds(DOMXPath $xpath, string $field): array
    {
        return array_map(static fn (DOMNode $n): string => $n->nodeValue,
            iterator_to_array($xpath->query('//select[@name="' . $field . '"]/option[@value!=""]/@value')));
    }
    private function assertSafe(Response $response): void
    {
        foreach (['FOREIGN_SECRET', 'SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/',
            'htdocs/app/', 'uq_', 'fk_', 'private-db-details', 'private-fixture-hash', 'password_hash', 'national_id'] as $secret) {
            self::assertStringNotContainsString($secret, $response->body());
        }
    }
    private function assertForms(Response $response): void
    {
        $xpath = $this->xpath($response->body());
        foreach (['school_id', 'teacher_id', 'user_id', 'actor', 'actor_user_id', 'context_type', 'permission', 'scope'] as $field) {
            self::assertSame(0, $xpath->query('//*[@name="' . $field . '"]')->length);
        }
        foreach ($xpath->query('//form[@method="post"]') as $form) {
            self::assertSame($this->token(), $xpath->evaluate('string(.//input[@name="_token"]/@value)', $form));
        }
        self::assertSame(0, $xpath->query('//form[contains(@action,"delete")]')->length);
    }
}

/** Retain real transaction/rollback behavior while service commits use fixture savepoints. */
final class TeachingManagementHttpPDO extends PDO
{
    public int $depth = 0;
    public int $writeAttempts = 0;
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;
    public function beginTransaction(): bool
    {
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT teaching_management_' . $this->depth) !== false;
        ++$this->depth; return $ok;
    }
    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT teaching_management_' . ($this->depth - 1)) !== false;
        --$this->depth; return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT teaching_management_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT teaching_management_' . ($this->depth - 1)); }
        --$this->depth; return $ok;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE)\b/i', $query)) { ++$this->writeAttempts; }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null; $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-db-details /Applications/MAMP/htdocs/app/secret.php');
        }
        return parent::prepare($query, $options);
    }
}
