<?php

/** AuthService — Couche métier pour l'authentification. */

namespace App\Services;

use Exception;
use App\Repository\UserRepository;
use App\Event\EventDispatcher;

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
     */
    public function getAuthenticatedUser(): ?array
    {
        if (\isUserLoggedIn()) {
            return \getUserSession();
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
            if ($user) {
                \setUserSession($user);
                return $user;
            }
        }

        return null;
    }

    /**
     * Find existing user or auto-create from Windows login.
     */
    public function findOrCreateUser(string $username): ?array
    {
        $user = $this->repo->findByUsernameOrAny($username);

        if ($user) {
            if (!$user['is_active']) {
                return null;
            }
            $user = $this->checkAndPromote($user, $username);
            return $user;
        }

        return $this->autoProvision($username);
    }

    /**
     * Attempt mock login (DEV_MODE only).
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
        if ($user) {
            \setUserSession($user);
            return $user;
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Provisioning
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Auto-provision a new user from their Windows login.
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

        $userId = $this->repo->create([
            'username' => $username,
            'nom'      => $nom,
            'prenom'   => $prenom,
            'email'    => $username . '@dreets.gouv.fr',
            'role'     => $role,
            'site_id'  => null,
        ]);

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
        $superviseurUsernames = \getConfig('app_superviseur_usernames', '');
        if (!empty($superviseurUsernames)) {
            $users = self::parseSuperviseurUsernames($superviseurUsernames);
            if (in_array(strtolower($username), $users)) {
                return ROLE_SUPERVISEUR;
            }
        }
        return ROLE_AGENT;
    }

    /**
     * Check if existing user should be auto-promoted to superviseur.
     */
    public function checkAndPromote(array $user, string $username): array
    {
        if ($user['role'] !== ROLE_AGENT) {
            return $user;
        }

        $superviseurUsernames = \getConfig('app_superviseur_usernames', '');
        if (!empty($superviseurUsernames)) {
            $users = self::parseSuperviseurUsernames($superviseurUsernames);
            if (in_array(strtolower($username), $users)) {
                $this->repo->promoteToSuperviseur($user['id']);
                $user['role'] = ROLE_SUPERVISEUR;
                error_log("SST App: Auto-promoted user '$username' to superviseur (config list rule)");

                $this->events->dispatch('user.promoted', [
                    'user' => $user,
                    'oldRole' => ROLE_AGENT,
                    'newRole' => ROLE_SUPERVISEUR,
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
        if ($autoUser) {
            \setUserSession($autoUser);
            \safeSessionRegenerate();

            require_once __DIR__ . '/../cron.php';
            try {
                \runLazyCron(\getDB());
            } catch (Exception $e) {
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
                \redirect(\url('home'));
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
            \setIntendedUrl($_SERVER['REQUEST_URI'] ?? '');
            \redirect(\url('login'));
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
     */
    public function handleLogout(): void
    {
        \clearSession();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly']]
            );
        }
        session_destroy();

        if (DEV_MODE) {
            \redirect(\url('login'));
        } else {
            \redirect(\url('home'));
        }
    }
}
