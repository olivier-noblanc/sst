<?php

/** ReportAuditRepository — Couche d'accès aux données pour l'audit des signalements (journal d'accès). */

namespace App\Repository;

use PDO;

class ReportAuditRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            if (function_exists('getContainer') && getContainer()->has(self::class)) {
                $instance = getContainer()->get(self::class);
            } else {
                $instance = new self(getDB());
            }
        }
        return $instance;
    }

    public function logAccess(string $reportUuid, int $userId, string $role): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO report_access_log (report_uuid, user_id, role)
            VALUES (:report_uuid, :user_id, :role)
        ');
        $stmt->execute([
            ':report_uuid' => $reportUuid,
            ':user_id'     => $userId,
            ':role'        => $role,
        ]);
    }
}
