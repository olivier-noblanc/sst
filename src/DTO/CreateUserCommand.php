<?php

/** CreateUserCommand — DTO pour la création d'un utilisateur. */

namespace App\DTO;

use App\Enum\UserRole;

class CreateUserCommand
{
    public function __construct(
        public readonly string $username,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $role,
        public readonly SiteId $siteId,
        public readonly ?string $email,
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
            email: !empty(trim($post['email'] ?? '')) ? trim((string) $post['email']) : null,
        );
    }

    /**
     * @return array{
     *     username: string,
     *     nom: string,
     *     prenom: string,
     *     role: string,
     *     site_id: ?int,
     *     email: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'role' => $this->role,
            'site_id' => $this->siteId->toSql(),
            'email' => $this->email,
        ];
    }
}
