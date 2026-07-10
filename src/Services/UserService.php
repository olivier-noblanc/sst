<?php
/** UserService — Couche métier pour la gestion des utilisateurs. */

namespace App\Services;

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
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        $demoteErrors = $this->canDemote($id, $cmd->role, $user);
        if (!empty($demoteErrors)) {
            throw new RuntimeException(implode(' ', $demoteErrors));
        }

        $roleChanged = $user['role'] !== $cmd->role;
        $this->repo->update($id, $cmd->toArray());

        if ($currentUserId === $id) {
            refreshCurrentUser($this->repo->getPdo());
        }

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

        return true;
    }

    public function deactivate(int $id, int $currentUserId): bool
    {
        if ($currentUserId === $id) {
            throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user = $this->repo->findById($id);
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        if (!$this->canDeactivate($id)) {
            throw new RuntimeException('Impossible de désactiver le dernier superviseur actif.');
        }

        $result = $this->repo->deactivate($id);

        $this->events->dispatch('user.deactivated', [
            'user' => $user,
            'pdo' => $this->repo->getPdo(),
        ]);

        return $result;
    }

    public function reactivate(int $id): bool
    {
        $user = $this->repo->findById($id);
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }
        if ($user['is_active']) {
            throw new RuntimeException('Cet utilisateur est déjà actif.');
        }
        $result = $this->repo->reactivate($id);

        $this->events->dispatch('user.reactivated', [
            'user' => $user,
            'pdo' => $this->repo->getPdo(),
        ]);

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Queries
    // ═══════════════════════════════════════════════════════════════════════════════

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->repo->findByUsername($username);
    }

    public function findAll(int $siteId = 0, bool $active = true): array
    {
        return $this->repo->findAll($siteId, $active);
    }

    public function countActive(): int
    {
        return $this->repo->countActive();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Validation
    // ═══════════════════════════════════════════════════════════════════════════════

    public function validate(array $input, int $excludeId = 0): array
    {
        $errors = [];

        $nom = trim($input['nom'] ?? '');
        if (empty($nom)) {
            $errors['nom'] = 'Le nom est requis.';
        }

        $prenom = trim($input['prenom'] ?? '');
        if (empty($prenom)) {
            $errors['prenom'] = 'Le prénom est requis.';
        }

        $username = trim($input['username'] ?? '');
        if (empty($username)) {
            $errors['username'] = 'L\'identifiant est requis.';
        } elseif ($this->repo->existsByUsername($username, $excludeId)) {
            $errors['username'] = 'Cet identifiant est déjà utilisé';
        }

        $role = trim($input['role'] ?? '');
        if (!in_array($role, [ROLE_AGENT, ROLE_SUPERVISEUR, ROLE_CHSCT])) {
            $errors['role'] = 'Rôle invalide.';
        }

        $siteId = (int) ($input['site_id'] ?? 0);
        if (!isNoSiteMode($this->repo->getPdo()) && $siteId > 0) {
            $site = getSiteById($this->repo->getPdo(), $siteId);
            if (!$site) {
                $errors['site_id'] = 'Site invalide.';
            }
        }

        $email = trim($input['email'] ?? '');
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse email invalide.';
        }

        return $errors;
    }

    public function canDeactivate(int $id): bool
    {
        $user = $this->repo->findById($id);
        if (!$user) {
            return false;
        }
        if ($user['role'] === ROLE_SUPERVISEUR && $this->repo->countActiveSuperviseurs() <= 1) {
            return false;
        }
        return true;
    }

    public function canDemote(int $id, string $newRole, array $user): array
    {
        $errors = [];
        if ($user['role'] === ROLE_SUPERVISEUR && $newRole !== ROLE_SUPERVISEUR) {
            if ($this->repo->countActiveSuperviseurs() <= 1) {
                $errors['role'] = 'Impossible de rétrograder le dernier superviseur actif.';
            }
        }
        return $errors;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // GDPR
    // ═══════════════════════════════════════════════════════════════════════════════

    public function exportData(int $id): array
    {
        return $this->repo->exportData($id);
    }

    public function anonymize(int $id): bool
    {
        return $this->repo->anonymize($id);
    }
}
