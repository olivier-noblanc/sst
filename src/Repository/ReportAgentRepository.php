<?php

/** ReportAgentRepository — Couche d'accès aux données pour les agents liés aux signalements et les invitations. */

namespace App\Repository;

use App\Enum\ReportState;
use App\Enum\VisibilityMode;
use Exception;
use PDO;

class ReportAgentRepository
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

    /**
     * Count reports visible to an agent, including reports where they are linked
     * via report_agents table.
     */
    public function countVisibleForAgent(string $type, int $userId, int $siteId = 0, string $visibility = VisibilityMode::Confidential->value): int
    {
        $sql = "SELECT COUNT(*) FROM reports r WHERE r.type = :type AND r.etat != '" . ReportState::Abandonne->value . "'";
        $params = [':type' => $type];

        $linkedClause = '(r.declarant_id = :user_id OR r.uuid IN (SELECT report_uuid FROM report_agents WHERE user_id = :user_id))';

        if ($visibility === VisibilityMode::Confidential->value) {
            $sql .= " AND $linkedClause";
            $params[':user_id'] = $userId;
        } elseif ($visibility === VisibilityMode::AgentChoice->value) {
            if ($siteId > 0) {
                $sql .= ' AND r.site_id = :site_id';
                $params[':site_id'] = $siteId;
            }
            $sql .= " AND (r.is_confidential = 0 OR $linkedClause)";
            $params[':user_id'] = $userId;
        } else {
            // public
            if ($siteId > 0) {
                $sql .= ' AND r.site_id = :site_id';
                $params[':site_id'] = $siteId;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array{id: int, nom: string, prenom: string, email: string}> */
    public function getLinkedAgents(string $reportUuid): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.nom, u.prenom, u.email
            FROM report_agents ra
            JOIN users u ON u.id = ra.user_id
            WHERE ra.report_uuid = ?
            ORDER BY u.nom, u.prenom
        ');
        $stmt->execute([$reportUuid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<int, array{id: int, nom: string, prenom: string, email: string}> $rows */
        return $rows;
    }

    /** @return list<array{email: string, created_at: string}> */
    public function getPendingInvites(string $reportUuid): array
    {
        $stmt = $this->pdo->prepare('
            SELECT email, created_at FROM report_agent_invites
            WHERE report_uuid = ? AND confirmed = 0
            ORDER BY created_at
        ');
        $stmt->execute([$reportUuid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        /** @var list<array{email: string, created_at: string}> $rows */
        return $rows;
    }

    /** @return array{id: int, report_uuid: string, email: string, token: string, confirmed: int, confirmed_at: string|null, created_at: string}|null */
    public function getAgentInviteByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM report_agent_invites WHERE token = ? AND confirmed = 0
        ');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        /** @var array{id: int, report_uuid: string, email: string, token: string, confirmed: int, confirmed_at: string|null, created_at: string}|null $row */
        return is_array($row) ? $row : null;
    }

    /** @param list<int|string> $userIds */
    public function linkAgents(string $reportUuid, array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        // Filter valid user IDs
        $validIds = array_filter(array_map(fn($id) => (int) $id, $userIds), fn($id) => $id > 0);
        if (empty($validIds)) {
            return;
        }

        // Build multi-row INSERT with UNION ALL for SQLite
        $rows = [];
        $params = [];
        foreach ($validIds as $i => $uid) {
            $rows[] = "(:uuid, :uid_{$i})";
            $params[":uid_{$i}"] = $uid;
        }
        $params[':uuid'] = $reportUuid;

        $sql = 'INSERT OR IGNORE INTO report_agents (report_uuid, user_id) VALUES '
            . implode(', ', $rows);
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * Bug #10 — Insert invite with a pre-generated token (after email sent successfully).
     */
    public function createAgentInviteWithToken(string $reportUuid, string $email, string $token): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO report_agent_invites (report_uuid, email, token)
            VALUES (:uuid, :email, :token)
        ');
        $stmt->execute([':uuid' => $reportUuid, ':email' => $email, ':token' => $token]);
    }

    public function confirmAgentInvite(string $token, int $userId): bool
    {
        $invite = $this->getAgentInviteByToken($token);
        if ($invite === null) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE report_agent_invites SET confirmed = 1, confirmed_at = datetime('now') WHERE token = ?
            ");
            $stmt->execute([$token]);
            $this->linkAgents($invite['report_uuid'], [$userId]);
            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return true;
    }

    /**
     * Check if a user is linked to a report via report_agents table.
     */
    public function isLinkedAgent(string $reportUuid, int $userId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1 FROM report_agents WHERE report_uuid = :uuid AND user_id = :user_id LIMIT 1
        ');
        $stmt->execute([':uuid' => $reportUuid, ':user_id' => $userId]);
        return (bool) $stmt->fetch();
    }
}
