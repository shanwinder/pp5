<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditLogRepository
{
    public function __construct(private PDO $pdo) {}

    public function record(
        ?int $schoolId,
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValue,
        ?array $newValue,
        ?string $reason,
        ?string $ipAddress
    ): void {
        $oldJson = $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $newJson = $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_logs (school_id, user_id, action, entity_type, entity_id, old_value, new_value, reason, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$schoolId, $userId, $action, $entityType, $entityId, $oldJson, $newJson, $reason, $ipAddress]);
    }
}
