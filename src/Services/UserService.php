<?php

namespace App\Services;

use App\Repository\SiteRepository;
use App\Enum\UserRole;
use RuntimeException;
use App\Repository\UserRepository;
use App\Event\EventDispatcher;
use App\DTO\CreateUserCommand;
use App\DTO\UpdateUserCommand;

class UserService
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly EventDispatcher $events
    ) {}

    // ═══════════════════════════════════════════════════════════════════════════════
    // CRUD
    // ═══════════════════════════════════════════════════════════════════════════════

    public function create(CreateUserCommand $cmd): int
    {
        $userId = $this->repo->create($cmd->toArray());
        $user = $this->repo->findById($userId);

        $this->events->dispatch('user.created', [
            'user' => $user,
            'cmd' => $cmd,
            'pdo' => $this->repo->getPdo(),
        ]);

        return $userId;
    }

    public function update(int $id, UpdateUserCommand $cmd, int $currentUserId): bool
    {
        $user = $this->repo->findById($id);
        if ($user === null) {
            throw new RuntimeException('Utilisateur introuvable.');
        }
        /** @var UserArray $user */

        $demoteErrors = $this->canDemote($id, $cmd->role, $user);
        if (!empty($demoteErrors)) {
            throw new RuntimeException(implode(' ', $demoteErrors));
        }

        $roleChanged = $user['role'] !== $cmd->role;
        // Audit #29 — test le retour du repo.update. Avant ce fix, l'event
        // 'user.updated' était dispatché même si l'UPDATE était no-op.
        $updateResult = $this->repo->update($id, $cmd->toArray());

        if ($currentUserId === $id) {
            refreshCurrentUser($this->repo->getPdo());
        }

        if ($updateResult) {
            $this->events->dispatch('user.updated', [
                'user' => $user,
                'cmd' => $cmd,
                'pdo' => $this->repo->getPdo(),
            ]);

            if ($roleChanged) {
                // Audit #23 — invalidate the user's other active sessions so
                // they don't keep their old role for 24h.
                $this->invalidateUserSessions($id);

                $this->events->dispatch('user.role_changed', [
                    'user' => $user,
                    'oldRole' => $user['role'],
                    'newRole' => $cmd->role,
                    'pdo' => $this->repo->getPdo(),
                ]);
            }
        }

        return $updateResult;
    }

    public function deactivate(int $id, int $currentUserId): bool
    {
        if ($currentUserId === $id) {
            throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user = $this->repo->findById($id);
        if ($user === null) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        if (!$this->canDeactivate($id)) {
            throw new RuntimeException('Impossible de désactiver le dernier superviseur actif.');
        }

        $result = $this->repo->deactivate($id);

        // Audit #12 — ne dispatcher que si la désactivation a réussi.
        if ($result) {
            // Audit #9 + #22 — invalidate all the user's active sessions immediately.
            $this->invalidateUserSessions($id);

            $this->events->dispatch('user.deactivated', [
                'user' => $user,
                'pdo' => $this->repo->getPdo(),
            ]);
        }

        return $result;
    }

    public function reactivate(int $id): bool
    {
        $user = $this->repo->findById($id);
        if ($user === null) {
            throw new RuntimeException('Utilisateur introuvable.');
        }
        if ((int) $user['is_active'] === 1) {
            throw new RuntimeException('Cet utilisateur est déjà actif.');
        }
        $result = $this->repo->reactivate($id);

        if ($result) {
            $this->events->dispatch('user.reactivated', [
                'user' => $user,
                'pdo' => $this->repo->getPdo(),
            ]);
        }

        return $result;
    }

    /**
     * Invalidate all active sessions of the given user.
     *
     * Audit #9 + #22 + #23 + #38 — bump the sessions_invalid_before marker
     * so the next request from that user forces a re-validation (and logout
     * if they're now inactive or their role changed).
     */
    private function invalidateUserSessions(int $userId): void
    {
        // Audit #9 — delegate SQL to UserRepository (PHPStan NoSqlOutsideRepository)
        $this->repo->invalidateSessions($userId);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Queries
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @return UserArray|null
     */
    public function findById(int $id): ?array
    {
        $result = $this->repo->findById($id);
        /** @var UserArray|null $result */
        return $result;
    }

    /**
     * @return UserArray|null
     */
    public function findByUsername(string $username): ?array
    {
        $result = $this->repo->findByUsername($username);
        /** @var UserArray|null $result */
        return $result;
    }

    /**
     * @return list<UserArray>
     */
    public function findAll(int $siteId = 0, bool $active = true): array
    {
        $result = $this->repo->findAll($siteId, $active);
        /** @var list<UserArray> $result */
        return $result;
    }

    public function countActive(): int
    {
        return $this->repo->countActive();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Validation
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    public function validate(array $input, int $excludeId = 0): array
    {
        $errors = [];

        /** @var string */
        $nomVal = $input['nom'] ?? '';
        $nom = trim($nomVal);
        if (empty($nom)) {
            $errors['nom'] = 'Le nom est requis.';
        }

        /** @var string */
        $prenomVal = $input['prenom'] ?? '';
        $prenom = trim($prenomVal);
        if (empty($prenom)) {
            $errors['prenom'] = 'Le prénom est requis.';
        }

        /** @var string */
        $usernameVal = $input['username'] ?? '';
        $username = trim($usernameVal);
        if (empty($username)) {
            $errors['username'] = 'L\'identifiant est requis.';
        } elseif ($this->repo->existsByUsername($username, $excludeId)) {
            $errors['username'] = 'Cet identifiant est déjà utilisé';
        } elseif (!preg_match('/^[a-zA-Z0-9.\-_]{2,100}$/', $username)) {
            // Audit #35 — validation format username. Before this fix, any string
            // was accepted (including spaces, special chars, etc.). Now enforces
            // the same pattern as the HTML <input pattern> attribute.
            $errors['username'] = 'L\'identifiant ne doit contenir que des lettres, chiffres, points, tirets et underscores (2 à 100 caractères).';
        }

        /** @var string */
        $roleVal = $input['role'] ?? '';
        $role = trim($roleVal);
        if (UserRole::tryFrom($role) === null) {
            $errors['role'] = 'Rôle invalide.';
        }

        /** @var string */
        $siteIdVal = $input['site_id'] ?? '0';
        $siteId = (int) $siteIdVal;
        if (!isNoSiteMode($this->repo->getPdo()) && $siteId > 0) {
            $site = SiteRepository::instance()->findById($siteId);
            if ($site === null) {
                $errors['site_id'] = 'Site invalide.';
            }
        }

        /** @var string */
        $emailVal = $input['email'] ?? '';
        $email = trim($emailVal);
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Adresse email invalide.';
        }

        return $errors;
    }

    public function canDeactivate(int $id): bool
    {
        $user = $this->repo->findById($id);
        if ($user === null) {
            return false;
        }
        if ($user['role'] === UserRole::Superviseur->value && $this->repo->countActiveSuperviseurs() <= 1) {
            return false;
        }
        return true;
    }

    /**
     * @param array{role: string} $user
     * @return array<string, string>
     */
    public function canDemote(int $id, string $newRole, array $user): array
    {
        $errors = [];
        if ($user['role'] === UserRole::Superviseur->value && $newRole !== UserRole::Superviseur->value) {
            if ($this->repo->countActiveSuperviseurs() <= 1) {
                $errors['role'] = 'Impossible de rétrograder le dernier superviseur actif.';
            }
        }
        return $errors;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // GDPR
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @return UserArray|null
     */
    public function exportData(int $id): array
    {
        return $this->repo->exportData($id);
    }

    public function anonymize(int $id, int $currentUserId): bool
    {
        if ($currentUserId === $id) {
            throw new RuntimeException('Vous ne pouvez pas anonymiser votre propre compte.');
        }

        $user = $this->repo->findById($id);
        if ($user === null) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        if (!$this->canDeactivate($id)) {
            throw new RuntimeException('Impossible d\'anonymiser le dernier superviseur actif.');
        }

        $result = $this->repo->anonymize($id);

        // Audit #9 + #22 — invalidate all the user's active sessions.
        $this->invalidateUserSessions($id);

        return $result;
    }
}
