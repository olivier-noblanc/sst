<?php

/**
 * SessionInvalidator — Service for invalidating user sessions from the server.
 *
 * Audit #9 + #22 + #23 + #38 (refactor R4).
 *
 * Problème adressé :
 *   Avant ce fix, AuthService::getAuthenticatedUser() retournait directement
 *   la valeur cachée en session ($_SESSION['user']) sans re-vérifier is_active
 *   ou role en DB. Un user désactivé (ou dont le role avait changé) gardait
 *   donc sa session active jusqu'à expiration (24h) :
 *     - User licencié à 14h → encore connecté à 14h05
 *     - User rétrogradé agent → encore superviseur dans sa session
 *
 * Solution :
 *   - Ajout d'une colonne `users.sessions_invalid_before DATETIME` (nullable)
 *   - Lors de deactivate/anonymize/update (si role change), on bump le
 *     marqueur à NOW()
 *   - AuthService::getAuthenticatedUser() compare le timestamp de début de
 *     session avec sessions_invalid_before. Si le marqueur est plus récent
 *     que le début de session → on force un re-fetch et on désactive
 *     l'utilisateur si is_active=0.
 *   - Pour limiter le coût (1 SQL par page load), on re-vérifie au maximum
 *     toutes les 5 minutes (configurable via `app_session_check_interval`).
 */

namespace App\Services;

use App\Repository\UserRepository;
use PDO;

class SessionInvalidator
{
    /** Re-check interval in seconds (5 minutes by default). */
    private const DEFAULT_CHECK_INTERVAL = 300;

    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Mark all sessions of the given user as invalid (forces re-validation on next request).
     *
     * Called from UserService::deactivate/anonymize/update (if role changed).
     */
    public function invalidateUserSessions(int $userId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET sessions_invalid_before = datetime('now')
            WHERE id = :id
        ");
        $stmt->execute([':id' => $userId]);
    }

    /**
     * Check if the current session is still valid by comparing session_start_time
     * with the user's sessions_invalid_before marker.
     *
     * Returns true if the session is valid (or no marker is set).
     * Returns false if the session should be invalidated (force re-login or re-fetch).
     */
    public function isSessionStillValid(int $userId, int $sessionStartedAt): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT sessions_invalid_before, is_active
            FROM users
            WHERE id = :id
        ");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            // User no longer exists → session invalid
            return false;
        }

        if ((int) $row['is_active'] !== 1) {
            // User was deactivated → session invalid
            return false;
        }

        $invalidBefore = $row['sessions_invalid_before'] ?? null;
        if ($invalidBefore === null || $invalidBefore === '') {
            // No invalidation marker → session still valid
            return true;
        }

        $invalidBeforeTs = strtotime((string) $invalidBefore);
        if ($invalidBeforeTs === false) {
            return true; // Malformed marker → fail open (better than locking everyone out)
        }

        // If the marker is more recent than the session start → invalidated
        return $invalidBeforeTs <= $sessionStartedAt;
    }

    /**
     * Get the configured check interval (in seconds).
     * Used by AuthService to throttle DB checks.
     */
    public function getCheckInterval(): int
    {
        // Read from config_app if available, otherwise default to 5 minutes.
        $configured = getConfigService()->get('app_session_check_interval', (string) self::DEFAULT_CHECK_INTERVAL);
        $interval = (int) $configured;
        return $interval > 0 ? $interval : self::DEFAULT_CHECK_INTERVAL;
    }
}
