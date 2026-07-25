<?php

/** RespondToReportCommand — DTO pour la réponse à un signalement. */

namespace App\DTO;

use App\Enum\ReportState;

class RespondToReportCommand
{
    /**
     * @param array<string, mixed> $attachment
     */
    public function __construct(
        public readonly string $reponse,
        public readonly ReportState $nouvelEtat,
        public readonly array $attachment = [],
    ) {}

    /** @param array<string, string> $post */
    public static function fromPost(array $post): self
    {
        return new self(
            reponse: trim($post['reponse'] ?? ''),
            nouvelEtat: ReportState::from($post['nouvel_etat'] ?? ReportState::EnCours->value),
        );
    }
}
