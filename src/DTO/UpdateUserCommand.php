<?php
/** UpdateUserCommand — DTO pour l'édition d'un utilisateur. */

namespace App\DTO;

class UpdateUserCommand
{
    public function __construct(
        public readonly string $username,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $role,
        public readonly int $siteId,
        public readonly ?string $email,
    ) {}

    public static function fromPost(array $post): self
    {
        return new self(
            username: trim($post['username'] ?? ''),
            nom: trim($post['nom'] ?? ''),
            prenom: trim($post['prenom'] ?? ''),
            role: trim($post['role'] ?? ROLE_AGENT),
            siteId: (int) ($post['site_id'] ?? 0),
            email: !empty(trim($post['email'] ?? '')) ? trim((string) $post['email']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'role' => $this->role,
            'site_id' => $this->siteId,
            'email' => $this->email,
        ];
    }
}
