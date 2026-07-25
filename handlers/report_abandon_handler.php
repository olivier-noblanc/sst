<?php

/**
 * Report Abandon Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

/** @var array<string, string> $_POST */

use App\Services\ReportService;

$http = new \App\Services\HttpService();
$session = \App\Services\SessionService::getInstance();

$reportUuid = trim((string) ($_POST['report_uuid'] ?? ''));
$report = fetchReportOrRedirect($reportUuid);

/** @var array<string, string> $report */

$user = $session->getUserSession();
$userId = (int)($session->getUserSession()['id'] ?? 0);
$type = (string) ($report['type'] ?? '');

requireReportOwnership($report, $userId, $reportUuid, 'abandonner');
requireReportEditable($report, $reportUuid, 'abandonné');

$pdo = getDB();

try {
    $service = getContainer()->get(ReportService::class);
    $abandoned = $service->abandon($reportUuid, $userId);

    if ($abandoned) {
        auditLog($pdo, 'report', 'abandon', 'Signalement abandonné : ' . (string) $report['reference'], null, 'report', ['reference' => $report['reference'] ?? ''], $reportUuid);

        // Notify supervisors of the site
        require_once __DIR__ . '/../src/mail.php';
        $siteId = (int) ($report['site_id'] ?? 0);
        $recipients = getNotificationRecipients($pdo, $siteId);
        if (!empty($recipients)) {
            $registryLabel = getRegistryShortLabel($type);
            $subject = "Signalement abandonné $registryLabel — {$report['reference']}";
            $body = '<html><body>';
            $body .= '<h2>Signalement abandonné</h2>';
            $body .= '<p><strong>Référence :</strong> ' . e((string) $report['reference']) . '</p>';
            $body .= "<p><strong>Registre :</strong> $registryLabel</p>";
            $body .= '<p><strong>Objet :</strong> ' . e((string) $report['objet']) . '</p>';
            $body .= '<p><strong>Déclarant :</strong> ' . e((string) $report['declarant_prenom'] . ' ' . (string) $report['declarant_nom']) . '</p>';
            $body .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
            $body .= '</body></html>';
            foreach ($recipients as $email) {
                sendMail($email, $subject, $body);
            }
        }

        $session->setFlash('success', 'Signalement ' . e((string) $report['reference']) . ' abandonné.');
        $http->redirect($http->url('report_list', ['type' => $type]));
    } else {
        $session->setFlash('error', 'Impossible d\'abandonner le signalement. Il a peut-être été modifié entre-temps. (uuid=' . e($reportUuid) . ', etat=' . e((string) ($report['etat'] ?? '')) . ')');
        $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
    }
} catch (RuntimeException $e) {
    $session->setFlash('error', e($e->getMessage()));
    $http->redirect($http->url('report_view', ['uuid' => $reportUuid]));
}
