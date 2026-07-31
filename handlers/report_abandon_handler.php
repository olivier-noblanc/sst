<?php

use App\Services\HttpService;
use App\Services\SessionService;

/**
 * Report Abandon Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\Services\ReportService;

$http = new HttpService();
$session = SessionService::getInstance();

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$report = fetchReportOrRedirect($reportUuid);
$user = $session->getUserSession();
$userId = (int) ($session->getUserSession()['id'] ?? 0);
$type = $report->type;

requireReportOwnership($report, $userId, $reportUuid, 'abandonner');
requireReportEditable($report, $reportUuid, 'abandonné');

$pdo = getDB();

try {
    $service = getContainer()->get(ReportService::class);
    $abandoned = $service->abandon($reportUuid, $userId);

    if ($abandoned) {
        auditLog($pdo, 'report', 'abandon', 'Signalement abandonné : ' . $report->reference, null, 'report', ['reference' => $report->reference], $reportUuid);

        // Notify supervisors of the site
        require_once __DIR__ . '/../src/mail.php';
        // AGENTS.md §"Mode sans site" : ne jamais coercer null → 0.
        // getNotificationRecipients accepte ?int et skipe la requête per-site
        // quand null (retourne les destinataires globaux uniquement).
        $recipients = getNotificationRecipients($pdo, $report->siteId);
        if (!empty($recipients)) {
            $registryLabel = getRegistryShortLabel($type);
            $subject = "Signalement abandonné $registryLabel — {$report->reference}";
            $body = '<html><body>';
            $body .= '<h2>Signalement abandonné</h2>';
            $body .= '<p><strong>Référence :</strong> ' . e((string) $report->reference) . '</p>';
            $body .= "<p><strong>Registre :</strong> $registryLabel</p>";
            $body .= '<p><strong>Objet :</strong> ' . e((string) $report->objet) . '</p>';
            $body .= '<p><strong>Déclarant :</strong> ' . e((string) $report->declarantPrenom . ' ' . (string) $report->declarantNom) . '</p>';
            $body .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
            $body .= '</body></html>';
            foreach ($recipients as $email) {
                sendMail($email, $subject, $body);
            }
        }

        $session->setFlash('success', 'Signalement ' . e((string) $report->reference) . ' abandonné.');
        $http->redirect($http->url('report_list', ['type' => $type]));
    } else {
        $session->setFlash('error', 'Impossible d\'abandonner le signalement. Il a peut-être été modifié entre-temps. (uuid=' . e($reportUuid) . ', etat=' . e($report->etat) . ')');
        $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
    }
} catch (RuntimeException $e) {
    // @silent-ok: handler boundary — flash error shown to the user, standard pattern.
    $session->setFlash('error', e($e->getMessage()));
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}
