<?php
/**
 * Report Abandon Handler — Application SST DREETS BFC
 *
 * POST handler for abandoning a report (soft delete).
 * Only the declarant can abandon, and only if etat is nouveau or en_cours.
 * Sets etat to 'abandonne' — does NOT delete from DB.
 */

validatePostRequest(url('home'));

// Get report UUID from form
$reportUuid = trim($_POST['report_uuid'] ?? '');
$report = fetchReportOrRedirect($reportUuid);

$user = currentUser();
$userId = currentUserId();
$type = $report['type'];

requireReportOwnership($report, $userId, $reportUuid, 'abandonner');
requireReportEditable($report, $reportUuid, 'abandonné');

$pdo = getDB();

// Abandon the report (soft delete)
$abandoned = abandonReport($pdo, $reportUuid, $userId);

if ($abandoned) {
    auditLog($pdo, 'report', 'abandon', 'Signalement abandonné : ' . $report['reference'], (int) $report['id'] ?? null, 'report', ['reference' => $report['reference']]);
    setFlash('success', 'Signalement ' . e($report['reference']) . ' abandonné.');
    redirect(url('report_list', ['type' => $type]));
} else {
    setFlash('error', 'Impossible d\'abandonner le signalement. Il a peut-être été modifié entre-temps. (uuid=' . e($reportUuid) . ', etat=' . e($report['etat']) . ')');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}
