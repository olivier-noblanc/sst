<?php

/**
 * Report Abandon Handler — Application SST DREETS BFC
 *
 * Thin controller: validates request → ReportService → flash + redirect.
 */

require_once __DIR__ . '/../src/bootstrap_services.php';

use App\Services\ReportService;

$reportUuid = trim($_POST['report_uuid'] ?? '');
$report = fetchReportOrRedirect($reportUuid);

$user = currentUser();
$userId = currentUserId();
$type = $report['type'];

requireReportOwnership($report, $userId, $reportUuid, 'abandonner');
requireReportEditable($report, $reportUuid, 'abandonné');

$pdo = getDB();

try {
    $service = getContainer()->get(ReportService::class);
    $abandoned = $service->abandon($reportUuid, $userId);

    if ($abandoned) {
        auditLog($pdo, 'report', 'abandon', 'Signalement abandonné : ' . $report['reference'], (int) $report['id'], 'report', ['reference' => $report['reference']]);

        // Notify supervisors of the site
        try {
            require_once __DIR__ . '/../src/mail.php';
            $siteId = (int) $report['site_id'];
            $recipients = getNotificationRecipients($pdo, $siteId);
            if (!empty($recipients)) {
                $registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
                $subject = "Signalement abandonné $registryLabel — {$report['reference']}";
                $body = '<html><body>';
                $body .= '<h2>Signalement abandonné</h2>';
                $body .= '<p><strong>Référence :</strong> ' . e($report['reference']) . '</p>';
                $body .= "<p><strong>Registre :</strong> $registryLabel</p>";
                $body .= '<p><strong>Objet :</strong> ' . e($report['objet']) . '</p>';
                $body .= '<p><strong>Déclarant :</strong> ' . e($report['declarant_prenom'] . ' ' . $report['declarant_nom']) . '</p>';
                $body .= '<p><a href="' . getBaseUrl() . '/' . url('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
                $body .= '</body></html>';
                foreach ($recipients as $email) {
                    sendMail($email, $subject, $body);
                }
            }
        } catch (\Throwable $mailEx) {
            error_log('[SST-MAIL] Abandon notification error: ' . $mailEx->getMessage());
        }

        setFlash('success', 'Signalement ' . e($report['reference']) . ' abandonné.');
        redirect(url('report_list', ['type' => $type]));
    } else {
        setFlash('error', 'Impossible d\'abandonner le signalement. Il a peut-être été modifié entre-temps. (uuid=' . e($reportUuid) . ', etat=' . e($report['etat']) . ')');
        redirect(url('report_view', ['uuid' => $reportUuid]));
    }
} catch (\RuntimeException $e) {
    setFlash('error', e($e->getMessage()));
    redirect(url('report_view', ['uuid' => $reportUuid]));
}
