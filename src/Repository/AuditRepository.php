<?php

/** AuditRepository — Couche d'accès aux données pour le journal d'audit. */

namespace App\Repository;

use PDO;

class AuditRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

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

    /** @param array<string, mixed> $context  // audit context — inherently mixed */
    public function log(
        string $category,
        string $action,
        string $details,
        ?int $targetId = null,
        ?string $targetType = null,
        array $context = [],
        ?string $targetUuid = null,
        ?int $userId = null,
    ): void {
        if ($userId === null) {
            // Audit #60 (deptrac) — délègue la lecture du user_id à l'appelant.
            // Avant : AuditRepository dépendait de SessionService (Repository → Service,
            // interdit par deptrac). Maintenant l'appelant passe userId explicitement.
            // Si null, on retombe sur currentUser() helper (qui n'est pas une dépendance OOP).
            $userId = (int) (\currentUser()['id'] ?? 0);
        }
        $username = \currentUserUsername() !== '' ? \currentUserUsername() : 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        $stmt = $this->pdo->prepare('
            INSERT INTO audit_log (user_id, username, category, action, target_id, target_type, target_uuid, details, context, ip_address)
            VALUES (:user_id, :username, :category, :action, :target_id, :target_type, :target_uuid, :details, :context, :ip)
        ');
        $stmt->execute([
            ':user_id'      => $userId,
            ':username'     => $username,
            ':category'     => $category,
            ':action'       => $action,
            ':target_id'    => $targetId,
            ':target_type'  => $targetType,
            ':target_uuid'  => $targetUuid,
            ':details'      => $details,
            ':context'      => !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            ':ip'           => $ip,
        ]);
    }

    /**
     * @param array<string, string|int|null> $filters
     * @return array{entries: list<array<string, mixed>>, total: int}
     */
    public function findPaginated(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['category'])) {
            $where .= ' AND category = :category';
            $params[':category'] = $filters['category'];
        }
        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = :user_id';
            /** @var int */
            $userId = $filters['user_id'];
            $params[':user_id'] = $userId;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (details LIKE :q OR username LIKE :q2)';
            $params[':q'] = '%' . $filters['q'] . '%';
            $params[':q2'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['username'])) {
            $where .= ' AND username = :filter_username';
            $params[':filter_username'] = $filters['username'];
        }

        $countSql = "SELECT COUNT(*) FROM audit_log WHERE $where";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM audit_log WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        /** @var list<array<string, mixed>> */
        $entries = $stmt->fetchAll();
        return ['entries' => $entries, 'total' => $total];
    }

    /**
     * @param int|string $targetId
     * @return list<array<string, mixed>>
     */
    public function findByTarget(string $targetType, int|string $targetId): array
    {
        if (is_string($targetId) && !is_numeric($targetId)) {
            $stmt = $this->pdo->prepare('
                SELECT * FROM audit_log
                WHERE target_type = :target_type AND target_uuid = :target_uuid
                ORDER BY created_at DESC
            ');
            $stmt->execute([':target_type' => $targetType, ':target_uuid' => $targetId]);
        } else {
            $stmt = $this->pdo->prepare('
                SELECT * FROM audit_log
                WHERE target_type = :target_type AND target_id = :target_id
                ORDER BY created_at DESC
            ');
            $stmt->execute([':target_type' => $targetType, ':target_id' => $targetId]);
        }
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }
}
