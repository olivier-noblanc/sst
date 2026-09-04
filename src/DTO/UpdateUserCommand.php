<?php

/** UpdateUserCommand — DTO pour l'édition d'un utilisateur. */

namespace App\DTO;

use App\Enum\UserRole;

class UpdateUserCommand
{
    public function __construct(
        public readonly string $username,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $role,
        public readonly SiteId $siteId,
        /**
     * Email réel obligatoire (invariant users.email NOT NULL) — la validation
     * refuse le vide et la sentinelle d'anonymisation.
     */
        public readonly string $email,
    ) {}

    /** @param array<string, string> $post */
    public static function fromPost(array $post): self
    {
        return new self(
            username: trim($post['username'] ?? ''),
            nom: trim($post['nom'] ?? ''),
            prenom: trim($post['prenom'] ?? ''),
            role: trim($post['role'] ?? UserRole::Agent->value),
            siteId: SiteId::fromInput((int) ($post['site_id'] ?? 0)),
            email: trim((string) ($post['email'] ?? '')),
        );
    }
}
