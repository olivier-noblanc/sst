<?php
/**
 * Report Attachment Download — Application SST DREETS BFC
 * 
 * Serves the attachment BLOB from the database as a file download.
 * URL: index.php?page=report_attachment&uuid={report_uuid}
 */

$uuid = $_GET['uuid'] ?? '';

if (!isValidUuid($uuid)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT attachment_blob, attachment_name, attachment_mime FROM reports WHERE uuid = :uuid');
$stmt->execute([':uuid' => $uuid]);
$row = $stmt->fetch();

if (!$row || empty($row['attachment_blob'])) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Access control: same as report_view
$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(403);
    exit('Accès refusé.');
}

$userRole = $user['role'];
$userSiteId = (int) $user['site_id'];
$userId = (int) $user['id'];
$reportVisibility = getReportVisibility();

// Get full report for access check
$report = getReportByUuid($pdo, $uuid);
if (!$report) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Same access control as report_view
if (!in_array($userRole, ['superviseur', 'chsct'])) {
    if ((int) $report['site_id'] !== $userSiteId) {
        http_response_code(403);
        exit('Accès refusé.');
    }
    if ($reportVisibility === 'confidential' && (int) $report['declarant_id'] !== $userId) {
        http_response_code(403);
        exit('Accès refusé.');
    }
    if ($reportVisibility === 'agent_choice' && (int) $report['is_confidential'] === 1 && (int) $report['declarant_id'] !== $userId) {
        http_response_code(403);
        exit('Accès refusé.');
    }
}

// Serve the file
$mime = $row['attachment_mime'] ?? 'application/octet-stream';
$name = $row['attachment_name'] ?? 'piece_jointe';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $name) . '"');
header('Content-Length: ' . strlen($row['attachment_blob']));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $row['attachment_blob'];
exit;
