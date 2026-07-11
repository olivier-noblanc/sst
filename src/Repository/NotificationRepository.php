<?php
/** NotificationRepository — Couche d'accès aux données pour les notifications email. */

namespace App\Repository;

use PDO;

class NotificationRepository
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

    public function findAll(): array
    {
        $stmt = $this->pdo->query('
            SELECT ns.*, s.code as site_code, s.nom as site_nom
            FROM notification_settings ns
            LEFT JOIN sites s ON ns.site_id = s.id
            ORDER BY ns.type, s.code, ns.registry
        ');
        return $stmt->fetchAll();
    }

    public function save(?int $siteId, string $type, string $registry, string $email): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM notification_settings WHERE site_id = :site_id AND type = :type AND registry = :registry AND email = :email');
        $stmt->execute([
            ':site_id'  => $siteId,
            ':type'     => $type,
            ':registry' => $registry,
            ':email'    => $email,
        ]);

        $stmt = $this->pdo->prepare('
            INSERT INTO notification_settings (site_id, type, registry, email)
            VALUES (:site_id, :type, :registry, :email)
        ');
        $stmt->execute([
            ':site_id'  => $siteId,
            ':type'     => $type,
            ':registry' => $registry,
            ':email'    => $email,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM notification_settings WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByType(string $type): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM notification_settings WHERE type = :type');
        $stmt->execute([':type' => $type]);
        return $stmt->rowCount();
    }

    public function findSiteEmails(int $siteId): array
    {
        $stmt = $this->pdo->prepare("SELECT email FROM notification_settings WHERE site_id = :site_id AND type = 'site'");
        $stmt->execute([':site_id' => $siteId]);
        return array_column($stmt->fetchAll(), 'email');
    }

    public function findGlobalEmails(): array
    {
        $stmt = $this->pdo->query("SELECT email FROM notification_settings WHERE type = 'global'");
        return array_column($stmt->fetchAll(), 'email');
    }

    public function getPdo(): PDO { return $this->pdo; }
}
