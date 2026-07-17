<?php

/** UserRepository — Couche d'accès aux données pour les utilisateurs. */

namespace App\Repository;

use Exception;
use PDO;

class UserRepository
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

    // ═══════════════════════════════════════════════════════════════════════════════
    // Queries
    // ═══════════════════════════════════════════════════════════════════════════════

    private function baseQuery(): string
    {
        return 'SELECT u.*, s.code as site_code, s.nom as site_nom
                FROM users u
                LEFT JOIN sites s ON u.site_id = s.id';
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare($this->baseQuery() . ' WHERE u.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            $this->baseQuery() . ' WHERE u.username = :username AND u.is_active = 1'
        );
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByUsernameOrAny(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            $this->baseQuery() . ' WHERE u.username = :username'
        );
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByRole(string $role): array
    {
        $stmt = $this->pdo->prepare(
            $this->baseQuery() . ' WHERE u.role = :role AND u.is_active = 1'
        );
        $stmt->execute([':role' => $role]);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findAll(int $siteId = 0, bool $active = true): array
    {
        $sql = $this->baseQuery() . ' WHERE 1=1';
        $params = [];

        if ($active) {
            $sql .= ' AND u.is_active = 1';
        }
        if ($siteId > 0) {
            $sql .= ' AND u.site_id = :site_id';
            $params[':site_id'] = $siteId;
        }

        $sql .= ' ORDER BY u.nom, u.prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function countActive(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE is_active = 1');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function existsByUsername(string $username, int $excludeId = 0): bool
    {
        $sql = 'SELECT id FROM users WHERE username = :username';
        $params = [':username' => $username];

        if ($excludeId > 0) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function countActiveSuperviseurs(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE role = '" . ROLE_SUPERVISEUR . "' AND is_active = 1"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Writes
    // ═══════════════════════════════════════════════════════════════════════════════

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO users (username, nom, prenom, email, role, site_id)
            VALUES (:username, :nom, :prenom, :email, :role, :site_id)
        ');
        $stmt->execute([
            ':username' => $data['username'],
            ':nom'      => $data['nom'],
            ':prenom'   => $data['prenom'],
            ':email'    => $data['email'] ?? null,
            ':role'     => $data['role'] ?? ROLE_AGENT,
            ':site_id'  => $data['site_id'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET nom = :nom, prenom = :prenom, email = :email,
                username = :username, role = :role, site_id = :site_id,
                updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([
            ':nom'      => $data['nom'],
            ':prenom'   => $data['prenom'],
            ':email'    => !empty($data['email']) ? $data['email'] : null,
            ':username' => $data['username'],
            ':role'     => $data['role'],
            ':site_id'  => $data['site_id'],
            ':id'       => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updateRole(int $id, string $role): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET role = :role, updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':role' => $role, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateSite(int $id, int $siteId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET site_id = :site_id, updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':site_id' => $siteId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET is_active = 0, updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function reactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET is_active = 1, updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function promoteToSuperviseur(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET role = '" . ROLE_SUPERVISEUR . "', updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function anonymize(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET nom = 'Anonymisé', prenom = 'Utilisateur', email = NULL,
                    is_active = 0, updated_at = datetime('now')
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare("
                UPDATE reports
                SET declarant_nom = 'Anonymisé', declarant_prenom = 'Utilisateur',
                    pour_compte_nom = CASE WHEN pour_compte_nom IS NOT NULL THEN 'Anonymisé' ELSE NULL END,
                    pour_compte_prenom = CASE WHEN pour_compte_prenom IS NOT NULL THEN 'Utilisateur' ELSE NULL END,
                    telephone_mobile = NULL
                WHERE declarant_id = :id
            ");
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('
                UPDATE reports SET repondant_id = NULL WHERE repondant_id = :id AND repondant_id IS NOT NULL
            ');
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('
                UPDATE report_responses SET user_id = NULL WHERE user_id = :id AND user_id IS NOT NULL
            ');
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('
                UPDATE report_access_log SET user_id = NULL WHERE user_id = :id AND user_id IS NOT NULL
            ');
            $stmt->execute([':id' => $id]);

            try {
                $this->pdo->exec('DELETE FROM reports_fts');
                $this->pdo->exec('INSERT INTO reports_fts(uuid, objet, description) SELECT uuid, objet, description FROM reports WHERE uuid IS NOT NULL');
            } catch (Exception) {
                // Non-critical
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[SST-DB] anonymize failed: ' . $e->getMessage());
            return false;
        }
    }

    public function exportData(int $id): array
    {
        $user = $this->findById($id);
        if (!$user) {
            return [];
        }

        $stmt = $this->pdo->prepare('
            SELECT r.uuid, r.reference, r.type, r.objet, r.description, r.date_evenement,
                   r.heure_evenement, r.lieu, r.is_confidential, r.etat, r.created_at
            FROM reports r WHERE r.declarant_id = :id ORDER BY r.created_at DESC
        ');
        $stmt->execute([':id' => $id]);
        $reports = $stmt->fetchAll();

        $stmt = $this->pdo->prepare('
            SELECT rr.report_uuid, rr.reponse, rr.nouvel_etat, rr.created_at
            FROM report_responses rr WHERE rr.user_id = :id ORDER BY rr.created_at DESC
        ');
        $stmt->execute([':id' => $id]);
        $responses = $stmt->fetchAll();

        $reports = is_array($reports) ? $reports : [];
        $responses = is_array($responses) ? $responses : [];

        return [
            'user' => [
                'id' => $user['id'], 'username' => $user['username'],
                'nom' => $user['nom'], 'prenom' => $user['prenom'],
                'email' => $user['email'], 'role' => $user['role'],
                'site_id' => $user['site_id'], 'is_active' => $user['is_active'],
                'created_at' => $user['created_at'],
            ],
            'reports_count' => count($reports), 'reports' => $reports,
            'responses_count' => count($responses), 'responses' => $responses,
        ];
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
