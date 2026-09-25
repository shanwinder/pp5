<?php
declare(strict_types=1);

use App\Support\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__).'/Support/GradebookReadFixtures.php';

/** One product contract, exercised through real Application responses and rolled-back MySQL fixtures. */
final class UiCrossScreenContractTest extends TestCase
{
    use GradebookReadFixtures;

    public static function screens(): iterable
    {
        yield 'login' => ['GUEST', '/login', null];
        yield 'dashboard' => ['SCHOOL_ADMIN', '/dashboard', '/dashboard'];
        foreach (['/system/schools', '/system/schools/create'] as $path) {
            yield $path => ['SYSTEM_ADMIN', $path, $path];
        }
        foreach (['/admin/users', '/admin/users/create', '/admin/users/{user}/edit'] as $path) {
            yield $path => ['SCHOOL_ADMIN', $path, '/admin/users'];
        }
        foreach (['years'=>'yearA', 'classrooms'=>'roomA', 'subjects'=>'subjectA', 'offerings'=>'offeringA'] as $area=>$key) {
            foreach (['', '/create', '/{'.$key.'}/edit'] as $suffix) {
                yield $area.$suffix => ['SCHOOL_ADMIN', '/academic/'.$area.$suffix, '/academic/'.$area];
            }
        }
        foreach (['', '/create', '/{student}', '/{student}/edit'] as $suffix) {
            yield 'students'.$suffix => ['SCHOOL_ADMIN', '/students'.$suffix, '/students'];
        }
        foreach (['', '/create', '/{enrollment_current}/edit'] as $suffix) {
            yield 'enrollments'.$suffix => ['SCHOOL_ADMIN', '/academic/enrollments'.$suffix, '/academic/enrollments'];
        }
        yield 'assignments' => ['SCHOOL_ADMIN', '/academic/teaching-assignments', '/academic/teaching-assignments'];
        yield 'upload' => ['SCHOOL_ADMIN', '/academic/student-import', '/academic/student-import'];
        yield 'preview' => ['SCHOOL_ADMIN', '/academic/student-import/{batch}', '/academic/student-import'];
        foreach (['/gradebooks', '/gradebook/{offeringA}', '/gradebook/{offeringA}/setup'] as $path) {
            yield $path => ['SCHOOL_ADMIN', $path, '/gradebooks'];
        }
        yield 'read-only grid' => ['EXECUTIVE', '/gradebook/{offeringA}', '/gradebooks'];
        yield '403' => ['VIEWER', '/admin/users', null, 403];
        yield '404' => ['VIEWER', '/missing/<img onerror="bad">', null, 404];
    }

    #[DataProvider('screens')]
    public function testFullPageContract(string $role, string $path, ?string $active, int $status = 200): void
    {
        if ($role !== 'GUEST') { $this->login($role); }
        $hostile = str_repeat('ชื่อไทยยาว', 3).'\"><img src=x onerror="bad"><script>bad</script>';
        $_SESSION['display_name'] = $hostile;
        foreach (['schools'=>'schoolA', 'classrooms'=>'roomA', 'subjects'=>'subjectA', 'gradebook_components'=>'componentA'] as $table=>$key) {
            $this->pdo->prepare("UPDATE {$table} SET name_th=? WHERE id=?")->execute([$hostile, $this->f[$key]]);
        }
        $student = $this->row('student_enrollments', $this->f['enrollment_current'])['student_id'];
        $this->pdo->prepare('UPDATE students SET first_name_th=? WHERE id=?')->execute([$hostile, $student]);
        $this->pdo->prepare('UPDATE users SET display_name=? WHERE id=?')->execute([$hostile, $this->users['SUBJECT_TEACHER']['user']]);
        $ids = $this->f + ['student'=>$student, 'user'=>$this->users['VIEWER']['user']];
        if (str_contains($path, '{batch}')) { $ids['batch'] = $this->importPreview($hostile); }
        foreach ($ids as $key=>$id) { $path = str_replace('{'.$key.'}', (string)$id, $path); }
        $grade = $this->row('classrooms', $this->f['roomA'])['grade_level_id'];
        $r = $this->request('GET', $path, [], ['academic_year_id'=>$this->f['yearA'], 'grade_level_id'=>$grade]);
        self::assertSame($status, $r->status(), $path);
        $html = $r->body(); $x = $this->xpath($html);

        // Check source too: DOMDocument repairs nested documents and could conceal a regression.
        self::assertSame(1, preg_match_all('/<!doctype\s+html\s*>/i', $html));
        foreach (['html', 'head', 'body', 'main', 'h1'] as $tag) {
            self::assertSame(1, preg_match_all('/<'.$tag.'(?:\s|>)/i', $html), $path.' '.$tag);
            self::assertSame(1, $x->query('//'.$tag)->length, $tag);
        }
        self::assertSame('th', $x->evaluate('string(/html/@lang)'));
        self::assertSame(1, $x->query('//head/meta[@name="viewport" and @content="width=device-width, initial-scale=1"]')->length);
        self::assertSame(['/assets/vendor/bootstrap-5.3.8.min.css', '/assets/app.css'],
            array_map(static fn($n)=>$n->nodeValue, iterator_to_array($x->query('//link[@rel="stylesheet"]/@href'))));
        self::assertSame($active === null ? 0 : 1, $x->query('//script[@src="/assets/app.js" and @defer]')->length);

        $issues = []; $seen = [];
        foreach ($x->query('//*[@id]') as $node) {
            $id = $node->getAttribute('id');
            if ($id === '' || isset($seen[$id])) { $issues[] = 'duplicate/empty ID: '.$id; }
            $seen[$id] = $node;
        }
        foreach ($x->query('//*[@aria-labelledby or @aria-describedby or @aria-controls]') as $node) {
            foreach (['aria-labelledby', 'aria-describedby', 'aria-controls'] as $attr) {
                foreach (preg_split('/\s+/', trim($node->getAttribute($attr)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    if (!isset($seen[$id])) { $issues[] = 'missing '.$attr.' target: '.$id; }
                }
            }
        }
        foreach ($x->query('//input[not(@type="hidden")]|//select|//textarea') as $control) {
            $id = $control->getAttribute('id'); $name = trim($control->getAttribute('aria-label'));
            foreach ($x->query('//label') as $label) {
                if ($id !== '' && $label->getAttribute('for') === $id) { $name .= trim($label->textContent); }
            }
            foreach (preg_split('/\s+/', trim($control->getAttribute('aria-labelledby')), -1, PREG_SPLIT_NO_EMPTY) as $ref) {
                $name .= trim($seen[$ref]->textContent ?? '');
            }
            if ($id === '' || $name === '') { $issues[] = 'unnamed control: '.$control->getAttribute('name'); }
            if ($control->getAttribute('type') === 'password' && $control->hasAttribute('value')) { $issues[] = 'password value'; }
        }
        foreach ($x->query('//*') as $node) {
            foreach ($node->attributes as $attr) {
                if (preg_match('/^on[a-z]+$/i', $attr->name)) { $issues[] = 'event handler '.$attr->name; }
                if (in_array($attr->name, ['href','src','action','formaction'], true)
                    && preg_match('/^javascript:/i', preg_replace('/[\x00-\x20]/', '', $attr->value))) { $issues[] = 'script URL'; }
            }
        }
        foreach ($x->query('//script|//link[@rel="stylesheet" or @rel="preconnect" or @rel="preload"]') as $asset) {
            $url = $asset->getAttribute($asset->tagName === 'script' ? 'src' : 'href');
            if (!str_starts_with($url, '/assets/') || str_starts_with($url, '//')) { $issues[] = 'non-local asset: '.$url; }
        }
        foreach ($x->query('//table') as $table) {
            if ($x->query('ancestor::*[contains(@class,"pp5-table-scroll") and @role="region" and @aria-label and @tabindex="0"]', $table)->length !== 1) { $issues[] = 'unnamed/unreachable table region'; }
            if ($x->query('.//thead/th|.//thead//th[not(@scope="col")]|.//tbody//th[not(@scope="row")]', $table)->length) { $issues[] = 'table header scope'; }
        }
        foreach ($x->query('//*[contains(@class,"pp5-alert--danger")]') as $alert) {
            if ($alert->getAttribute('role') !== 'alert') { $issues[] = 'unannounced error'; }
        }
        if ($x->query('//*[contains(@class,"pp5-alert--success") and @role="alert"]')->length) { $issues[] = 'routine success is assertive'; }
        foreach ($x->query('//*[contains(@class,"pp5-badge")]') as $badge) {
            if (trim($badge->textContent) === '') { $issues[] = 'color-only badge'; }
        }
        foreach ($x->query('//form[translate(@method,"POST","post")="post"]') as $form) {
            if ($x->evaluate('string(.//input[@name="_token"]/@value)', $form) !== $this->token()) { $issues[] = 'CSRF token mismatch'; }
        }
        foreach ($x->query('//form[@data-confirm]') as $form) {
            if (!preg_match('~^/(system/schools/\d+/status|students/\d+/status|academic/enrollments/\d+/status|academic/student-import/\d+/(apply|cancel))$~', $form->getAttribute('action'))) { $issues[] = 'unexpected confirmation'; }
            if (str_contains($form->getAttribute('data-confirm'), $hostile) || trim($form->getAttribute('data-confirm')) === '') { $issues[] = 'non-static confirmation'; }
        }
        self::assertSame([], $issues, $path);
        if ($active !== null) {
            self::assertSame(1, $x->query('//a[@class="pp5-skip-link" and @href="#main-content"]')->length);
            self::assertSame(1, $x->query('//main[@id="main-content" and @tabindex="-1"]')->length);
            self::assertSame(1, $x->query('//nav[@aria-label="เมนูหลัก"]')->length);
            self::assertSame([$active], array_map(static fn($n)=>$n->nodeValue,
                iterator_to_array($x->query('//nav[@aria-label="เมนูหลัก"]//a[@aria-current="page"]/@href'))));
            self::assertSame(0, $x->query('//nav//section[not(.//a)]')->length);
            self::assertSame(1, $x->query('//button[@data-nav-toggle and @aria-expanded="true" and @aria-controls="app-navigation" and normalize-space(.)!=""]')->length);
            self::assertSame(1, $x->query('//*[@id="app-navigation" and not(@hidden)]')->length);
            self::assertSame(1, $x->query('//form[@method="post" and @action="/logout"]')->length);
            $links = array_map(static fn($n)=>$n->nodeValue, iterator_to_array($x->query('//nav[@aria-label="เมนูหลัก"]//a/@href')));
            if ($role === 'SYSTEM_ADMIN') { self::assertSame(['/system/schools', '/system/schools/create'], $links); }
            elseif ($role === 'EXECUTIVE') { self::assertSame(['/dashboard', '/gradebooks'], $links); }
            else { self::assertSame(['/dashboard','/students','/academic/enrollments','/academic/student-import','/academic/years','/academic/classrooms','/academic/subjects','/academic/offerings','/academic/teaching-assignments','/gradebooks','/admin/users'], $links); }
        }
        // Approved edit input is the only allowed raw-ID location, even on the edit response.
        foreach ($x->query('//input[@name="national_id"]') as $input) { $input->removeAttribute('value'); }
        $safe = $x->document->saveHTML();
        foreach ([self::NATIONAL_MARKER, 'SQLSTATE', 'Stack trace:', '/Applications/', '/private/', 'password_hash', '$_SESSION'] as $secret) { self::assertStringNotContainsString($secret, $safe); }
        self::assertDoesNotMatchRegularExpression('~\$2[ayb]\$\d{2}\$[./A-Za-z0-9]{53}~', $safe);
        self::assertSame(0, $x->query('//img|//script[not(@src)]|//*[@style]')->length);
    }

    public static function requiredStatusForms(): array
    {
        return [['SYSTEM_ADMIN','/system/schools'], ['SCHOOL_ADMIN','/admin/users/{user}/edit'],
            ['SCHOOL_ADMIN','/academic/enrollments/{enrollment_current}/edit']];
    }

    #[DataProvider('requiredStatusForms')]
    public function testStatusChoiceExposesBackendRequiredSemantics(string $role, string $path): void
    {
        $this->login($role);
        $path = strtr($path, ['{user}'=>(string)$this->users['VIEWER']['user'], '{enrollment_current}'=>(string)$this->f['enrollment_current']]);
        $x = $this->xpath($this->request('GET', $path)->body());
        self::assertGreaterThan(0, $x->query('//form[@method="post"]//select[@name="status"]')->length);
        self::assertSame(0, $x->query('//form[@method="post"]//select[@name="status" and not(@required)]')->length);
    }

    public function testGradebookUsesSameStatusVocabularyAsOtherScreens(): void
    {
        $this->login(); $model = $this->gradebook();
        $model['teachers'] = [['display_name'=>'ครูทดสอบ', 'status'=>'ACTIVE']];
        $model['rows'][0]['enrollment_status'] = 'TRANSFERRED_OUT';
        $html = View::render('gradebook/view', ['gradebook'=>$model, 'canScore'=>false, 'canManageComponents'=>false]);
        $x = $this->xpath($html);
        self::assertSame(1, $x->query('//li//*[@data-status="ACTIVE" and normalize-space(.)="ใช้งาน"]')->length);
        self::assertSame(1, $x->query('//td//*[@data-status="TRANSFERRED_OUT" and normalize-space(.)="ย้ายออก"]')->length);
        $model['teachers'][0]['status'] = '<img onerror="bad">';
        $model['rows'][0]['enrollment_status'] = '<script>bad</script>';
        $html = View::render('gradebook/view', ['gradebook'=>$model, 'canScore'=>false, 'canManageComponents'=>false]);
        self::assertStringContainsString('&lt;img onerror=&quot;bad&quot;&gt;', $html);
        self::assertStringContainsString('&lt;script&gt;bad&lt;/script&gt;', $html);
        self::assertSame(0, $this->xpath($html)->query('//img|//script')->length);
    }

    public function testUnexpectedErrorKeepsExistingGenericNonDocumentContract(): void
    {
        $this->login(); $this->pdo->failPrepare = 'FROM gradebook_scores gs';
        $r = $this->request('GET', $this->readPath());
        self::assertSame(500, $r->status()); self::assertTrue($this->pdo->failureTriggered);
        self::assertSame('Internal Server Error', $r->body());
    }

    private function importPreview(string $hostile): int
    {
        $service = new App\Services\StudentImportService($this->pdo, new App\Repositories\SchoolRepository($this->pdo),
            new App\Repositories\AcademicYearRepository($this->pdo), new App\Repositories\StudentRepository($this->pdo),
            new App\Repositories\GradeLevelRepository($this->pdo), new App\Repositories\ClassroomRepository($this->pdo),
            new App\Repositories\StudentEnrollmentRepository($this->pdo), new App\Repositories\StudentClassroomPlacementRepository($this->pdo),
            new App\Repositories\StudentImportBatchRepository($this->pdo), new App\Repositories\StudentImportRowRepository($this->pdo), new App\Repositories\AuditLogRepository($this->pdo));
        return $service->preview($this->f['schoolA'], $this->users['SCHOOL_ADMIN']['user'], $this->f['yearA'],
            $hostile.'.csv', hash('sha256', 'cross-screen'), [['row_no'=>2,'student_code'=>'NEW','national_id'=>null,
            'prefix_th'=>'ด.ช.','first_name_th'=>'ทดสอบ','last_name_th'=>'สังเคราะห์','gender_code'=>null,'birth_date'=>null,
            'grade_level_code'=>'P1','classroom_code'=>'','entry_date'=>null]]);
    }
}
