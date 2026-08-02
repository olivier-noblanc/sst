<?php

/** ReopenReportCommand — DTO pour la réouverture d'un signalement. */

namespace App\DTO;

use InvalidArgumentException;

class ReopenReportCommand
{
    public function __construct(
        public readonly string $motif,
    ) {
        // Audit #15 — validation du motif (longueur minimale) déplacée du handler
        // vers le DTO. Before this fix, the validation was only in the handler
        // (report_reopen_handler.php), so any direct caller of ReportService::reopen
        // could bypass it.
        if (mb_strlen(trim($motif), 'UTF-8') < 10) {
            throw new InvalidArgumentException('Le motif de réouverture doit contenir au moins 10 caractères.');
        }
    }
}
