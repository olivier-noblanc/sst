<?php
/**
 * Export Handler — Application SST DREETS BFC
 * 
 * POST handler: generate CSV and send as download.
 * Access: superviseur, chsct
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
$data = getExportData($pdo, $filters);

// Generate CSV
$filename = 'export_sst_' . date('Y-m-d_His') . '.csv';

// UTF-8 BOM for Excel compatibility
$csv = "\xEF\xBB\xBF";

// Header row
$csv .= implode(';', [
    'Référence',
    'Type',
    'Date',
    'Heure',
    'Objet',
    'Description',
    'Nom auteur',
    'Prénom auteur',
    getConfig('app_label_unite', 'UR'),
    'État',
    'Confidentiel',
    'Réponse',
    'Répondu par',
    'Date réponse',
]) . "\r\n";

// Helper: prevent CSV formula injection by prefixing cells that start with =+@-
$csvEscape = function($value): string {
    $value = (string) $value;
    if (preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
};

// Data rows
foreach ($data as $row) {
    $csv .= implode(';', [
        $csvEscape($row['reference'] ?? ''),
        $csvEscape(strtoupper($row['type'] ?? '')),
        $csvEscape($row['date_evenement'] ?? ''),
        $csvEscape($row['heure_evenement'] ?? ''),
        $csvEscape(str_replace(["\r\n", "\n", "\r"], ' ', $row['objet'] ?? '')),
        $csvEscape(str_replace(["\r\n", "\n", "\r"], ' ', $row['description'] ?? '')),
        $csvEscape($row['declarant_nom'] ?? ''),
        $csvEscape($row['declarant_prenom'] ?? ''),
        $csvEscape($row['site_code'] ?? ''),
        $csvEscape(ETAT_LABELS[$row['etat'] ?? ''] ?? $row['etat'] ?? ''),
        !empty($row['is_confidential']) ? 'Oui' : 'Non',
        $csvEscape(str_replace(["\r\n", "\n", "\r"], ' ', $row['reponse'] ?? '')),
        $csvEscape(trim(($row['repondant_prenom'] ?? '') . ' ' . ($row['repondant_nom'] ?? ''))),
        $csvEscape($row['date_reponse'] ?? ''),
    ]) . "\r\n";
}

// Send as download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($csv));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

echo $csv;
exit;
