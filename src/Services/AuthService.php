<?php
/** AuthService — Couche métier pour l'authentification. */

namespace App\Services;

use App\Repository\UserRepository;
use App\Event\EventDispatcher;

class AuthService
{
    public function __construct(
        private UserRepository $repo,
        private EventDispatcher $events
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
        if (isUserLoggedIn()) {
            return getUserSession();
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
                setUserSession($user);
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
            setUserSession($user);
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
        $superviseurUsernames = getConfig('app_superviseur_usernames', '');
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

        $superviseurUsernames = getConfig('app_superviseur_usernames', '');
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

        if (strpos($authUser, '\\') !== false) {
            $parts = explode('\\', $authUser);
            return strtolower(trim(end($parts)));
        }
        if (strpos($authUser, '@') !== false) {
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
        $usernames = array_map('trim', explode(',', strtolower($list)));
        return array_values(array_filter($usernames, fn($u) => $u !== ''));
    }
}
