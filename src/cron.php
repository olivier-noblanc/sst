<?php

use App\Services\CronService;

/**
 * Lazy Cron — Application SST DREETS BFC
 *
 * Point d'entrée procédural pour le cron lazy.
 * Délègue à CronService pour l'exécution réelle.
 *
 * Les tâches de maintenance sont déclenchées
 * paresseusement (lazy) lors de la connexion d'un utilisateur.
 *
 * Tâches planifiées :
 *   1. check_delays   — Alerte superviseurs si signalements en retard
 *   2. anonymize      — Anonymisation RGPD des signalements anciens
 *   3. cleanup        — Purge des invitations agent expirées
 *   4. session_gc     — Purge des sessions expirées (>24h)
 *   5. audit_purge    — Purge des logs d'audit > 180 jours
 *   6. access_purge   — Purge des logs de consultation > 2 ans
 */

require_once __DIR__ . '/bootstrap_services.php';

/**
 * Wrapper procédural pour CronService (compatibilité avec code existant).
 */
function runLazyCron(): void
{
    $cronService = getContainer()->get(CronService::class);
    $cronService->runLazyCron();
}
