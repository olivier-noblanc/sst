<?php

/**
 * Outbox Retry Handler — Application SST DREETS BFC
 *
 * POST handler : requalifie les messages outbox en échec définitif
 * (status = failed) pour un nouvel essai (failed → pending, attempts remis à 0).
 * Aucune ligne n'est supprimée et l'identité logique (dedup_key) est préservée :
 * la requalification est réversible et sans perte. Le prochain drain outbox
 * (lazy cron, toutes les 5 min) réclamera les messages requalifiés.
 *
 * Access: superviseur only (RoleMiddleware) + CSRF (CsrfMiddleware), comme les
 * autres actions d'administration SMTP.
 *
 * Résultat local via flash, puis retour à l'onglet SMTP (là où la bannière
 * d'incident renvoie). Pas de try/catch : une erreur DB remonte (crash hard,
 * jamais d'échec silencieux — AGENTS.md).
 */

use App\Repository\AuditRepository;
use App\Repository\EmailOutboxRepository;
use App\Services\HttpService;
use App\Services\SessionService;

$http = new HttpService();
$session = SessionService::getInstance();

$requeued = EmailOutboxRepository::instance()->requeueFailed();

if ($requeued > 0) {
    AuditRepository::instance()->log(
        category: 'config',
        action: 'outbox_retry',
        details: "Requalification manuelle outbox : {$requeued} message(s) en échec reprogrammé(s) pour un nouvel envoi",
        targetType: 'config',
        context: ['requeued' => $requeued],
    );
    $session->setFlash(
        'success',
        "{$requeued} message(s) en échec ont été reprogrammés pour un nouvel envoi. Le prochain envoi automatique les traitera."
    );
} else {
    $session->setFlash('info', 'Aucun message en échec à reprogrammer.');
}

$http->redirect($http->url('settings', ['tab' => 'smtp']));
