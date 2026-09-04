<?php

/**
 * Export Handler — Application SST DREETS BFC
 *
 * POST handler: generate CSV and send as download.
 * Access: superviseur, chsct
 *
 * Uses fputcsv() for proper field enclosure (handles semicolons,
 * quotes, and newlines inside fields). Exports multi-response history.
 */
use App\Services\HttpService;
use App\Services\SessionService;
use App\Services\ExportService;
use App\Repository\StatsRepository;
use App\Repository\ReportRepository;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();
$config = getConfigService();
$exportService = getContainer()->get(ExportService::class);

$pdo = getDB();
$noSiteMode = isNoSiteMode($pdo);
$reportRepo = ReportRepository::instance();

// Build filters from form data (delegated to ExportService — testable,
// etats normalisé en list<string> pour le contrat de getExportData())
$filters = $exportService->buildFiltersFromPost($_POST);

// Get data (with optional registryCode for dynamic columns)
// Fiabilisation (audit A2) — l'ancien code lisait $_POST['registry'], champ
// qui n'existait dans AUCUN formulaire (pages/export.php poste `type` +
// `all_registries`) → registryCode toujours null → les champs custom des
// registres custom n'étaient JAMAIS exportés (ni en en-têtes, ni en valeurs).
$registryCode = $exportService->resolveRegistryCodeFromPost($_POST);
$reports = StatsRepository::instance()->getExportData($filters, $registryCode);
$count = count($reports);
$truncated = $count >= StatsRepository::EXPORT_MAX_ROWS;

// Build CSV in memory using fputcsv (proper enclosure, no injection risk)
$filename = 'export_sst_' . date('Y-m-d_His') . '.csv';
$tmpFile = tmpfile();
if ($tmpFile === false) {
    $err = error_get_last();
    $session->setFlash('error', 'Erreur lors de la génération du fichier (tmpfile) : ' . e($err['message'] ?? 'erreur inconnue'));
    $http->redirect($http->url('export'));
}

// Audit #65 — Audit log AFTER tmpfile() success. Before this fix, the audit
// log was written before tmpfile() → if tmpfile() failed (disk full, /tmp
// not writable), the audit log claimed success but the user got an error.
// Now the audit log reflects the actual export attempt (data was fetched).
// Issue #1 — fini le json_encode($filters) qui causait un double-encoding
// (AuditRepository::log() re-json_encode le context). buildExportAuditContext()
// aplatit les filtres en clés scalaires filter_*.
auditLog($pdo, 'export', 'csv_export', 'Export CSV — ' . $count . ' signalements' . ($truncated ? ' (tronqué)' : ''), null, null, buildExportAuditContext($filters, $count));

/** @var resource $tmpFile */
// UTF-8 BOM for Excel compatibility
fwrite($tmpFile, "\xEF\xBB\xBF");

// Header row — includes multi-response columns (declarative via ExportService)
// + colonnes dynamiques du registre quand un registre unique est exporté
$headers = $exportService->buildHeaders($noSiteMode, $registryCode);
fputcsv($tmpFile, $headers, ';', escape: '');

// Bulk-fetch all responses for the reports being exported (avoids N+1 queries)
$allResponses = [];
if (!empty($reports)) {
    $allUuids = array_column($reports, 'uuid');
    $allResponses = $reportRepo->getResponsesForUuids($allUuids);
}

// Data rows (transformed via ExportService)
foreach ($reports as $row) {
    /** @var array<string, string> $row */
    // Get response history for this report (from bulk-fetched data)
    $responses = $allResponses[(string) ($row['uuid'] ?? '')] ?? [];

    // Build CSV row using ExportService (with dynamic registry columns)
    $csvRow = $exportService->buildCsvRow($row, $responses, $noSiteMode, $registryCode);

    fputcsv($tmpFile, $csvRow, ';', escape: '');
}

// Stream CSV directly to output (avoids loading entire file into memory)
rewind($tmpFile);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header_remove('X-Powered-By');
header_remove('Server');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $filename) . '"');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

fpassthru($tmpFile);
fclose($tmpFile);
exit;
