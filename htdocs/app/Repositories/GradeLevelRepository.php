<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GradeLevelRepository
{
    public function __construct(private PDO $pdo) {}

    public function listActive(): array
    {
        return $this->pdo->query("SELECT id, code, name_th, sort_order, status FROM grade_levels
            WHERE status = 'ACTIVE' ORDER BY sort_order ASC, code ASC")->fetchAll();
    }

    public function findActiveById(int $gradeLevelId): ?array
    {
        $statement = $this->pdo->prepare("SELECT id, code, name_th, sort_order, status FROM grade_levels WHERE id = ? AND status = 'ACTIVE' LIMIT 1");
        $statement->execute([$gradeLevelId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
