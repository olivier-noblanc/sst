<?php

namespace App\Repository;

use App\DTO\SiteId;
use App\Enum\UserRole;
use Exception;
use PDO;
use Throwable;

/**
 * UserRepository — Couche d'accès aux données pour les utilisateurs.
 */
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

    /** @return UserArray|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare($this->baseQuery() . ' WHERE u.id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        /** @var UserArray|null $row */
        return is_array($row) ? $row : null;
    }

    /** @return UserArray|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            $this->baseQuery() . ' WHERE u.username = :username AND u.is_active = 1'
        );
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        /** @var UserArray|null $row */
        return is_array($row) ? $row : null;
    }

    /** @return UserArray|null */
    public function findByUsernameOrAny(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            $this->baseQuery() . ' WHERE u.username = :username'
        );
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        /** @var UserArray|null $row */
        return is_array($row) ? $row : null;
    }

    /** @return list<UserArray> */
    public function findByRole(string $role): array
    {
        $stmt = $this->pdo->prepare(
            $this->baseQuery() . ' WHERE u.role = :role AND u.is_active = 1'
        );
        $stmt->execute([':role' => $role]);
        $rows = $stmt->fetchAll();
        /** @var list<UserArray> $rows */
        return is_array($rows) ? $rows : [];
    }

    /** @return list<UserArray> */
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
        /** @var list<UserArray> $rows */
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
        // Audit #36 — bind the enum value as a parameter instead of concatenating
        // it into the SQL string. Same logic, but safer (defense-in-depth even
        // though the value comes from a PHP enum and is safe).
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM users WHERE role = :role AND is_active = 1'
        );
        $stmt->execute([':role' => UserRole::Superviseur->value]);
        return (int) $stmt->fetchColumn();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Writes
    // ═══════════════════════════════════════════════════════════════════════════════

    /** @param array{username: string, nom: string, prenom: string, email?: string|null, role?: string, site_id?: int|null} $data */
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
            ':role'     => $data['role'] ?? UserRole::Agent->value,
            // site_id = 0 is the UI/form sentinel for "no site" ("— Aucun —" option,
            // and the hidden field forced empty in no-site-mode) — 0 is never a real
            // site id (SQLite autoincrement starts at 1), and the FOREIGN KEY on
            // site_id rejects it. Must bind NULL, which the FK accepts (nullable column).
            ':site_id'  => SiteId::fromInput((int) ($data['site_id'] ?? 0))->toSql(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array{nom: string, prenom: string, email: string|null, username: string, role: string, site_id?: int|null} $data */
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
            // Same NULL-vs-0 fix as create() above — this is the exact bug that made
            // any edit (role change or otherwise) fail with a FOREIGN KEY constraint
            // violation whenever site_id came through as 0 (no-site-mode, or the
            // explicit "— Aucun —" option), silently reported as a generic error and
            // leaving the role (and every other field) unchanged.
            ':site_id'  => SiteId::fromInput((int) ($data['site_id'] ?? 0))->toSql(),
            ':id'       => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updateSite(int $id, int $siteId): bool
    {
        // Audit #21 — site_chosen_at wasn't set → the 7-day grace period
        // check in choose_site_handler was always falling back to $daysSinceChoice = 999
        // (since $siteChosenAt was always null), which would have blocked every change...
        // except the check was guarded by $hasExistingSite which was already false on
        // first set, so the bug was latent. Now site_chosen_at is written atomically
        // with site_id, the grace period actually works.
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET site_id = :site_id,
                site_chosen_at = datetime('now'),
                updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':site_id' => SiteId::fromInput($siteId)->toSql(), ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Audit #9 + #22 + #23 + #38 — invalidate all active sessions of a user
     * by bumping the sessions_invalid_before marker. Called from
     * UserService::deactivate/anonymize/update (if role changed).
     */
    public function invalidateSessions(int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET sessions_invalid_before = datetime('now')
                WHERE id = :id
            ");
            $stmt->execute([':id' => $userId]);
        } catch (Throwable $e) {
            // Migration may not have run yet (column missing) — log and continue.
            error_log('[SST-USER] invalidateSessions failed: ' . $e->getMessage());
        }
    }

    /**
     * Audit #9 — fetch the session invalidation state for a user.
     * Returns ['is_active' => int, 'sessions_invalid_before' => ?string] or null if user not found.
     *
     * @return array{is_active: int, sessions_invalid_before: ?string}|null
     */
    public function findSessionState(int $userId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT is_active, sessions_invalid_before
                FROM users
                WHERE id = :id
            ');
            $stmt->execute([':id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }
            return [
                'is_active' => (int) ($row['is_active'] ?? 0),
                'sessions_invalid_before' => $this->normalizeTimestamp($row['sessions_invalid_before'] ?? null),
            ];
        } catch (Throwable $e) {
            // Pre-migration (column missing) — fail safe (session considered valid)
            error_log('[SST-USER] findSessionState failed: ' . $e->getMessage());
            return ['is_active' => 1, 'sessions_invalid_before' => null];
        }
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
            UPDATE users SET role = '" . UserRole::Superviseur->value . "', updated_at = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function anonymize(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            // Consolidé dans AnonymizationPolicy — valeurs et liste des tables
            // liées ne vivent plus qu'à un seul endroit (voir sa docblock pour
            // le pourquoi : c'est ce qui a laissé passer le trou report_agents).
            new AnonymizationPolicy()->anonymizeUser($this->pdo, $id);

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

    /** @return array{user: array{id: int, username: string, nom: string, prenom: string, email: string|null, role: string, site_id: int|null, is_active: int, created_at: string}, reports_count: int, reports: list<array{uuid: string, reference: string, type: string, objet: string, description: string, date_evenement: string, heure_evenement: string|null, lieu: string|null, is_confidential: int, etat: string, created_at: string}>, responses_count: int, responses: list<array{report_uuid: string, reponse: string|null, nouvel_etat: string|null, created_at: string}>} */
    public function exportData(int $id): array
    {
        $user = $this->findById($id);
        if ($user === null) {
            return ['user' => [], 'reports_count' => 0, 'reports' => [], 'responses_count' => 0, 'responses' => []];
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

        /** @var list<array{uuid: string, reference: string, type: string, objet: string, description: string, date_evenement: string, heure_evenement: string|null, lieu: string|null, is_confidential: int, etat: string, created_at: string}> $reports */
        $reports = is_array($reports) ? $reports : [];
        /** @var list<array{report_uuid: string, reponse: string|null, nouvel_etat: string|null, created_at: string}> $responses */
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

    /**
     * Normalize a DB timestamp value (mixed from PDO fetch) to ?string.
     *
     * Audit #60 — PHPStan strict rules complain about `mixed !== null`
     * (always true since mixed includes null but the comparison is type-unsafe).
     * This helper centralizes the normalization with proper type checks.
     *
     * @param string|int|null $value
     */
    private function normalizeTimestamp($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            return null;
        }
        if ($value === '') {
            return null;
        }
        return $value;
    }
}
