<?php

declare(strict_types=1);

namespace App\DTO;

use PDO;

/**
 * UserEventData — DTO pour les events liés aux utilisateurs.
 *
 * Remplace les array<string, mixed> dans EventDispatcher::dispatch() pour
 * les events user.created, user.updated, user.role_changed, user.deactivated, etc.
 */
final readonly class UserEventData
{
    public function __construct(
        public ?SessionUser $user = null,
        public ?int $userId = null,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (kept for future event listeners — le listener de notification role_changed a été retiré : chemin unique = handler) */
        public ?string $oldRole = null,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (kept for future event listeners — idem $oldRole) */
        public ?string $newRole = null,
        /** @phpstan-ignore shipmonk.deadProperty.neverRead (kept for DB access in listeners) */
        public ?PDO $pdo = null,
    ) {}

    /**
     * Factory for role change events.
     */
    public static function forRoleChange(SessionUser $user, string $oldRole, string $newRole, ?PDO $pdo = null): self
    {
        return new self(
            user: $user,
            userId: $user->id,
            oldRole: $oldRole,
            newRole: $newRole,
            pdo: $pdo,
        );
    }

    /**
     * Factory for simple user events (created, updated, deactivated, etc.).
     */
    public static function forUser(?SessionUser $user, ?PDO $pdo = null): self
    {
        return new self(
            user: $user,
            userId: $user?->id,
            pdo: $pdo,
        );
    }

    /**
     * Convenience accessor — always returns an int userId (0 if null).
     *
     * Ancré par DtoDefaultValuesMutationTest (mutants Infection) et réservé
     * aux futurs listeners d'événements user.* — même justification que les
     * propriétés $motif/$pdo ci-dessus.
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public function userIdInt(): int
    {
        return $this->userId ?? ($this->user !== null ? $this->user->id : 0);
    }
}
