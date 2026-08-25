<?php

use App\Services\SessionService;
use App\Repository\ReportRepository;
use App\Repository\ReportAttachmentRepository;

/**
 * Response Attachment Download — Application SST DREETS BFC
 *
 * Serves the attachment BLOB from report_responses as a file download.
 * URL: index.php?page=response_attachment&id={response_id}
 */

$responseIdStr = $_GET['id'] ?? '0';
$responseId = (int) $responseIdStr;

if ($responseId <= 0) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$pdo = getDB();
$row = ReportAttachmentRepository::instance()->getResponseAttachmentById($responseId);

if ($row === null || empty($row['attachment_blob'])) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Access control: check access to the parent report
$user = SessionService::getInstance()->getUserSession();
if ($user === null) {
    http_response_code(403);
    exit('Accès refusé.');
}

$report = ReportRepository::instance()->findById($row['report_uuid']);
if ($report === null || !canAccessReport($report, $user)) {
    http_response_code(403);
    exit('Accès refusé.');
}

// Log confidential report access by supervisor/CHSCT
logConfidentialReportAccess($pdo, $report, $user);

// Serve the file
$mime = $row['attachment_mime'] ?? 'application/octet-stream';
$name = $row['attachment_name'] ?? 'piece_jointe';
$inline = !empty($_GET['inline']);
$isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true);
$disposition = ($inline && $isImage) ? 'inline' : 'attachment';

sendFileDownload($row['attachment_blob'], $name, $mime, $disposition);
