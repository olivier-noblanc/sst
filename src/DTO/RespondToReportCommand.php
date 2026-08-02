<?php

/** RespondToReportCommand — DTO pour la réponse à un signalement. */

namespace App\DTO;

use App\Enum\ReportState;

class RespondToReportCommand
{
    public function __construct(
        public readonly string $reponse,
        public readonly ReportState $nouvelEtat,
        public readonly ?AttachmentData $attachment = null,
    ) {}
}
