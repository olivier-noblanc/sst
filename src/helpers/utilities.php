<?php

/**
 * Utility helpers — Application SST DREETS BFC
 *
 * Extracted from formatting.php for single-responsibility clarity.
 */

/**
 * Generate a report reference string.
 * Format: {type}-{YY}-{NNN}
 *
 * @param string $type    Registry type: 'rsst', 'rami', 'dgi'
 * @param string $year2   2-digit year, e.g. '25'
 * @param int    $seq     Sequence number
 * @return string
 */
function generateReference(string $type, string $year2, int $seq): string
{
    return $type . '-' . $year2 . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

/**
 * Get the next sequence number for a report reference.
 * Uses atomic UPSERT on the report_sequence table.
 *
 * @param PDO    $pdo   Database connection
 * @param string $type  Registry type
 * @param int    $year  Full year, e.g. 2025
 * @return int
 */
function getNextSequence(PDO $pdo, string $type, int $year): int
{
    $stmt = $pdo->prepare('
        INSERT INTO report_sequence (type, year, last_sequence)
        VALUES (:type, :year, 1)
        ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1
        RETURNING last_sequence
    ');
    $stmt->execute([':type' => $type, ':year' => $year]);
    return (int) $stmt->fetchColumn();
}

/**
 * Detect the MIME type of a file using the fileinfo extension.
 */
function getMimeType(string $filePath): string
{
    if (!class_exists('finfo')) {
        throw new \RuntimeException(
            'L\'extension PHP "fileinfo" est requise pour le téléchargement de pièces jointes. ' .
            'Veuillez l\'activer dans php.ini : extension=fileinfo, puis redémarrer le serveur web.'
        );
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($filePath);
    if ($mime === false) {
        throw new \RuntimeException('Impossible de déterminer le type du fichier.');
    }
    return $mime;
}

/**
 * Truncate a string to a given length with ellipsis.
 */
function truncate(string $string, int $length = 50): string
{
    if (mb_strlen($string, 'UTF-8') > $length) {
        return mb_substr($string, 0, $length, 'UTF-8') . '…';
    }
    return $string;
}

/**
 * Get today's date in ISO format (Y-m-d).
 */
function todayISO(): string
{
    return date('Y-m-d');
}

/**
 * Get current time in HH:MM format.
 */
function nowTime(): string
{
    return date('H:i');
}
