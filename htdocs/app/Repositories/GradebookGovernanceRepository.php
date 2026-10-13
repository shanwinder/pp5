<?php
declare(strict_types=1);

namespace App\Repositories;

use DomainException;
use PDO;

/** Internal foundation reads only. No bootstrap, origin update or cutover activation API. */
final class GradebookGovernanceRepository
{
    public function __construct(private PDO $pdo) {}

    public function lockCutover(int $schoolId): array
    {
        $this->transactionRequired();
        $q = $this->pdo->prepare('SELECT * FROM gradebook_school_cutovers WHERE school_id=? FOR UPDATE');
        $q->execute([$schoolId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || !in_array($row['phase'], ['PREPARING', 'ARMED', 'ACTIVE'], true)) {
            throw new DomainException('Missing or invalid cutover');
        }
        return $row;
    }

    public function lockOffering(int $schoolId, int $yearId, int $offeringId, string $epoch): array
    {
        $this->transactionRequired();
        $q = $this->pdo->prepare('SELECT * FROM gradebook_offering_governance WHERE subject_offering_id=? AND school_id=? AND academic_year_id=? FOR UPDATE');
        $q->execute([$offeringId, $schoolId, $yearId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['cutover_epoch_key'] !== $epoch
            || !in_array($row['origin'], ['PRE_ACTIVATION', 'POST_ACTIVATION'], true)
            || !in_array($row['state'], $row['origin'] === 'PRE_ACTIVATION' ? ['LEGACY', 'PUBLISHED'] : ['SETUP_REQUIRED', 'PUBLISHED'], true)) {
            throw new DomainException('Missing or invalid governance; no Legacy fallback');
        }
        return $row;
    }

    private function transactionRequired(): void
    {
        if (!$this->pdo->inTransaction()) { throw new DomainException('Locking reads require a transaction'); }
    }
}
