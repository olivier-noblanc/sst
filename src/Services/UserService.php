<?php

/** UserService — Couche métier pour la gestion des utilisateurs. */

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
        /** @var array<string, mixed> $user */

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

    // ═══════════════════════════════════════════════════════════════════════════════
    // Queries
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $result = $this->repo->findById($id);
        /** @var array<string, mixed>|null $result */
        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $result = $this->repo->findByUsername($username);
        /** @var array<string, mixed>|null $result */
        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(int $siteId = 0, bool $active = true): array
    {
        $result = $this->repo->findAll($siteId, $active);
        /** @var list<array<string, mixed>> $result */
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
     * @param array<string, mixed> $input
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
     * @param array<string, mixed> $user
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
     * @return array<string, mixed>
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

        return $this->repo->anonymize($id);
    }
}
