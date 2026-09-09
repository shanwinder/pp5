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
            "SELECT id, school_code, name_th
             FROM schools
             WHERE id = :id AND status = 'ACTIVE'
             LIMIT 1"
        );
        $statement->execute(['id' => $schoolId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
