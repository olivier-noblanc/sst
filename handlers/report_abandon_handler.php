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

// Get report UUID from form
$reportUuid = trim($_POST['report_uuid'] ?? '');
if ($reportUuid === '' || !isValidUuid($reportUuid)) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportByUuid($pdo, $reportUuid);

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
    redirect(url('report_view', ['uuid' => $reportUuid]));
}

// State check (from DB, not form)
if (!in_array($report['etat'], ['nouveau', 'en_cours'])) {
    setFlash('error', 'Ce signalement ne peut plus être abandonné (état : ' . (ETAT_LABELS[$report['etat']] ?? $report['etat']) . ').');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}

// Abandon the report (soft delete)
$abandoned = abandonReport($pdo, $reportUuid, $userId);

if ($abandoned) {
    require_once __DIR__ . '/../src/audit.php';
    auditLog($pdo, 'report', 'abandon', 'Signalement abandonné : ' . $report['reference'], (int) $report['id'] ?? null, 'report', ['reference' => $report['reference']]);
    setFlash('success', 'Signalement ' . e($report['reference']) . ' abandonné.');
    redirect(url('report_list', ['type' => $type]));
} else {
    setFlash('error', 'Impossible d\'abandonner le signalement. Il a peut-être été modifié entre-temps.');
    redirect(url('report_view', ['uuid' => $reportUuid]));
}
