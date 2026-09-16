<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/Support/GradebookScoreFixtures.php';

final class GradebookScoreUxTest extends TestCase
{
    use GradebookScoreFixtures;

    public static function readers(): array { return [['SCHOOL_ADMIN',8], ['ACADEMIC_ADMIN',8], ['SUBJECT_TEACHER',8], ['EXECUTIVE',0], ['VIEWER',0], ['HOMEROOM_TEACHER',0]]; }
    #[DataProvider('readers')]
    public function testOnlyAuthorizedCurrentRowsHaveAccessibleInputs(string $role, int $count): void
    {
        $this->login($role); $r = $this->request('GET', $this->readPath());
        self::assertSame(in_array($role, ['VIEWER','HOMEROOM_TEACHER'], true) ? 404 : 200, $r->status());
        $x = $this->xpath($r->body()); self::assertSame($count, $x->query('//input[@hx-post]')->length);
        foreach (['moved','exited','nullHistory','oldHistory'] as $key) {
            self::assertSame(0, $x->query('//tr[@data-enrollment-id="' . $this->f['enrollment_' . $key] . '"]//input')->length);
        }
        foreach ($x->query('//input[@hx-post]') as $input) {
            self::assertSame('text', $input->getAttribute('type')); self::assertSame('decimal', $input->getAttribute('inputmode'));
            self::assertSame('blur', $input->getAttribute('hx-trigger')); self::assertSame('score,_token', $input->getAttribute('hx-params'));
            self::assertSame('closest table:queue all', $input->getAttribute('hx-sync'));
            self::assertSame('outerHTML', $input->getAttribute('hx-swap'));
            self::assertSame((string) $this->f['offeringA'], $input->getAttribute('data-offering-id'));
            self::assertNotSame('', $input->getAttribute('data-enrollment-id')); self::assertNotSame('', $input->getAttribute('data-component-id'));
            self::assertNotSame('', $input->getAttribute('id')); self::assertNotSame('', $input->getAttribute('aria-labelledby'));
            self::assertFalse($input->hasAttribute('data-school-id')); self::assertFalse($input->hasAttribute('data-user-id'));
            self::assertSame(1, $x->query('//*[@id="' . $input->getAttribute('aria-describedby') . '" and @role="status"]')->length);
        }
        if ($count > 0) {
            self::assertSame($this->token(), $x->evaluate('string(//input[@id="gradebook-csrf"]/@value)'));
            self::assertSame(2, $x->query('//script[@src and @defer]')->length);
        }
    }

    public static function immutableStates(): array { return [['year'], ['offering'], ['component']]; }
    #[DataProvider('immutableStates')]
    public function testClosedYearInactiveOfferingAndInactiveColumnsAreReadonly(string $state): void
    {
        $this->login(); $this->changePrerequisite($state); $r = $this->request('GET', $this->readPath()); self::assertSame(200, $r->status());
        $x = $this->xpath($r->body());
        if ($state === 'component') { self::assertSame(0, $x->query('//input[@data-component-id="' . $this->f['componentA'] . '"]')->length); }
        else { self::assertSame(0, $x->query('//input[@hx-post]')->length); self::assertStringContainsString('อ่านอย่างเดียว', $r->body()); }
    }

    public function testEditabilityFollowsLivePermissionMetadataNotRoleName(): void
    {
        $this->login('SUBJECT_TEACHER'); $this->revokeScore('permission');
        $r = $this->request('GET', $this->readPath()); self::assertSame(200, $r->status());
        self::assertSame(0, $this->xpath($r->body())->query('//input[@hx-post]')->length);
        $this->pdo->exec("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='EXECUTIVE' AND p.code='GRADEBOOK_SCORE_ENTER'");
        $this->login('EXECUTIVE'); $r = $this->request('GET', $this->readPath());
        self::assertSame(8, $this->xpath($r->body())->query('//input[@hx-post]')->length);
    }
}
