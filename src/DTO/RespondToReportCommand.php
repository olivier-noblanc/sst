<?php

/** RespondToReportCommand — DTO pour la réponse à un signalement. */

namespace App\DTO;

class RespondToReportCommand
{
    /**
     * @param array<string, mixed> $attachment
     */
    public function __construct(
        public readonly string $reponse,
        public readonly string $nouvelEtat,
        public readonly array $attachment = [],
    ) {}

    /** @param array<string, string> $post */
    public static function fromPost(array $post): self
    {
        return new self(
            reponse: trim($post['reponse'] ?? ''),
            nouvelEtat: $post['nouvel_etat'] ?? ETAT_EN_COURS,
        );
    }
}
