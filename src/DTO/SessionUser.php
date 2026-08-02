<?php

namespace App\DTO;

use Error;
use ArrayAccess;

/**
 * SessionUser — immutable DTO representing the authenticated user's session data.
 *
 * Implements ArrayAccess for backward compatibility with code that still
 * accesses user data as $user['nom'] instead of $user->nom.
 * This will be removed once all callers are migrated to property access.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class SessionUser implements ArrayAccess
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly ?string $email,
        public readonly string $role,
        public readonly ?int $siteId,
        public readonly int $isActive,
        public readonly string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $siteCode,
        public readonly ?string $siteNom,
        public readonly ?string $siteChosenAt,
        public readonly ?string $sessionsInvalidBefore,
    ) {}

    /**
     * Hydrate from DB row (UserRepository result).
     *
     * @param array{id: mixed, username: mixed, nom: mixed, prenom: mixed, email: mixed, role: mixed, site_id: mixed, is_active: mixed, created_at: mixed, updated_at: mixed, site_code: mixed, site_nom: mixed, site_chosen_at: mixed, sessions_invalid_before: mixed} $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            username: (string) $row['username'],
            nom: (string) $row['nom'],
            prenom: (string) $row['prenom'],
            email: $row['email'] !== null ? (string) $row['email'] : null,
            role: (string) $row['role'],
            siteId: $row['site_id'] !== null ? (int) $row['site_id'] : null,
            isActive: (int) $row['is_active'],
            createdAt: (string) $row['created_at'],
            updatedAt: $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
            siteCode: $row['site_code'] !== null ? (string) $row['site_code'] : null,
            siteNom: $row['site_nom'] !== null ? (string) $row['site_nom'] : null,
            siteChosenAt: $row['site_chosen_at'] !== null ? (string) $row['site_chosen_at'] : null,
            sessionsInvalidBefore: $row['sessions_invalid_before'] !== null ? (string) $row['sessions_invalid_before'] : null,
        );
    }

    /**
     * For $_SESSION serialization.
     *
     * @return array{id: int, username: string, nom: string, prenom: string, email: string|null, role: string, siteId: int|null, isActive: int, createdAt: string, updatedAt: string|null, siteCode: string|null, siteNom: string|null, siteChosenAt: string|null, sessionsInvalidBefore: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'role' => $this->role,
            'siteId' => $this->siteId,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'siteCode' => $this->siteCode,
            'siteNom' => $this->siteNom,
            'siteChosenAt' => $this->siteChosenAt,
            'sessionsInvalidBefore' => $this->sessionsInvalidBefore,
        ];
    }

    /**
     * Hydrate from $_SESSION (toArray() output or legacy snake_case array).
     *
     * @param array{id?: int|string, username?: string, nom?: string, prenom?: string, email?: string|null, role?: string, siteId?: int|string|null, site_id?: int|string|null, isActive?: int|string, is_active?: int|string, createdAt?: string, created_at?: string, updatedAt?: string|null, updated_at?: string|null, siteCode?: string|null, site_code?: string|null, siteNom?: string|null, site_nom?: string|null, siteChosenAt?: string|null, site_chosen_at?: string|null, sessionsInvalidBefore?: string|null, sessions_invalid_before?: string|null} $data
     */
    public static function fromSession(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? $data['id'] ?? 0),
            username: (string) ($data['username'] ?? ''),
            nom: (string) ($data['nom'] ?? ''),
            prenom: (string) ($data['prenom'] ?? ''),
            email: $data['email'] ?? null,
            role: (string) ($data['role'] ?? ''),
            siteId: isset($data['siteId']) ? (int) $data['siteId'] : (isset($data['site_id']) ? (int) $data['site_id'] : null),
            isActive: (int) ($data['isActive'] ?? $data['is_active'] ?? 1),
            createdAt: (string) ($data['createdAt'] ?? $data['created_at'] ?? ''),
            updatedAt: $data['updatedAt'] ?? $data['updated_at'] ?? null,
            siteCode: $data['siteCode'] ?? $data['site_code'] ?? null,
            siteNom: $data['siteNom'] ?? $data['site_nom'] ?? null,
            siteChosenAt: $data['siteChosenAt'] ?? $data['site_chosen_at'] ?? null,
            sessionsInvalidBefore: $data['sessionsInvalidBefore'] ?? $data['sessions_invalid_before'] ?? null,
        );
    }

    /**
     * Create a new SessionUser with a different role (for impersonation/promotion).
     */
    public function withRole(string $newRole): self
    {
        return new self(
            id: $this->id,
            username: $this->username,
            nom: $this->nom,
            prenom: $this->prenom,
            email: $this->email,
            role: $newRole,
            siteId: $this->siteId,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            siteCode: $this->siteCode,
            siteNom: $this->siteNom,
            siteChosenAt: $this->siteChosenAt,
            sessionsInvalidBefore: $this->sessionsInvalidBefore,
        );
    }

    /**
     * Create a SessionUser from a minimal associative array (for tests, handlers, etc.).
     * Fills in sensible defaults for any missing fields.
     *
     * @param array{id?: int|string, username?: string, nom?: string, prenom?: string, email?: string|null, role?: string, site_id?: int|string|null, is_active?: int|string, created_at?: string, updated_at?: string|null, site_code?: string|null, site_nom?: string|null, site_chosen_at?: string|null, sessions_invalid_before?: string|null} $overrides
     *
     * @phpstan-ignore shipmonk.deadMethod
     */
    public static function fromArray(array $overrides = []): self
    {
        $defaults = [
            'id' => 0,
            'username' => '',
            'nom' => '',
            'prenom' => '',
            'email' => null,
            'role' => 'agent',
            'site_id' => null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null,
            'site_code' => null,
            'site_nom' => null,
            'site_chosen_at' => null,
            'sessions_invalid_before' => null,
        ];
        return self::fromRow(array_merge($defaults, $overrides));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // ArrayAccess — backward compat for $user['nom'] style access.
    // Maps snake_case keys to camelCase properties.
    // TODO: remove once all callers use property access ($user->nom).
    // ═══════════════════════════════════════════════════════════════════════════════

    /** @param string $offset */
    public function offsetExists(mixed $offset): bool
    {
        return match ($offset) {
            'id', 'username', 'nom', 'prenom', 'email', 'role',
            'site_id', 'is_active', 'created_at', 'updated_at',
            'site_code', 'site_nom', 'site_chosen_at', 'sessions_invalid_before',
            'siteId', 'isActive', 'createdAt', 'updatedAt',
            'siteCode', 'siteNom', 'siteChosenAt', 'sessionsInvalidBefore' => true,
            default => false,
        };
    }

    /** @param string $offset */
    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'id', 'id' => $this->id,
            'username' => $this->username,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'role' => $this->role,
            'site_id', 'siteId' => $this->siteId,
            'is_active', 'isActive' => $this->isActive,
            'created_at', 'createdAt' => $this->createdAt,
            'updated_at', 'updatedAt' => $this->updatedAt,
            'site_code', 'siteCode' => $this->siteCode,
            'site_nom', 'siteNom' => $this->siteNom,
            'site_chosen_at', 'siteChosenAt' => $this->siteChosenAt,
            'sessions_invalid_before', 'sessionsInvalidBefore' => $this->sessionsInvalidBefore,
            default => null,
        };
    }

    /** @param string $offset */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new Error('Cannot modify readonly property ' . $offset);
    }

    /** @param string $offset */
    public function offsetUnset(mixed $offset): never
    {
        throw new Error('Cannot unset readonly property ' . $offset);
    }
}
