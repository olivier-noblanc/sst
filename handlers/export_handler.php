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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('export'));
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Erreur de sécurité. Veuillez réessayer.');
    redirect(url('export'));
}

// Check role
if (!hasAnyRole(['superviseur', 'chsct'])) {
    setFlash('error', 'Vous n\'avez pas les permissions nécessaires.');
    redirect(url('home'));
}

$pdo = getDB();

// Build filters from form data
$filters = [];

// Registry type
if (empty($_POST['all_registries']) && !empty($_POST['type'])) {
    $filters['type'] = $_POST['type'];
}

// Site
if (empty($_POST['all_sites']) && !empty($_POST['site_id'])) {
    $filters['site_id'] = (int) $_POST['site_id'];
}

// Agent (declarant)
if (empty($_POST['all_agents']) && !empty($_POST['declarant_id'])) {
    $filters['declarant_id'] = (int) $_POST['declarant_id'];
}

// Date range
if (!empty($_POST['date_from'])) {
    $filters['date_from'] = $_POST['date_from'];
}
if (!empty($_POST['date_to'])) {
    $filters['date_to'] = $_POST['date_to'];
}

// States
if (!empty($_POST['etats']) && is_array($_POST['etats'])) {
    $filters['etats'] = $_POST['etats'];
}

// Get data
$reports = getExportData($pdo, $filters);
require_once __DIR__ . '/../src/audit.php';
auditLog($pdo, 'export', 'csv_export', 'Export CSV — ' . count($reports) . ' signalements', null, null, ['filters' => $filters, 'count' => count($reports)]);

// Build CSV in memory using fputcsv (proper enclosure, no injection risk)
$filename = 'export_sst_' . date('Y-m-d_His') . '.csv';
$tmpFile = tmpfile();
if ($tmpFile === false) {
    setFlash('error', 'Erreur lors de la génération du fichier.');
    redirect(url('export'));
}

// UTF-8 BOM for Excel compatibility
fwrite($tmpFile, "\xEF\xBB\xBF");

// Header row — includes multi-response columns
$headers = [
    'Référence',
    'Registre',
    'Date événement',
    'Heure événement',
    'Lieu',
    'Objet',
    'Description',
    'Déclarant (nom)',
    'Déclarant (prénom)',
    getConfig('app_label_unite', 'UR'),
    'Nom ' . getConfig('app_label_unite', 'UR'),
    'État',
    'Confidentiel',
    'Date création',
    'Déclaré pour le compte de',
    'Nb réponses',
    'Dernière réponse',
    'Dernier répondant',
    'Date dernière réponse',
    'Historique réponses',
];
fputcsv($tmpFile, $headers, ';');

// Data rows
foreach ($reports as $row) {
    // Get response history for this report
    $responses = getReportResponses($pdo, $row['uuid']);
    $responseCount = count($responses);

    // Build "Pour le compte de" field
    $pourCompte = '';
    if (!empty($row['pour_compte_nom'])) {
        $pourCompte = trim(($row['pour_compte_prenom'] ?? '') . ' ' . $row['pour_compte_nom']);
    }

    // Build response history as structured text
    // Format: [Date] Répondant (État) : Réponse | [Date] ...
    $historyParts = [];
    foreach ($responses as $resp) {
        $date = $resp['created_at'] ?? '';
        $respondent = trim(($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? ''));
        $etat = ETAT_LABELS[$resp['nouvel_etat']] ?? $resp['nouvel_etat'] ?? '';
        $text = $resp['reponse'] ?? '';
        $historyParts[] = "[$date] $respondent ($etat) : $text";
    }
    $historyText = implode(' | ', $historyParts);

    // CSV formula injection prevention: prefix cells starting with =+@-
    $csvEscape = function($value): string {
        $value = (string) $value;
        if (preg_match('/^[=+\-@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    };

    $csvRow = [
        $csvEscape($row['reference'] ?? ''),
        $csvEscape(strtoupper($row['type'] ?? '')),
        $csvEscape($row['date_evenement'] ?? ''),
        $csvEscape($row['heure_evenement'] ?? ''),
        $csvEscape($row['lieu'] ?? ''),
        $csvEscape($row['objet'] ?? ''),
        $csvEscape($row['description'] ?? ''),           // Newlines preserved by fputcsv enclosure
        $csvEscape($row['declarant_nom'] ?? ''),
        $csvEscape($row['declarant_prenom'] ?? ''),
        $csvEscape($row['site_code'] ?? ''),
        $csvEscape($row['site_nom'] ?? ''),
        $csvEscape(ETAT_LABELS[$row['etat'] ?? ''] ?? $row['etat'] ?? ''),
        !empty($row['is_confidential']) ? 'Oui' : 'Non',
        $csvEscape($row['created_at'] ?? ''),
        $csvEscape($pourCompte),
        $responseCount,
        $csvEscape($row['reponse'] ?? ''),
        $csvEscape(trim(($row['repondant_prenom'] ?? '') . ' ' . ($row['repondant_nom'] ?? ''))),
        $csvEscape($row['date_reponse'] ?? ''),
        $csvEscape($historyText),
    ];

    fputcsv($tmpFile, $csvRow, ';');
}

// Read the CSV content from temp file
rewind($tmpFile);
$csv = stream_get_contents($tmpFile);
fclose($tmpFile);

// Disable gzip output buffer for file download
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Send as download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($csv));
header_remove('X-Powered-By');
header_remove('Server');
header_remove('Expires');
header_remove('Pragma');
header('Cache-Control: no-cache, max-age=0');
header('X-Content-Type-Options: nosniff');

echo $csv;
exit;
