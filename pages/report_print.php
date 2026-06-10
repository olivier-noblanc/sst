<?php
/**
 * Report Print Page — Application SST DREETS BFC
 *
 * Generates a PDF of a single report using mPDF.
 * No JavaScript, no window.print() — pure server-side PDF generation.
 * URL: index.php?page=report_print&id={report_id}
 *
 * NOTE: This page is included by the router BEFORE header/sidebar.
 * The router has already started the session, loaded config/database/helpers/queries,
 * and checked authentication. We do NOT need to re-require or re-start session.
 */

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

$pdo = getDB();
$report = getReportById($pdo, $id);

if (!$report) {
    setFlash('error', 'Signalement introuvable.');
    redirect(url('home'));
}

// Access control: depends on report visibility setting
$user = $_SESSION['user'];
$userSiteId = (int) $user['site_id'];
$userId = (int) $user['id'];
$userRole = $user['role'];
$reportVisibility = getReportVisibility();

// Superviseur/CHSCT can always see everything
if (!in_array($userRole, ['superviseur', 'chsct'])) {
    // Agent access control
    if ((int) $report['site_id'] !== $userSiteId) {
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
    if ($reportVisibility === 'confidential' && (int) $report['declarant_id'] !== $userId) {
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
    if ($reportVisibility === 'agent_choice' && (int) $report['is_confidential'] === 1 && (int) $report['declarant_id'] !== $userId) {
        setFlash('error', 'Vous n\'avez pas accès à ce signalement.');
        redirect(url('home'));
    }
}

// Get response history
$responses = getReportResponses($pdo, $id);

$type = $report['type'] ?? 'rsst';
$registryLabel = REGISTRY_LABELS[$type] ?? strtoupper($type);
$registryShortLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
$etatLabel = ETAT_LABELS[$report['etat']] ?? $report['etat'];

// --- Build PDF with mPDF ---
require_once __DIR__ . '/../vendor/autoload.php';

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_left'   => 15,
    'margin_right'  => 15,
    'margin_top'    => 25,
    'margin_bottom' => 20,
    'default_font'  => 'dejavusans',
]);

$mpdf->SetTitle('Signalement ' . $report['reference']);
$mpdf->SetAuthor(getConfig('app_nom_organisation', 'DREETS BFC'));

// Header
$mpdf->SetHTMLHeader(
    '<div style="font-size:9px;color:#666;border-bottom:1px solid #ccc;padding-bottom:4px;">'
    . e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté'))
    . ' — Signalement ' . e($report['reference'])
    . '</div>'
);

// Footer with page number
$mpdf->SetHTMLFooter(
    '<div style="font-size:8px;color:#999;border-top:1px solid #ccc;padding-top:4px;text-align:center;">'
    . 'Page {PAGENO} / {nb} — Généré le ' . date('d/m/Y H:i')
    . '</div>'
);

// --- CSS ---
$css = '
    body { font-family: dejavusans, sans-serif; font-size: 11pt; color: #222; }
    h1 { font-size: 16pt; color: #1a3a5c; margin: 0 0 12px 0; }
    h2 { font-size: 13pt; color: #1a3a5c; margin: 20px 0 8px 0; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
    .field { margin: 6px 0; }
    .field-label { font-weight: bold; color: #555; display: inline-block; min-width: 180px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9pt; color: #fff; }
    .badge-rsst { background-color: #2E5C8A; }
    .badge-rami { background-color: #6C6C6C; }
    .badge-dgi { background-color: #B22222; }
    .badge-nouveau { background-color: #2E5C8A; }
    .badge-en_cours { background-color: #E67E22; }
    .badge-traite { background-color: #27AE60; }
    .badge-abandonne { background-color: #95A5A6; }
    .response-box { background: #f5f5f5; padding: 10px; border-radius: 4px; border-left: 4px solid #27AE60; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; margin: 8px 0; }
    th { background: #f0f0f0; text-align: left; padding: 6px 8px; border: 1px solid #ddd; font-size: 10pt; }
    td { padding: 6px 8px; border: 1px solid #ddd; font-size: 10pt; }
    hr { border: none; border-top: 1px solid #ccc; margin: 16px 0; }
    .footer-info { font-size: 8pt; color: #999; text-align: center; margin-top: 20px; }
';

// --- Build HTML body ---
$orgName = e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté'));
$labelUnite = e(getConfig('app_label_unite', 'UR'));

$html = '<style>' . $css . '</style>';

// Organization header
$html .= '<div style="font-size:9pt;color:#666;margin-bottom:16px;">' . $orgName . '</div>';

// Title + badges
$html .= '<h1>Signalement — ' . e($report['reference']) . '</h1>';
$html .= '<div style="margin-bottom:12px;">'
    . '<span class="badge badge-' . e($type) . '">' . e($registryShortLabel) . '</span> '
    . '<span class="badge badge-' . e($report['etat']) . '">' . e($etatLabel) . '</span>';
if (!empty($report['is_confidential'])) {
    $html .= ' <span class="badge" style="background-color:#6b7280;">Confidentiel</span>';
}
$html .= '</div>';

// Fields
$fields = [
    'Référence'             => e($report['reference']),
    'Registre'              => e($registryLabel),
    'Date de l\'événement'  => formatDateFR($report['date_evenement']),
    'Heure de l\'événement' => e($report['heure_evenement'] ?? '—'),
    'Lieu'                  => e($report['lieu'] ?? '—'),
    'Objet'                 => e($report['objet']),
    'Description'           => nl2br(e($report['description'])),
    'Déclarant'             => e($report['declarant_prenom'] . ' ' . $report['declarant_nom']),
    $labelUnite             => e($report['site_nom'] ?? '—') . ' (' . e($report['site_code'] ?? '—') . ')',
];

if ($type === 'rami' && !empty($report['pour_compte_nom'])) {
    $fields['Déclaré pour le compte de'] = e(($report['pour_compte_prenom'] ?? '') . ' ' . $report['pour_compte_nom']);
}

$fields['Date de création'] = formatDateTimeFR($report['created_at']);
// État as badge (not plain text)
// $fields['État'] = ... handled separately

foreach ($fields as $label => $value) {
    $html .= '<div class="field"><span class="field-label">' . e($label) . '</span> <span class="field-value">' . $value . '</span></div>';
}

// État field with badge
$html .= '<div class="field"><span class="field-label">État</span> <span class="field-value"><span class="badge badge-' . e($report['etat']) . '">' . e($etatLabel) . '</span></span></div>';

// Response section
if (!empty($report['reponse'])) {
    $html .= '<hr>';
    $html .= '<h2>Réponse</h2>';
    $html .= '<div class="response-box">' . nl2br(e($report['reponse'])) . '</div>';
    $html .= '<div class="field"><span class="field-label">Répondant</span> <span class="field-value">' . e(($report['repondant_prenom'] ?? '') . ' ' . ($report['repondant_nom'] ?? '')) . '</span></div>';
    $html .= '<div class="field"><span class="field-label">Date de réponse</span> <span class="field-value">' . formatDateTimeFR($report['date_reponse']) . '</span></div>';
}

// Response history table
if (!empty($responses)) {
    $html .= '<hr>';
    $html .= '<h2>Historique des réponses</h2>';
    $html .= '<table><thead><tr><th>Date</th><th>Répondant</th><th>Nouvel état</th><th>Réponse</th></tr></thead><tbody>';
    foreach ($responses as $resp) {
        $etatResp = !empty($resp['nouvel_etat'])
            ? e(ETAT_LABELS[$resp['nouvel_etat']] ?? $resp['nouvel_etat'])
            : '—';
        $html .= '<tr>'
            . '<td>' . formatDateTimeFR($resp['created_at']) . '</td>'
            . '<td>' . e(($resp['prenom'] ?? '') . ' ' . ($resp['nom'] ?? '')) . '</td>'
            . '<td>' . $etatResp . '</td>'
            . '<td>' . nl2br(e($resp['reponse'])) . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table>';
}

// Footer info
$html .= '<hr>';
$html .= '<div class="footer-info">Document généré le ' . formatDateFR(date('Y-m-d')) . ' — ' . e(getConfig('app_nom_organisation', 'DREETS BFC')) . '</div>';

// Output PDF
$mpdf->WriteHTML($html);

$filename = 'signalement-' . $report['reference'] . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit;
