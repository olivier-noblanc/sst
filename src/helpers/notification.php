<?php

use App\Services\NotificationService;

/**
 * Notification Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\NotificationService.
 */

/**
 * Date (UTC, 'Y-m-d H:i:s') de la transmission réussie d'un signalement vers
 * le rôle CSA/CHSCT, ou null si elle n'a jamais été mise en file.
 *
 * Preuve durable : les lignes outbox `ReportTransmitted` (dédupliquées par
 * signalement × destinataire, jamais purgées en fonctionnement normal).
 */
function reportTransmissionDate(string $reportUuid): ?string
{
    return getContainer()->get(NotificationService::class)->findReportTransmissionDate($reportUuid);
}
