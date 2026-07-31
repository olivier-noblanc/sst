<?php

/** AuthService — Couche métier pour l'authentification. */

namespace App\Services;

use Exception;
use Throwable;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Event\EventDispatcher;
use App\DTO\CreateUserCommand;
use App\DTO\SiteId;

class AuthService
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly EventDispatcher $events
    ) {}

    // ═══════════════════════════════════════════════════════════════════════════════
    // Authentication
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Get the currently authenticated user.
     * In PROD: reads IIS AUTH_USER. In DEV: returns null (login form).
     * @return UserArray|null
     */
    public function getAuthenticatedUser(): ?array
    {
        if (\isUserLoggedIn()) {
            // Audit #9 + #22 + #23 + #38 — re-validate session periodically.
            // Before this fix, a deactivated user (or a user whose role had
            // changed) kept their session active 24h until expiration.
            // Now we check sessions_invalid_before marker every 5 minutes.
            $user = \getUserSession();
            if (is_array($user) && $this->shouldRevalidateSession($user)) {
                $userId = (int) $user['id'];
                if ($userId > 0) {
                    $sessionStartedAt = (int) ($_SESSION['session_started_at'] ?? 0);
                    // session_started_at may be missing for legacy sessions — treat as 0 (force check)
                    $valid = $this->isSessionValid($userId, $sessionStartedAt);
                    if (!$valid) {
                        // Force logout
                        \clearSession();
                        return null;
                    }
                    // Re-fetch the user (role may have changed)
                    $freshUser = $this->repo->findById($userId);
                    if ($freshUser === null || (int) $freshUser['is_active'] !== 1) {
                        \clearSession();
                        return null;
                    }
                    \setUserSession($freshUser);
                    $_SESSION['last_session_check'] = time();
                    return $freshUser;
                }
            }
            return $user;
        }

        if (!DEV_MODE) {
            $authUser = $_SERVER['AUTH_USER'] ?? '';
            if (empty($authUser)) {
                return null;
            }

            $username = self::extractUsername($authUser);
            if (empty($username)) {
                return null;
            }

            $user = $this->findOrCreateUser($username);
            if ($user !== null) {
                \setUserSession($user);
                $_SESSION['session_started_at'] = time();
                $_SESSION['last_session_check'] = time();
                return $user;
            }
        }

        return null;
    }

    /**
     * Throttle DB check — only re-validate every N seconds (default 5 min).
     *
     * @param UserArray $user
     */
    private function shouldRevalidateSession(array $user): bool
    {
        $lastCheck = (int) ($_SESSION['last_session_check'] ?? 0);
        if ($lastCheck === 0) {
            return true; // Never checked — first page load after login
        }
        // Default 5 min, configurable via config_app
        $interval = (int) (getConfigService()->get('app_session_check_interval', '300'));
        return (time() - $lastCheck) >= $interval;
    }

    /**
     * Check if the session is still valid via the sessions_invalid_before marker.
     */
    private function isSessionValid(int $userId, int $sessionStartedAt): bool
    {
        // Audit #9 — delegate SQL to UserRepository (PHPStan NoSqlOutsideRepository)
        $state = $this->repo->findSessionState($userId);
        if ($state === null) {
            return false; // User no longer exists
        }
        if ($state['is_active'] !== 1) {
            return false; // User deactivated
        }
        $invalidBefore = $state['sessions_invalid_before'];
        if ($invalidBefore === null) {
            return true; // No invalidation marker
        }
        $invalidBeforeTs = strtotime($invalidBefore);
        if ($invalidBeforeTs === false) {
            return true; // Malformed — fail open
        }
        // If the marker is more recent than session start → invalid
        return $invalidBeforeTs <= $sessionStartedAt;
    }

    /**
     * Find existing user or auto-create from Windows login.
     * @return UserArray|null
     */
    public function findOrCreateUser(string $username): ?array
    {
        /** @var UserArray|null $user */
        $user = $this->repo->findByUsernameOrAny($username);

        if ($user !== null) {
            if ($user['is_active'] === 0) {
                return null;
            }
            /** @var UserArray $user */
            $user = $this->checkAndPromote($user, $username);
            return $user;
        }

        return $this->autoProvision($username);
    }

    /**
     * Attempt mock login (DEV_MODE only).
     * @return UserArray|null
     */
    public function mockLogin(string $username): ?array
    {
        if (!DEV_MODE) {
            return null;
        }

        $username = trim($username);
        if (empty($username)) {
            return null;
        }

        $user = $this->findOrCreateUser($username);
        if ($user !== null) {
            \setUserSession($user);
            // Audit #9 — track session start for periodic re-validation.
            $_SESSION['session_started_at'] = time();
            $_SESSION['last_session_check'] = time();
            return $user;
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Provisioning
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Auto-provision a new user from their Windows login.
     * @return UserArray|null
     */
    public function autoProvision(string $username): ?array
    {
        $role = $this->determineRole($username);

        $parts = explode('.', $username);
        $prenom = ucfirst($parts[0]);
        $nom = ucfirst($parts[1] ?? 'Utilisateur');
        if (count($parts) > 2) {
            $nom = ucfirst($parts[1]) . ' ' . ucfirst($parts[2]);
        }

        $userId = $this->repo->create(new CreateUserCommand(
            username: $username,
            nom: $nom,
            prenom: $prenom,
            email: $username . '@dreets.gouv.fr',
            role: $role,
            siteId: SiteId::none(),
        ));

        /** @var UserArray|null $user */
        $user = $this->repo->findById($userId);

        $this->events->dispatch('user.provisioned', [
            'user' => $user,
            'username' => $username,
            'pdo' => $this->repo->getPdo(),
        ]);

        return $user;
    }

    /**
     * Determine role from superviseur username list config.
     */
    public function determineRole(string $username): string
    {
        $superviseurUsernames = getConfigService()->get('app_superviseur_usernames', '');
        if (!empty($superviseurUsernames)) {
            $users = self::parseSuperviseurUsernames($superviseurUsernames);
            if (in_array(strtolower($username), $users, true)) {
                return UserRole::Superviseur->value;
            }
        }
        return UserRole::Agent->value;
    }

    /**
     * Check if existing user should be auto-promoted to superviseur.
     * @param UserArray $user
     * @return UserArray
     */
    public function checkAndPromote(array $user, string $username): array
    {
        if ($user['role'] !== UserRole::Agent->value) {
            return $user;
        }

        $superviseurUsernames = getConfigService()->get('app_superviseur_usernames', '');
        if (!empty($superviseurUsernames)) {
            $users = self::parseSuperviseurUsernames($superviseurUsernames);
            if (in_array(strtolower($username), $users, true)) {
                $id = $user['id'];
                $this->repo->promoteToSuperviseur($id);
                $user['role'] = UserRole::Superviseur->value;
                error_log("SST App: Auto-promoted user '$username' to superviseur (config list rule)");

                $this->events->dispatch('user.promoted', [
                    'user' => $user,
                    'oldRole' => UserRole::Agent->value,
                    'newRole' => UserRole::Superviseur->value,
                    'pdo' => $this->repo->getPdo(),
                ]);
            }
        }

        return $user;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Extract clean username from AUTH_USER (DOMAIN\user or user@domain).
     */
    public static function extractUsername(string $authUser): string
    {
        $authUser = trim($authUser);
        if (empty($authUser)) {
            return '';
        }

        if (str_contains($authUser, '\\')) {
            $parts = explode('\\', $authUser);
            return strtolower(trim(array_last($parts)));
        }
        if (str_contains($authUser, '@')) {
            $parts = explode('@', $authUser);
            return strtolower(trim($parts[0]));
        }
        return strtolower($authUser);
    }

    /**
     * Parse comma-separated superviseur username list.
     * @return list<string>
     */
    public static function parseSuperviseurUsernames(string $list): array
    {
        $usernames = array_map(trim(...), explode(',', strtolower($list)));
        return array_values(array_filter($usernames, fn($u) => $u !== ''));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Auth Flow (from auth_flow.php)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Handle auto-authentication via IIS Windows Authentication.
     */
    public function handleAutoAuth(): void
    {
        if (\isUserLoggedIn()) {
            return;
        }

        $autoUser = $this->getAuthenticatedUser();
        if ($autoUser !== null) {
            \setUserSession($autoUser);
            \safeSessionRegenerate();

            require_once __DIR__ . '/../cron.php';
            try {
                \runLazyCron(\getDB());
            } catch (Exception $e) {
                // @silent-ok: lazy-cron piggybacking on IIS auto-auth requests — must not
                // block the login itself if a background task fails.
                error_log('[SST-CRON] Lazy cron failed on IIS auto-auth: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle the login page (dev mode only).
     * Always calls exit — never returns.
     */
    public function handleLoginPage(string $page): void
    {
        if ($page !== 'login') {
            return;
        }

        if (!DEV_MODE) {
            if (\isUserLoggedIn()) {
                new HttpService()->redirect(new HttpService()->url('home'));
            } else {
                http_response_code(500);
                $message = 'L\'authentification Windows IIS n\'est pas active. Vérifiez que Windows Authentication est activée et Anonymous Authentication désactivée dans IIS Manager.';
                echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur de configuration</title>';
                echo '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f5f5;}';
                echo '.error-box{background:white;padding:2rem;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);max-width:500px;text-align:center;}';
                echo 'h1{color:#dc3545;}</style></head><body><div class="error-box">';
                echo '<h1>Erreur de configuration</h1>';
                echo '<p>' . htmlspecialchars($message) . '</p>';
                echo '<p>Contactez l\'administrateur.</p>';
                echo '</div></body></html>';
                exit;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require __DIR__ . '/../../handlers/login_handler.php';
            exit;
        }

        $pageFile = __DIR__ . '/../../pages/login.php';
        if (file_exists($pageFile)) {
            require $pageFile;
        }
        exit;
    }

    /**
     * Handle the case where the user is not authenticated.
     */
    public function handleNotAuthenticated(): void
    {
        if (\isUserLoggedIn()) {
            return;
        }

        if (DEV_MODE) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            \setIntendedUrl($requestUri);
            new HttpService()->redirect(new HttpService()->url('login'));
        } else {
            http_response_code(500);
            $message = 'AUTH_USER non disponible. Vérifiez que Windows Authentication est activée dans IIS Manager.';
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur de configuration</title>';
            echo '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f5f5;}';
            echo '.error-box{background:white;padding:2rem;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);max-width:500px;text-align:center;}';
            echo 'h1{color:#dc3545;}</style></head><body><div class="error-box">';
            echo '<h1>Erreur de configuration</h1>';
            echo '<p>' . htmlspecialchars($message) . '</p>';
            echo '<p>Contactez l\'administrateur.</p>';
            echo '</div></body></html>';
            exit;
        }
    }

    /**
     * Handle logout: clear session, destroy cookie, redirect.
     *
     * Audit #38 — Bump sessions_invalid_before so all OTHER sessions of this
     * user are also invalidated (logout everywhere). This is the expected
     * behavior for a security-conscious application — when a user logs out,
     * they probably want all their sessions closed, not just the current one.
     */
    public function handleLogout(): void
    {
        // Audit #38 — invalidate all sessions of this user before clearing
        $userId = (int) (\getUserSession()['id'] ?? 0);
        if ($userId > 0) {
            try {
                $this->repo->invalidateSessions($userId);
            } catch (Throwable $e) {
                // @silent-ok: pre-migration (column missing) — fail silently, logout still works
                error_log('[SST-AUTH] handleLogout: invalidateSessions failed: ' . $e->getMessage());
            }
        }

        \clearSession();
        if (ini_get('session.use_cookies') !== false) {
            $params = session_get_cookie_params();
            $sessionName = session_name() !== false ? session_name() : '';
            setcookie(
                $sessionName,
                '',
                ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly']]
            );
        }
        session_destroy();

        if (DEV_MODE) {
            new HttpService()->redirect(new HttpService()->url('login'));
        } else {
            new HttpService()->redirect(new HttpService()->url('home'));
        }
    }
}
