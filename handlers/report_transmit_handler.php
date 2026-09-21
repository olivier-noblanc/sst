<?php

/**
 * Report Transmit Handler — Application SST DREETS BFC
 *
 * Action MANUELLE du superviseur : transmettre un signalement aux membres
 * CSA/CHSCT par e-mail, via l'outbox transactionnelle. Décision métier
 * (Oracle) : aucune transmission automatique à la création — consent_syndicat
 * est une consigne pour le superviseur, qui décide seul de déclencher l'envoi.
 *
 * Déduplication garantie par l'outbox (signalement × destinataire) : rejouer
 * l'action ne crée pas de doublon. L'action est auditée (catégorie 'report',
 * action 'transmit').
 *
 * Access: superviseur only (RoleMiddleware) + CSRF (CsrfMiddleware) — câblage
 * vérifié par RouterRoleMappingTest. Erreurs : crash hard, jamais silencieux.
 */

use App\Repository\AuditRepository;
use App\Repository\ReportRepository;
use App\Services\HttpService;
use App\Services\NotificationService;
use App\Services\SessionService;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();

$user = $session->getUserSession();
if ($user === null) {
    $session->setFlash('error', 'Accès refusé.');
    $http->redirect($http->url('home'));
    exit;
}

$uuid = (string) ($_POST['uuid'] ?? '');
$report = ReportRepository::instance()->findById($uuid);
if ($report === null) {
    $session->setFlash('error', 'Signalement introuvable.');
    $http->redirect($http->url('home'));
    exit;
}

$enqueued = getContainer()->get(NotificationService::class)->notifyReportTransmitted($uuid);

AuditRepository::instance()->log(
    category: 'report',
    action: 'transmit',
    details: 'Transmission manuelle aux organisations syndicales — ' . (string) $report->reference,
    targetType: 'report',
    targetUuid: $uuid,
    context: ['reference' => (string) $report->reference, 'enqueued' => $enqueued],
);

if ($enqueued > 0) {
    $session->setFlash(
        'success',
        $enqueued . ' transmission(s) aux organisations syndicales programmée(s) pour envoi.'
    );
} else {
    $session->setFlash(
        'info',
        'Aucune nouvelle transmission : les destinataires ont déjà été notifiés ou aucun membre CSA/CHSCT joignable n\'est configuré.'
    );
}

$http->redirect($http->url('report_view', ['uuid' => $uuid]));
