<?php
/**
 * Report Abandon Handler — Application SST DREETS BFC
 *
 * POST handler for abandoning a report (soft delete).
 * Only the declarant can abandon, and only if etat is nouveau or en_cours.
 * Sets etat to 'abandonne' — does NOT delete from DB.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('home'));
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    setFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
    redirect(url('home'));
}

// Get report ID from form (more reliable than GET param for POST)
$reportId = (int) ($_POST['report_id'] ?? 0);
if ($reportId <= 0) {
    // Fall back to GET param
    $reportId = (int) ($_GET['id'] ?? 0);
}
if ($reportId <= 0) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportById($pdo, $reportId);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$user = $_SESSION['user'];
$userId = (int) $user['id'];
$type = $report['type'];

// Ownership check
if ((int) $report['declarant_id'] !== $userId) {
    setFlash('error', 'Vous ne pouvez abandonner que vos propres signalements.');
    redirect(url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}

// State check (from DB, not form)
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus être abandonné (état : ' . (ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    redirect(url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}

// Abandon the report (soft delete)
$abandoned = abandonReport($pdo, $reportId, $userId);

if ($abandoned) {
    setFlash('success', 'Signalement ' . e($report['reference']) . ' abandonné.');
    redirect(url('report_list', ['type' => $type]));
} else {
    setFlash('error', 'Impossible d\'abandonner le signalement. Il a peut-être été modifié entre-temps.');
    redirect(url('report_view', ['uuid' => getReportById($pdo, $reportId)['uuid']]));
}
