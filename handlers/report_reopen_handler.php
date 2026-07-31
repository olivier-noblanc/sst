<?php

/**
 * Report Reopen Handler — Thin controller delegating to ReportService.
 */
use App\Services\HttpService;
use App\Services\SessionService;
use App\Repository\UserRepository;
use App\Repository\ReportRepository;
use App\DTO\ReopenReportCommand;
use App\Services\ReportService;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$motifReouverture = trim((string) ($_POST['motif_reouverture'] ?? ''));

if (mb_strlen($motifReouverture, 'UTF-8') < 10) {
    $session->setFlash('error', 'Le motif de réouverture doit contenir au moins 10 caractères.');
    setFormData($_POST);
    $http->redirect($http->url('report_reopen', ['uuid' => $reportUuid]));
}

$report = fetchReportOrRedirect($reportUuid);
$userId = (int) ($session->getUserSession()['id'] ?? 0);

try {
    $cmd = new ReopenReportCommand(motif: $motifReouverture);
    $service = getContainer()->get(ReportService::class);
    $result = $service->reopen($reportUuid, $cmd, $userId);

    if ($result) {
        auditLog(getDB(), 'report', 'reopen', 'Signalement réouvert : ' . (string) $report->reference . ' — Motif : ' . $motifReouverture, null, 'report', ['reference' => $report->reference, 'motif' => $motifReouverture], $reportUuid);

        // Notify declarant + linked agents (non-blocking)
        require_once __DIR__ . '/../src/mail.php';
        $pdo = getDB();
        $registryLabel = getRegistryShortLabel($report->type);
        $declarant = UserRepository::instance()->findById($report->declarantId);
        if ($declarant !== null && !empty($declarant['email']) && $report->declarantId !== $userId) {
            /** @var array<string, string> $declarant */
            $subject = "Signalement réouvert $registryLabel — {$report->reference}";
            $body = '<html><body>';
            $body .= '<h2>Votre signalement a été réouvert</h2>';
            $body .= '<p><strong>Référence :</strong> ' . e((string) $report->reference) . '</p>';
            $body .= '<p><strong>Motif :</strong> ' . e($motifReouverture) . '</p>';
            $body .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
            $body .= '</body></html>';
            sendMail($declarant['email'], $subject, $body);
        }
        $linkedAgents = ReportRepository::instance()->getLinkedAgents($reportUuid);
        foreach ($linkedAgents as $linkedAgent) {
            /** @var array<string, string> $linkedAgent */
            if (!empty($linkedAgent['email']) && $linkedAgent['email'] !== ($declarant['email'] ?? '')) {
                $linkedSubject = "Signalement réouvert $registryLabel — {$report->reference}";
                $linkedBody = renderEmailBody(
                    'Signalement réouvert',
                    '<p>Bonjour ' . e((string) ($linkedAgent['prenom'] ?? '')) . ',</p>'
                    . '<p>Le signalement <strong>' . e((string) $report->reference) . '</strong> auquel vous êtes rattaché(e) a été réouvert.</p>'
                    . '<p><strong>Motif :</strong> ' . e($motifReouverture) . '</p>'
                    . '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>'
                );
                sendMail($linkedAgent['email'], $linkedSubject, $linkedBody);
            }
        }

        $session->setFlash('success', 'Signalement ' . e((string) $report->reference) . ' réouvert avec succès.');
    } else {
        $session->setFlash('error', 'Ce signalement a été modifié entre-temps. Veuillez réessayer.');
    }
} catch (RuntimeException $e) {
    // @silent-ok: handler boundary — flash error shown to the user, standard pattern.
    $session->setFlash('error', e($e->getMessage()));
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}

$http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
