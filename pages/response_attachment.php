<?php

/**
 * Response Attachment Download — Application SST DREETS BFC
 *
 * Serves the attachment BLOB from report_responses as a file download.
 * URL: index.php?page=response_attachment&id={response_id}
 */

/** @var string $responseIdStr */
$responseIdStr = $_GET['id'] ?? '0';
$responseId = (int) $responseIdStr;

if ($responseId <= 0) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$pdo = getDB();
$stmt = $pdo->prepare('
    SELECT rr.attachment_blob, rr.attachment_name, rr.attachment_mime, rr.report_uuid
    FROM report_responses rr
    WHERE rr.id = :id
');
$stmt->execute([':id' => $responseId]);
$row = $stmt->fetch();

if (!$row || empty($row['attachment_blob'])) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Access control: check access to the parent report
$user = currentUser();
if (!$user) {
    http_response_code(403);
    exit('Accès refusé.');
}

$report = getReportByUuid($pdo, $row['report_uuid']);
if (!$report || !canAccessReport($report, $user)) {
    http_response_code(403);
    exit('Accès refusé.');
}

// Serve the file
$mime = $row['attachment_mime'] ?? 'application/octet-stream';
$name = $row['attachment_name'] ?? 'piece_jointe';
$inline = !empty($_GET['inline']);
$isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/gif']);
$disposition = ($inline && $isImage) ? 'inline' : 'attachment';

sendFileDownload($row['attachment_blob'], $name, $mime, $disposition);
