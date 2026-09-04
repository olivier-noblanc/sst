<?php

/**
 * Report Reopen Handler — Thin controller delegating to ReportService.
 */
use App\Enum\ReportState;
use App\Enum\UserRole;
use App\Services\HttpService;
use App\Services\SessionService;
use App\DTO\FormData;
use App\DTO\ReopenReportCommand;
use App\Services\ReportService;
use App\Services\ReportStateMachine;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$motifReouverture = trim((string) ($_POST['motif_reouverture'] ?? ''));

if (mb_strlen($motifReouverture, 'UTF-8') < 10) {
    $session->setFlash('error', 'Le motif de réouverture doit contenir au moins 10 caractères.');
    setFormData(FormData::fromPost($_POST));
    $http->redirect($http->url('report_reopen', ['uuid' => $reportUuid]));
}

$report = fetchReportOrRedirect($reportUuid);
$userId = $session->getUserSession()->id ?? 0;

// Fiabilisation (audit lifecycle) — validation PRÉALABLE contre la matrice
// (autorité) : une transition absente (ex. Nouveau→Reouvert) levait une
// InvalidArgumentException non interceptée par le catch(RuntimeException)
// → fatal. Refus contrôlé symétrique à respond + tryFrom (jamais ::from).
$reopenStateMachine = new ReportStateMachine();
$reopenCurrentState = ReportState::tryFrom($report->etat);
$reopenRole = UserRole::tryFrom((string) currentUserRole());
if ($reopenCurrentState === null || $reopenRole === null
    || !$reopenStateMachine->canTransition($reopenCurrentState, ReportState::Reouvert, $reopenRole)) {
    $session->setFlash('error', 'Ce signalement ne peut pas être réouvert (état : '
        . e(ETAT_LABELS[$report->etat] ?? $report->etat) . ').');
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}

try {
    $cmd = new ReopenReportCommand(motif: $motifReouverture);
    $service = getContainer()->get(ReportService::class);
    $result = $service->reopen($reportUuid, $cmd, $userId);

    if ($result) {
        auditLog(getDB(), 'report', 'reopen', 'Signalement réouvert : ' . (string) $report->reference . ' — Motif : ' . $motifReouverture, null, 'report', ['reference' => $report->reference, 'motif' => $motifReouverture], $reportUuid);

        // Fiabilisation (council) — notification de réouverture : chemin UNIQUE
        // via l'event 'report.reopened' dispatché par ReportService::reopen()
        // (NotificationService::notifyReportReopen → déclarant + rattachés,
        // motif transmis via ReportEventData::motif). L'ancien bloc d'envoi
        // direct ici DOUBLAIT chaque e-mail de réouverture.

        $session->setFlash('success', 'Signalement ' . e((string) $report->reference) . ' réouvert avec succès.');
    } else {
        $session->setFlash('error', 'Ce signalement a été modifié entre-temps. Veuillez réessayer.');
    }
} catch (RuntimeException|InvalidArgumentException $e) {
    // @silent-ok: handler boundary — flash error shown to the user, standard pattern.
    // Audit lifecycle — InvalidArgumentException (transition absente) gérée
    // symétriquement : race condition entre le GET et le POST.
    $session->setFlash('error', e($e->getMessage()));
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}

$http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
