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
use App\Services\ConfigService;
use App\Enum\ReportType;
use App\Repository\StatsRepository;
use App\Repository\ReportRepository;

/** @var array<string, string> $_POST */

$http = new HttpService();
$session = SessionService::getInstance();
$config = getConfigService();

$pdo = getDB();
$noSiteMode = isNoSiteMode($pdo);
$reportRepo = ReportRepository::instance();

// Build filters from form data
$filters = [];

// Registry type
if (empty($_POST['all_registries']) && !empty($_POST['type'])) {
    $filters['type'] = (string) ($_POST['type'] ?? '');
}

// Site
if (empty($_POST['all_sites']) && !empty($_POST['site_id'])) {
    $filters['site_id'] = (int) ($_POST['site_id'] ?? 0);
}

// Agent (declarant)
if (empty($_POST['all_agents']) && !empty($_POST['declarant_id'])) {
    $filters['declarant_id'] = (int) ($_POST['declarant_id'] ?? 0);
}

// Date range
if (!empty($_POST['date_from'])) {
    $filters['date_from'] = (string) ($_POST['date_from'] ?? '');
}
if (!empty($_POST['date_to'])) {
    $filters['date_to'] = (string) ($_POST['date_to'] ?? '');
}

// States
if (!empty($_POST['etats']) && is_array($_POST['etats'])) {
    $filters['etats'] = $_POST['etats'];
}

// Get data
$reports = $reportRepo->getExportData($filters);
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
auditLog($pdo, 'export', 'csv_export', 'Export CSV — ' . $count . ' signalements' . ($truncated ? ' (tronqué)' : ''), null, null, ['filters' => $filters, 'count' => $count]);

/** @var resource $tmpFile */
// UTF-8 BOM for Excel compatibility
fwrite($tmpFile, "\xEF\xBB\xBF");

// Header row — includes multi-response columns
$headers = [
    'Référence',
    'Registre',
    'Date événement',
    'Heure dépôt',
    'Lieu',
    'Pôle',
    'Service d\'affectation',
    'Téléphone mobile',
    'Site (texte)',
    'Objet',
    'Description',
    'Déclarant (nom)',
    'Déclarant (prénom)',
];
if (!$noSiteMode) {
    $headers[] = $config->get('app_label_unite', 'UR');
    $headers[] = 'Nom ' . $config->get('app_label_unite', 'UR');
}
$headers = array_merge($headers, [
    'État',
    'Confidentiel',
    'Transmission FS/CSA',
    'Date création',
    'Déclaré pour le compte de',
    'Nature de l\'auteur (RAMI)',
    'Type d\'acte (RAMI)',
    'Nb réponses',
    'Dernière réponse',
    'Dernier répondant',
    'Date dernière réponse',
    'Historique réponses',
]);
fputcsv($tmpFile, $headers, ';', escape: '\\');

// Bulk-fetch all responses for the reports being exported (avoids N+1 queries)
$allResponses = [];
if (!empty($reports)) {
    $allUuids = array_column($reports, 'uuid');
    $allResponses = $reportRepo->getResponsesForUuids($allUuids);
}

// Data rows
foreach ($reports as $row) {
    /** @var array<string, string> $row */
    // Get response history for this report (from bulk-fetched data)
    $responses = $allResponses[(string) ($row['uuid'] ?? '')] ?? [];
    $responseCount = count($responses);

    // Build "Pour le compte de" field
    $pourCompte = '';
    if (!empty($row['pour_compte_nom'])) {
        $pourCompte = trim(($row['pour_compte_prenom'] ?? '') . ' ' . $row['pour_compte_nom']);
    }

    // Build RAMI structured fields labels
    $natureAuteurLabel = getRegistryFieldOptions(ReportType::Rami->value, 'nature_auteur')[(string) ($row['nature_auteur'] ?? '')] ?? '';
    $typeActeLabel = getRegistryFieldOptions(ReportType::Rami->value, 'type_acte')[(string) ($row['type_acte'] ?? '')] ?? '';

    // Build response history as structured text
    // Format: [Date] Répondant (État) : Réponse | [Date] ...
    $historyParts = [];
    foreach ($responses as $resp) {
        /** @var array<string, string> $resp */
        $date = (string) ($resp['created_at'] ?? '');
        $respondent = trim((string) ($resp['prenom'] ?? '') . ' ' . (string) ($resp['nom'] ?? ''));
        $etat = ETAT_LABELS[(string) ($resp['nouvel_etat'] ?? '')] ?? (string) ($resp['nouvel_etat'] ?? '');
        $text = (string) ($resp['reponse'] ?? '');
        $historyParts[] = "[$date] $respondent ($etat) : $text";
    }
    $historyText = implode(' | ', $historyParts);

    // CSV formula injection prevention: prefix cells starting with =+@-
    $csvEscape = function ($value): string {
        $safe = $value;
        if (preg_match('/^[=+\-@]/', (string) $safe) > 0) {
            return "'" . $safe;
        }
        return $safe;
    };

    $csvRow = [
        $csvEscape($row['reference'] ?? ''),
        $csvEscape(strtoupper($row['type'] ?? '')),
        $csvEscape($row['date_evenement'] ?? ''),
        $csvEscape($row['heure_evenement'] ?? ''),
        $csvEscape($row['lieu'] ?? ''),
        $csvEscape($row['pole'] ?? ''),
        $csvEscape($row['service_affectation'] ?? ''),
        $csvEscape($row['telephone_mobile'] ?? ''),
        $csvEscape($row['site_text'] ?? ''),
        $csvEscape($row['objet'] ?? ''),
        $csvEscape($row['description'] ?? ''),
        $csvEscape($row['declarant_nom'] ?? ''),
        $csvEscape($row['declarant_prenom'] ?? ''),
    ];
    if (!$noSiteMode) {
        $csvRow[] = $csvEscape($row['site_code'] ?? '');
        $csvRow[] = $csvEscape($row['site_nom'] ?? '');
    }
    $csvRow = array_merge($csvRow, [
        $csvEscape(ETAT_LABELS[(string) ($row['etat'] ?? '')] ?? (string) ($row['etat'] ?? '')),
        !empty($row['is_confidential']) ? 'Oui' : 'Non',
        !empty($row['consent_syndicat']) ? 'Acceptée' : 'Refusée',
        $csvEscape($row['created_at'] ?? ''),
        $csvEscape($pourCompte),
        $csvEscape($natureAuteurLabel),
        $csvEscape($typeActeLabel),
        $responseCount,
        $csvEscape($row['reponse'] ?? ''),
        $csvEscape(trim(($row['repondant_prenom'] ?? '') . ' ' . ($row['repondant_nom'] ?? ''))),
        $csvEscape($row['date_reponse'] ?? ''),
        $csvEscape($historyText),
    ]);

    fputcsv($tmpFile, $csvRow, ';', escape: '\\');
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
