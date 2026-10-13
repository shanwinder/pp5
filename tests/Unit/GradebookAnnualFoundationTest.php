<?php
declare(strict_types=1);

use App\Services\GradebookAnnualRatio;
use App\Services\GradebookFoundationIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GradebookAnnualFoundationTest extends TestCase
{
    public function testAllIntegerRatiosHaveExactOwnTermTargets(): void
    {
        for ($cw=1;$cw<100;$cw++) {
            $target=GradebookAnnualRatio::from($cw,100-$cw)->targetsInHundredths();
            self::assertSame(5000,array_sum($target)); self::assertSame($cw*50,$target['COURSEWORK']);
        }
        self::assertSame(['COURSEWORK'=>3250,'FINAL_EXAM'=>1750],GradebookAnnualRatio::from('65','35')->targetsInHundredths());
    }
    public static function invalidRatios(): iterable
    {
        foreach ([[0,100],[100,0],[70,20],[-1,101],['70.0','30'],[70.4,29.6],[true,99],[[],30],['7e1',30],[' 70',30],['070',30],[null,null]] as $v) yield $v;
    }
    #[DataProvider('invalidRatios')]
    public function testRatioRejectsBeforeDatabaseCoercion(mixed $a,mixed $b): void
    {
        $this->expectException(DomainException::class);GradebookAnnualRatio::from($a,$b);
    }
    public function testExactDecimalAndRevisionBoundaries(): void
    {
        foreach (['0.01'=>1,'49.99'=>4999,'50.00'=>5000,'50.01'=>5001,'99999.99'=>9999999,'00020.0'=>2000] as $s=>$v) self::assertSame($v,GradebookAnnualRatio::maxInHundredths($s));
        self::assertSame(2,GradebookFoundationIdentity::nextRevision('1',1));
        $this->expectException(DomainException::class);GradebookFoundationIdentity::nextRevision(2,1);
    }
    public static function invalidMax(): iterable
    {
        foreach (['0','-1','100000','1.001','1e2','NaN','1.',1.5,[],null] as $v) yield [$v];
    }
    #[DataProvider('invalidMax')]
    public function testMaxRejectsLossOfPrecision(mixed $v): void
    {
        $this->expectException(DomainException::class);GradebookAnnualRatio::maxInHundredths($v);
    }
    public static function invalidRevisions(): iterable
    {
        foreach ([0,-1,'1.0','01',true,null,PHP_INT_MAX,'18446744073709551615'] as $v) yield [$v];
    }
    #[DataProvider('invalidRevisions')]
    public function testRevisionRejectsCoercionAndOverflow(mixed $v): void
    {
        $this->expectException(DomainException::class);GradebookFoundationIdentity::nextRevision($v,$v);
    }
}
