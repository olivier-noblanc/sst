<?php

/** RespondToReportCommand — DTO pour la réponse à un signalement. */

namespace App\DTO;

use App\Enum\ReportState;

class RespondToReportCommand
{
    /** @param array{blob: ?string, name: ?string, mime: ?string} $attachment */
    public function __construct(
        public readonly string $reponse,
        public readonly ReportState $nouvelEtat,
        public readonly array $attachment = ['blob' => null, 'name' => null, 'mime' => null],
    ) {}
}
