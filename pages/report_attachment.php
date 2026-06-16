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

// Access control: centralized via canAccessReport()
$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(403);
    exit('Accès refusé.');
}

// Get full report for access check
$report = getReportByUuid($pdo, $uuid);
if (!$report) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

if (!canAccessReport($report, $user)) {
    http_response_code(403);
    exit('Accès refusé.');
}

// Log confidential report access by supervisor/CHSCT
logConfidentialReportAccess($pdo, $report, $user);

// Serve the file
$mime = $row['attachment_mime'] ?? 'application/octet-stream';
$name = $row['attachment_name'] ?? 'piece_jointe';

// Check if inline mode is requested (for image preview in browser)
$inline = !empty($_GET['inline']);

// For images with inline=1, use Content-Disposition: inline (display in browser)
// For PDFs or downloads without inline param, force download
$isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif']);
$disposition = ($inline && $isImage) ? 'inline' : 'attachment';

sendFileDownload($row['attachment_blob'], $name, $mime, $disposition);
