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
function e(?string $string): string
{
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
function formatDateFR(?string $date): string
{
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
function formatDateTimeFR(?string $datetime): string
{
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
 * Get the registry color CSS variable name.
 */
function getRegistryColor(string $type): string
{
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
function getEtatBadgeClass(string $etat): string
{
    return match ($etat) {
        'nouveau'    => 'badge--nouveau',
        'en_cours'   => 'badge--en-cours',
        'traite'     => 'badge--traite',
        'abandonne'  => 'badge--abandonne',
        'reouvert'   => 'badge--reouvert',
        default      => '',
    };
}

/**
 * Get the badge CSS class for a registry type.
 */
function getRegistryBadgeClass(string $type): string
{
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
function getRoleBadgeClass(string $role): string
{
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

/**
 * Render a breadcrumb navigation bar.
 *
 * Replaces the duplicated breadcrumb HTML pattern across 6 files.
 * Each item is either a link ['url' => ..., 'label' => ...] or
 * a plain text current item ['label' => ...] (rendered as <span>).
 *
 * @param array<int, array{url?: string, label: string}> $items  Ordered list of breadcrumb items
 * @return string  HTML for the breadcrumb nav
 */
function renderBreadcrumb(array $items): string
{
    $html = '<nav class="breadcrumb" aria-label="Fil d\'Ariane">';
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        if ($i === $last) {
            // Current page — plain text
            $html .= '<span class="breadcrumb__current">' . e($item['label']) . '</span>';
        } else {
            // Clickable link
            $html .= '<a href="' . e($item['url']) . '" class="breadcrumb__item">' . e($item['label']) . '</a>';
            $html .= '<span class="breadcrumb__separator">/</span>';
        }
    }
    $html .= '</nav>';
    return $html;
}

/**
 * Build a word cloud from report descriptions/objets for a given registry type.
 *
 * Extracts the most frequent meaningful words (min 4 chars, excludes common French stop words).
 * Returns HTML spans with font sizes proportional to frequency.
 *
 * @param PDO    $pdo      Database connection
 * @param string $type     Report type (rsst/rami/dgi)
 * @param int    $maxWords Maximum number of words to display
 * @return string          HTML word cloud, or empty string if no data
 */
function buildWordCloud(PDO $pdo, string $type, int $maxWords = 30): string
{
    $stopWords = [
        'dans', 'pour', 'avec', 'plus', 'cette', 'tout', 'faire', 'être', 'avoir',
        'nous', 'vous', 'ils', 'elle', 'elles', 'monter', 'comme', 'mais', 'aussi',
        'bien', 'fait', 'leur', 'après', 'très', 'chez', 'entre', 'encore', 'avant',
        'peut', 'depuis', 'sans', 'tous', 'toute', 'toutes', 'quel', 'quelle',
        'autre', 'autres', 'deux', 'mêmes', 'même', 'ces', 'des', 'aux',
        'quelque', 'quelques', 'chaque', 'dont', 'rien', 'toujours', 'souvent',
        'quand', 'alors', 'ainsi', 'donc', 'car', 'notre', 'votre', 'moins',
        'trop', 'peu', 'beaucoup', 'celui', 'ceux', 'celle', 'celles',
        'signalement', 'signalements', 'agent', 'agents', 'superviseur',
        'registre', 'état', 'date', 'création', 'refusée', 'acceptée',
    ];

    try {
        $stmt = $pdo->prepare("SELECT objet, description FROM reports WHERE type = :type AND etat != 'abandonne'");
        $stmt->execute([':type' => $type]);
        $allText = '';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $allText .= ' ' . ($row['objet'] ?? '') . ' ' . ($row['description'] ?? '');
        }
    } catch (Exception $e) {
        return '';
    }

    if (empty(trim($allText))) {
        return '';
    }

    $words = preg_split('/[^\p{L}]+/u', mb_strtolower($allText, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    $freq = [];
    foreach ($words as $word) {
        $word = trim($word);
        if (mb_strlen($word, 'UTF-8') < 4) continue;
        if (in_array($word, $stopWords)) continue;
        $freq[$word] = ($freq[$word] ?? 0) + 1;
    }

    if (empty($freq)) return '';

    arsort($freq);
    $topWords = array_slice($freq, 0, $maxWords, true);

    $maxFreq = max($topWords);
    $minFreq = min($topWords);
    $range = max($maxFreq - $minFreq, 1);

    $html = '<div class="word-cloud" role="img" aria-label="Nuage de mots des signalements">';
    foreach ($topWords as $word => $count) {
        $ratio = ($count - $minFreq) / $range;
        $size = 0.7 + ($ratio * 1.3);
        $html .= '<span class="word-cloud__word" style="font-size:' . number_format($size, 1) . 'rem;" title="' . e($word) . ' (' . $count . ')">' . e($word) . '</span> ';
    }
    $html .= '</div>';
    return $html;
}
