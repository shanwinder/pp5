<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Support\Csrf;
use App\Support\Database;
use App\Repositories\{GradebookComponentRepository, SchoolRepository, AcademicYearRepository, SubjectOfferingRepository, AuditLogRepository};
use App\Services\GradebookComponentService;

trait GradebookComponentFixtures
{
    private ComponentTestPDO $pdo;
    private array $f;
    private array $users;

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = require dirname(__DIR__, 2) . '/htdocs/config/database.php';
        $config['database'] = 'pp5_test';
        $this->pdo = new ComponentTestPDO(Database::dsn($config), $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->beginTransaction(); $this->f = []; $this->users = [];
        $grade = (int) $this->pdo->query("SELECT id FROM grade_levels WHERE code='P1'")->fetchColumn();
        foreach (['A', 'B'] as $key) {
            $school = $this->f['school' . $key] = $this->insert('schools', ['school_code' => 'component-test-' . $key, 'name_th' => $key === 'B' ? 'FOREIGN_SECRET_SCHOOL' : 'โรงเรียนทดสอบ']);
            $this->f['subject' . $key] = $this->insert('subjects', ['school_id' => $school, 'code' => 'SCI', 'name_th' => $key === 'B' ? 'FOREIGN_SECRET_SUBJECT' : 'วิทยาศาสตร์']);
        }
        foreach (['A' => [2569, 'DRAFT'], 'Next' => [2570, 'ACTIVE'], 'Closed' => [2568, 'CLOSED'], 'B' => [2569, 'DRAFT']] as $key => [$year, $status]) {
            $schoolKey = $key === 'B' ? 'B' : 'A'; $school = $this->f['school' . $schoolKey];
            $yearId = $this->f['year' . $key] = $this->insert('academic_years', ['school_id' => $school, 'year_be' => $year, 'status' => $status]);
            $room = $this->f['room' . $key] = $this->insert('classrooms', ['school_id' => $school, 'academic_year_id' => $yearId, 'grade_level_id' => $grade, 'code' => 'ROOM_' . $key, 'name_th' => 'ห้องทดสอบ']);
            $offering = ['school_id' => $school, 'academic_year_id' => $yearId, 'classroom_id' => $room, 'subject_id' => $this->f['subject' . $schoolKey], 'term_no' => 1];
            $this->f['offering' . $key] = $this->insert('subject_offerings', $offering);
            if ($key === 'A') {
                $this->f['offeringOther'] = $this->insert('subject_offerings', array_replace($offering, ['term_no' => 2]));
                $this->f['offeringInactive'] = $this->insert('subject_offerings', array_replace($offering, ['term_no' => 3, 'status' => 'INACTIVE']));
            }
        }
        foreach (['SCHOOL_ADMIN','ACADEMIC_ADMIN','SUBJECT_TEACHER','EXECUTIVE','HOMEROOM_TEACHER','VIEWER','SYSTEM_ADMIN','FOREIGN'] as $role) {
            $user = $this->insert('users', ['username' => 'component-test-' . $role, 'display_name' => $role, 'password_hash' => 'private-fixture-hash']);
            $school = $role === 'SYSTEM_ADMIN' ? null : $this->f[$role === 'FOREIGN' ? 'schoolB' : 'schoolA'];
            $membership = $school === null ? null : $this->insert('school_memberships', ['school_id' => $school, 'user_id' => $user]);
            $roleId = $this->rows('SELECT id FROM roles WHERE code=?', [$role === 'FOREIGN' ? 'SCHOOL_ADMIN' : $role])[0]['id'];
            $assignment = $this->insert('user_role_assignments', ['school_id' => $school, 'user_id' => $user, 'role_id' => $roleId]);
            $this->users[$role] = compact('user', 'membership', 'assignment');
        }
        foreach (['A','Next','Closed','B','Other','Inactive'] as $key) {
            $school = $this->f[$key === 'B' ? 'schoolB' : 'schoolA'];
            $year = $this->f['year' . (in_array($key, ['Other','Inactive'], true) ? 'A' : $key)];
            $this->f['component' . $key] = $this->insert('gradebook_components', ['school_id' => $school, 'academic_year_id' => $year,
                'subject_offering_id' => $this->f['offering' . $key], 'code' => $key === 'B' ? 'FOREIGN_SECRET_COMPONENT' : 'EXAM',
                'name_th' => 'สอบ', 'max_score' => '20.00', 'sort_order' => 10]);
        }
        $this->f['inactiveComponent'] = $this->insert('gradebook_components', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'],
            'subject_offering_id' => $this->f['offeringA'], 'code' => 'OLD', 'name_th' => 'ประวัติ', 'max_score' => '10.00', 'sort_order' => 1, 'status' => 'INACTIVE']);
        $student = $this->insert('students', ['school_id' => $this->f['schoolA'], 'student_code' => 'fixture', 'prefix_th' => 'ด.ช.', 'first_name_th' => 'ทดสอบ', 'last_name_th' => 'ประวัติ']);
        $this->f['enrollment'] = $this->insert('student_enrollments', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'], 'student_id' => $student, 'grade_level_id' => $grade]);
        $this->pdo->queries = [];
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->failPrepare = null; $this->pdo->beforeLock = null;
            while ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
        }
        $_SESSION = [];
    }

    private function service(): GradebookComponentService
    {
        return new GradebookComponentService($this->pdo, new SchoolRepository($this->pdo), new AcademicYearRepository($this->pdo),
            new SubjectOfferingRepository($this->pdo), new GradebookComponentRepository($this->pdo), new AuditLogRepository($this->pdo));
    }
    private function create(array $values = [], string $key = 'A'): int
    {
        $v = array_replace(['code' => 'NEW', 'name_th' => 'งาน', 'max_score' => '20', 'sort_order' => '0'], $values);
        return $this->service()->createComponent($this->f['schoolA'], $this->users['SCHOOL_ADMIN']['user'], $this->f['offering' . $key],
            $v['code'], $v['name_th'], $v['max_score'], $v['sort_order'], '127.0.0.1');
    }
    private function update(array $values = [], string $key = 'A'): void
    {
        $v = array_replace(['code' => 'EXAM', 'name_th' => 'สอบ', 'max_score' => '20', 'sort_order' => '10'], $values);
        $this->service()->updateComponent($this->f['schoolA'], $this->users['SCHOOL_ADMIN']['user'], $this->f['offering' . $key], $this->f['component' . $key],
            $v['code'], $v['name_th'], $v['max_score'], $v['sort_order']);
    }
    private function componentStatus(string $status, string $key = 'A'): void
    {
        $this->service()->changeStatus($this->f['schoolA'], $this->users['SCHOOL_ADMIN']['user'], $this->f['offering' . $key], $this->f['component' . $key], $status);
    }
    private function score(?string $score, string $component = 'componentA'): int
    {
        return $this->insert('gradebook_scores', ['school_id' => $this->f['schoolA'], 'academic_year_id' => $this->f['yearA'], 'subject_offering_id' => $this->f['offeringA'],
            'component_id' => $this->f[$component], 'enrollment_id' => $this->f['enrollment'], 'score' => $score, 'updated_by' => $this->users['SCHOOL_ADMIN']['user']]);
    }
    private function login(string $role = 'SCHOOL_ADMIN'): void
    {
        $_SESSION = ['user_id' => $this->users[$role]['user'], 'context_type' => $role === 'SYSTEM_ADMIN' ? 'SYSTEM' : 'SCHOOL'];
        if ($role !== 'SYSTEM_ADMIN') {
            $_SESSION['school_id'] = $this->f['schoolA']; $_SESSION['school_membership_id'] = $this->users[$role]['membership'];
        }
        $this->token();
    }
    private function token(): string { return (new Csrf())->token(new Session()); }
    private function path(string $action = 'setup', string $key = 'A'): string
    {
        $base = '/gradebook/' . $this->f['offering' . $key];
        return $base . match ($action) {
            'setup' => '/setup', 'create' => '/components', 'update' => '/components/' . $this->f['component' . $key],
            'status' => '/components/' . $this->f['component' . $key] . '/status',
        };
    }
    private function payload(array $values = []): array
    {
        return array_replace(['_token' => $this->token(), 'code' => 'NEW', 'name_th' => 'งาน', 'max_score' => '01.50', 'sort_order' => '0', 'status' => 'INACTIVE'], $values);
    }
    private function request(string $method, string $path, array $post = [], array $query = []): Response
    {
        return (new Application($this->pdo))->handle(new Request($method, $path, $query, $post, ['REMOTE_ADDR' => '127.0.0.1']));
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
        $state = [];
        foreach (['gradebook_components', 'gradebook_scores', 'permission_scopes', 'audit_logs'] as $table) { $state[$table] = $this->rows("SELECT * FROM {$table} ORDER BY id"); }
        return $state;
    }
    private function assertRejected(callable $action): void
    {
        $before = $this->snapshot();
        try { $action(); self::fail('Expected DomainException'); }
        catch (DomainException $e) { $this->assertSafe($e->getMessage()); self::assertMatchesRegularExpression('/[ก-๙]/u', $e->getMessage()); }
        self::assertSame($before, $this->snapshot());
    }
    private function assertSafe(string $text): void
    {
        foreach (['FOREIGN_SECRET', 'SQLSTATE', 'PDOException', 'SELECT ', 'INSERT ', 'UPDATE ', 'Stack trace', '/Applications/MAMP/',
            'htdocs/app/', 'uq_', 'fk_', 'private-db-details', 'private-fixture-hash', 'password_hash', 'national_id'] as $secret) { self::assertStringNotContainsString($secret, $text); }
    }
    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument(); $old = libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="UTF-8">' . $html); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
        return new DOMXPath($doc);
    }
}

/** Real savepoints preserve fixture isolation while exercising the service transaction boundary. */
final class ComponentTestPDO extends PDO
{
    public int $depth = 0;
    public array $queries = [];
    public ?string $failPrepare = null;
    public bool $failureTriggered = false;
    public ?Closure $beforeLock = null;
    public function beginTransaction(): bool
    {
        $ok = $this->depth === 0 ? parent::beginTransaction() : $this->exec('SAVEPOINT component_' . $this->depth) !== false;
        ++$this->depth; return $ok;
    }
    public function commit(): bool
    {
        $ok = $this->depth === 1 ? parent::commit() : $this->exec('RELEASE SAVEPOINT component_' . ($this->depth - 1)) !== false;
        --$this->depth; return $ok;
    }
    public function rollBack(): bool
    {
        if ($this->depth === 1) { $ok = parent::rollBack(); }
        else { $ok = $this->exec('ROLLBACK TO SAVEPOINT component_' . ($this->depth - 1)) !== false; $this->exec('RELEASE SAVEPOINT component_' . ($this->depth - 1)); }
        --$this->depth; return $ok;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        if ($this->beforeLock !== null && str_contains($query, 'FOR UPDATE')) { ($this->beforeLock)($query); }
        if ($this->failPrepare !== null && str_contains($query, $this->failPrepare)) {
            $this->failPrepare = null; $this->failureTriggered = true;
            throw new PDOException('SQLSTATE private-db-details /Applications/MAMP/htdocs/app/secret.php');
        }
        return parent::prepare($query, $options);
    }
}
