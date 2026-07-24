<?php

use App\Repository\ReportRepository;

/**
 * Report Attachment Download — Application SST DREETS BFC
 *
 * Serves the attachment BLOB from the database as a file download.
 * URL: index.php?page=report_attachment&uuid={report_uuid}
 */

/** @var string */
$uuid = $_GET['uuid'] ?? '';

if (!isValidUuid($uuid)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$pdo = getDB();
$row = ReportRepository::instance()->getAttachmentBlob($uuid);

if (!is_array($row) || empty($row['attachment_blob'])) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Access control: centralized via canAccessReport()
$user = currentUser();
if ($user === null) {
    http_response_code(403);
    exit('Accès refusé.');
}

// Get full report for access check
$report = getReportByUuid($pdo, $uuid);
if ($report === null) {
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
/** @var string */
$mime = $row['attachment_mime'] ?? 'application/octet-stream';
/** @var string */
$name = $row['attachment_name'] ?? 'piece_jointe';

// Check if inline mode is requested (for image preview in browser)
$inline = !empty($_GET['inline']);

// For images with inline=1, use Content-Disposition: inline (display in browser)
// For PDFs or downloads without inline param, force download
$isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true);
$disposition = ($inline && $isImage) ? 'inline' : 'attachment';

/** @var string */
$blob = $row['attachment_blob'];
sendFileDownload($blob, $name, $mime, $disposition);
