<?php
/** ReopenReportCommand — DTO pour la réouverture d'un signalement. */

namespace App\DTO;

class ReopenReportCommand
{
    public function __construct(
        public readonly string $motif,
    ) {}
}
