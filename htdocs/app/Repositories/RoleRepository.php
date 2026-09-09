<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoleRepository
{
    public function __construct(private PDO $pdo) {}

    public function findActiveByCode(string $code): ?array
    {
        $statement = $this->pdo->prepare("SELECT id, code, name_th, scope_type, status FROM roles WHERE code = ? AND status = 'ACTIVE' LIMIT 1");
        $statement->execute([$code]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
