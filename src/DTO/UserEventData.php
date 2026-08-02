<?php

declare(strict_types=1);

namespace App\DTO;

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
        public ?string $oldRole = null,
        public ?string $newRole = null,
        public ?\PDO $pdo = null,
    ) {}

    /**
     * Factory for role change events.
     */
    public static function forRoleChange(SessionUser $user, string $oldRole, string $newRole, ?\PDO $pdo = null): self
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
    public static function forUser(SessionUser $user, ?\PDO $pdo = null): self
    {
        return new self(
            user: $user,
            userId: $user->id,
            pdo: $pdo,
        );
    }

    /**
     * Convenience accessor — always returns an int userId (0 if null).
     */
    public function userIdInt(): int
    {
        return $this->userId ?? ($this->user?->id ?? 0);
    }

    /**
     * Convenience accessor — always returns a string oldRole (empty if null).
     */
    public function oldRoleString(): string
    {
        return $this->oldRole ?? '';
    }

    /**
     * Convenience accessor — always returns a string newRole (empty if null).
     */
    public function newRoleString(): string
    {
        return $this->newRole ?? '';
    }
}
