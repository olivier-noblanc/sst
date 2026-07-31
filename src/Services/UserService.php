<?php

namespace App\Services;

use App\Repository\SiteRepository;
use App\Enum\UserRole;
use RuntimeException;
use App\Repository\UserRepository;
use App\Event\EventDispatcher;
use App\DTO\CreateUserCommand;
use App\DTO\UpdateUserCommand;
use App\DTO\SessionUser;

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
        $userId = $this->repo->create($cmd);
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

        $demoteErrors = $this->canDemote($id, $cmd->role, $user->role);
        if (!empty($demoteErrors)) {
            throw new RuntimeException(implode(' ', $demoteErrors));
        }

        $roleChanged = $user->role !== $cmd->role;
        // Audit #29 — test le retour du repo.update. Avant ce fix, l'event
        // 'user.updated' était dispatché même si l'UPDATE était no-op.
        $updateResult = $this->repo->update($id, $cmd);

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
                    'oldRole' => $user->role,
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
        if ($user->isActive === 1) {
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
     * @return SessionUser|null
     */
    public function findById(int $id): ?SessionUser
    {
        return $this->repo->findById($id);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Validation
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @param CreateUserCommand|UpdateUserCommand $command
     * @return array<string, string>
     */
    public function validate(CreateUserCommand|UpdateUserCommand $command, int $excludeId = 0): array
    {
        $errors = [];

        if (empty(trim($command->nom))) {
            $errors['nom'] = 'Le nom est requis.';
        }

        if (empty(trim($command->prenom))) {
            $errors['prenom'] = 'Le prénom est requis.';
        }

        $username = trim($command->username);
        if (empty($username)) {
            $errors['username'] = 'L\'identifiant est requis.';
        } elseif ($this->repo->existsByUsername($username, $excludeId)) {
            $errors['username'] = 'Cet identifiant est déjà utilisé';
        } elseif (!preg_match('/^[a-zA-Z0-9.\-_]{2,100}$/', $username)) {
            $errors['username'] = 'L\'identifiant ne doit contenir que des lettres, chiffres, points, tirets et underscores (2 à 100 caractères).';
        }

        if (UserRole::tryFrom(trim($command->role)) === null) {
            $errors['role'] = 'Rôle invalide.';
        }

        if (!isNoSiteMode($this->repo->getPdo()) && $command->siteId->toSql() !== null) {
            $site = SiteRepository::instance()->findById($command->siteId->toSql());
            if ($site === null) {
                $errors['site_id'] = 'Site invalide.';
            }
        }

        $email = trim((string) $command->email);
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
        if ($user->role === UserRole::Superviseur->value && $this->repo->countActiveSuperviseurs() <= 1) {
            return false;
        }
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function canDemote(int $id, string $newRole, string $currentRole): array
    {
        $errors = [];
        if ($currentRole === UserRole::Superviseur->value && $newRole !== UserRole::Superviseur->value) {
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
     * @return array{user: array{id: int, username: string, nom: string, prenom: string, email: string|null, role: string, siteId: int|null, isActive: int, createdAt: string, updatedAt: string|null, siteCode: string|null, siteNom: string|null, siteChosenAt: string|null, sessionsInvalidBefore: string|null}, reports_count: int, reports: list<array{uuid: string, reference: string, type: string, objet: string, description: string, date_evenement: string, heure_evenement: string|null, lieu: string|null, is_confidential: int, etat: string, created_at: string}>, responses_count: int, responses: list<array{report_uuid: string, reponse: string|null, nouvel_etat: string|null, created_at: string}>}
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
