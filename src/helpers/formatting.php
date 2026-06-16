<?php
/**
 * Formatting Helpers — Application SST DREETS BFC
 *
 * HTML escaping, date/time formatting, report references,
 * badge CSS classes, and text utilities.
 * Extracted from helpers.php for single-responsibility clarity.
 */

/**
 * Escape HTML special characters. Use for ALL output.
 * 
 * @param string|null $string  The string to escape
 * @return string
 */
function e(?string $string): string {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format an ISO date to French format (d/m/Y).
 * 
 * @param string|null $date  ISO date string (YYYY-MM-DD)
 * @return string
 */
function formatDateFR(?string $date): string {
    if (empty($date)) {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if ($dt === false) {
        // Try full datetime format
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
    }
    return $dt !== false ? $dt->format('d/m/Y') : e($date);
}

/**
 * Format an ISO datetime to French format (d/m/Y à H:i).
 * 
 * @param string|null $datetime  ISO datetime string
 * @return string
 */
function formatDateTimeFR(?string $datetime): string {
    if (empty($datetime)) {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    if ($dt === false) {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $datetime);
    }
    return $dt !== false ? $dt->format('d/m/Y \à H:i') : e($datetime);
}

/**
 * Generate a report reference string.
 * Format: {type}-{YY}-{NNN}
 * 
 * @param string $type    Registry type: 'rsst', 'rami', 'dgi'
 * @param string $year2   2-digit year, e.g. '25'
 * @param int    $seq     Sequence number
 * @return string
 */
function generateReference(string $type, string $year2, int $seq): string {
    return $type . '-' . $year2 . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
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
function getNextSequence(PDO $pdo, string $type, int $year): int {
    $stmt = $pdo->prepare("
        INSERT INTO report_sequence (type, year, last_sequence)
        VALUES (:type, :year, 1)
        ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1
    ");
    $stmt->execute([':type' => $type, ':year' => $year]);

    $stmt = $pdo->prepare("
        SELECT last_sequence FROM report_sequence WHERE type = :type AND year = :year
    ");
    $stmt->execute([':type' => $type, ':year' => $year]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get the registry color CSS variable name.
 */
function getRegistryColor(string $type): string {
    return match ($type) {
        'rsst' => 'var(--rsst-color)',
        'rami' => 'var(--rami-color)',
        'dgi'  => 'var(--dgi-color)',
        default => 'var(--color-primary)',
    };
}

/**
 * Get the badge CSS class for a report state.
 */
function getEtatBadgeClass(string $etat): string {
    return match ($etat) {
        'nouveau'    => 'badge--nouveau',
        'en_cours'   => 'badge--en-cours',
        'traite'     => 'badge--traite',
        'abandonne'  => 'badge--abandonne',
        default      => '',
    };
}

/**
 * Get the badge CSS class for a registry type.
 */
function getRegistryBadgeClass(string $type): string {
    return match ($type) {
        'rsst' => 'badge--rsst',
        'rami' => 'badge--rami',
        'dgi'  => 'badge--dgi',
        default => '',
    };
}

/**
 * Get the badge CSS class for a user role.
 */
function getRoleBadgeClass(string $role): string {
    return match ($role) {
        'agent'       => 'badge--agent',
        'superviseur' => 'badge--superviseur',
        'chsct'       => 'badge--chsct',
        default       => '',
    };
}

/**
 * Detect the MIME type of a file using the fileinfo extension.
 */
function getMimeType(string $filePath): string {
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
function truncate(string $string, int $length = 50): string {
    if (mb_strlen($string, 'UTF-8') > $length) {
        return mb_substr($string, 0, $length, 'UTF-8') . '…';
    }
    return $string;
}

/**
 * Get today's date in ISO format (Y-m-d).
 */
function todayISO(): string {
    return date('Y-m-d');
}

/**
 * Get current time in HH:MM format.
 */
function nowTime(): string {
    return date('H:i');
}
