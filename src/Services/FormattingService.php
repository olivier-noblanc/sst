<?php

/** FormattingService — HTML escaping, date/time formatting, report references, badge CSS, text utilities. */

namespace App\Services;

use DateTime;
use PDO;
use RuntimeException;
use finfo;
use Exception;

class FormattingService
{
    /**
     * Escape HTML special characters. Use for ALL output.
     */
    public function e(mixed $string): string
    {
        if ($string === null || $string === '') {
            return '';
        }
        return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format an ISO date to French format (d/m/Y).
     */
    public function formatDateFR(mixed $date): string
    {
        if (empty($date)) {
            return '—';
        }
        $date = (string) $date;
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        }
        return $dt !== false ? $dt->format('d/m/Y') : $this->e($date);
    }

    /**
     * Format an ISO datetime to French format (d/m/Y à H:i).
     */
    public function formatDateTimeFR(mixed $datetime): string
    {
        if (empty($datetime)) {
            return '—';
        }
        $datetime = (string) $datetime;
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $datetime);
        }
        return $dt !== false ? $dt->format('d/m/Y \à H:i') : $this->e($datetime);
    }

    /**
     * Format time only (H:i).
     */
    public function formatTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return '—';
        }
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $datetime);
        }
        return $dt !== false ? $dt->format('H:i') : $this->e($datetime);
    }

    /**
     * Generate a report reference string. Format: {type}-{YY}-{NNN}
     */
    public function generateReference(string $type, string $year2, int $seq): string
    {
        return $type . '-' . $year2 . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get the next sequence number for a report reference.
     */
    public function getNextSequence(PDO $pdo, string $type, int $year): int
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
    public function getRegistryColor(string $type): string
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
    public function getEtatBadgeClass(mixed $etat): string
    {
        return match ((string) $etat) {
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
    public function getRegistryBadgeClass(mixed $type): string
    {
        return match ((string) $type) {
            'rsst' => 'badge--rsst',
            'rami' => 'badge--rami',
            'dgi'  => 'badge--dgi',
            default => '',
        };
    }

    /**
     * Get the badge CSS class for a user role.
     */
    public function getRoleBadgeClass(mixed $role): string
    {
        return match ((string) $role) {
            'agent'       => 'badge--agent',
            'superviseur' => 'badge--superviseur',
            'chsct'       => 'badge--chsct',
            default       => '',
        };
    }

    /**
     * Detect the MIME type of a file using the fileinfo extension.
     */
    public function getMimeType(string $filePath): string
    {
        if (!class_exists('finfo')) {
            throw new RuntimeException(
                'L\'extension PHP "fileinfo" est requise pour le téléchargement de pièces jointes. '
                . 'Veuillez l\'activer dans php.ini : extension=fileinfo, puis redémarrer le serveur web.'
            );
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath);
        if ($mime === false) {
            throw new RuntimeException('Impossible de déterminer le type du fichier.');
        }
        return $mime;
    }

    /**
     * Truncate a string to a given length with ellipsis.
     */
    public function truncate(mixed $string, int $length = 50): string
    {
        $string = (string) $string;
        if (mb_strlen($string, 'UTF-8') > $length) {
            return mb_substr($string, 0, $length, 'UTF-8') . '…';
        }
        return $string;
    }

    /**
     * Get today's date in ISO format (Y-m-d).
     */
    public function todayISO(): string
    {
        return date('Y-m-d');
    }

    /**
     * Get current time in HH:MM format.
     */
    public function nowTime(): string
    {
        return date('H:i');
    }

    /**
     * Render a breadcrumb navigation bar.
     *
     * @param array<int, array{url?: string, label: mixed}> $items
     * @return string HTML for the breadcrumb nav
     */
    public function renderBreadcrumb(array $items): string
    {
        $html = '<nav class="breadcrumb" aria-label="Fil d\'Ariane">';
        $last = count($items) - 1;
        foreach ($items as $i => $item) {
            if ($i === $last) {
                $html .= '<span class="breadcrumb__current">' . $this->e($item['label']) . '</span>';
            } else {
                $html .= '<a href="' . $this->e($item['url']) . '" class="breadcrumb__item">' . $this->e($item['label']) . '</a>';
                $html .= '<span class="breadcrumb__separator">/</span>';
            }
        }
        $html .= '</nav>';
        return $html;
    }

    /**
     * Build a word cloud from report descriptions/objets for a given registry type.
     *
     * @param PDO $pdo Database connection
     * @param string $type     Report type (rsst/rami/dgi)
     * @param int    $maxWords Maximum number of words to display
     * @return string HTML word cloud, or empty string if no data
     */
    public function buildWordCloud(PDO $pdo, string $type, int $maxWords = 30): string
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
        } catch (Exception) {
            return '';
        }

        if (empty(trim($allText))) {
            return '';
        }

        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($allText, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        $freq = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 4) {
                continue;
            }
            if (in_array($word, $stopWords)) {
                continue;
            }
            $freq[$word] = ($freq[$word] ?? 0) + 1;
        }

        if (empty($freq)) {
            return '';
        }

        arsort($freq);
        $topWords = array_slice($freq, 0, $maxWords, true);

        $maxFreq = max($topWords);
        $minFreq = min($topWords);
        $range = max($maxFreq - $minFreq, 1);

        $html = '<div class="word-cloud" role="img" aria-label="Nuage de mots des signalements">';
        foreach ($topWords as $word => $count) {
            $ratio = ($count - $minFreq) / $range;
            $size = 0.7 + ($ratio * 1.3);
            $html .= '<span class="word-cloud__word" style="font-size:' . number_format($size, 1) . 'rem;" title="' . $this->e($word) . ' (' . $count . ')">' . $this->e($word) . '</span> ';
        }
        $html .= '</div>';
        return $html;
    }
}
