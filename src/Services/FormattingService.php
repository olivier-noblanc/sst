<?php

/** FormattingService — HTML escaping, date/time formatting, report references, badge CSS, text utilities. */

namespace App\Services;

use DateTimeZone;
use App\Enum\ReportType;
use DateTime;
use PDO;
use RuntimeException;
use finfo;

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
        $str = $string;
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format an ISO date to French format (d/m/Y).
     */
    public function formatDateFR(mixed $date): string
    {
        if (empty($date)) {
            return '—';
        }
        $date = $date;
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        }
        return $dt !== false ? $dt->format('d/m/Y') : $this->e($date);
    }

    /**
     * Format an ISO datetime to French format (d/m/Y à H:i).
     * Assumes the input is in UTC (from SQLite datetime('now')) and converts to Europe/Paris.
     */
    public function formatDateTimeFR(mixed $datetime): string
    {
        if (empty($datetime)) {
            return '—';
        }
        $datetime = $datetime;
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone('UTC'));
        if ($dt === false) {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $datetime, new DateTimeZone('UTC'));
        }
        if ($dt !== false) {
            $dt->setTimezone(new DateTimeZone('Europe/Paris'));
            return $dt->format('d/m/Y \à H:i');
        }
        return $this->e($datetime);
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
        return match (ReportType::from($type)) {
            ReportType::Rsst => 'var(--rsst-color)',
            ReportType::Rami => 'var(--rami-color)',
            ReportType::Dgi  => 'var(--dgi-color)',
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
        return ReportType::from((string) $type)->badgeClass();
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
                $html .= '<a href="' . $this->e($item['url'] ?? '') . '" class="breadcrumb__item">' . $this->e($item['label']) . '</a>';
                $html .= '<span class="breadcrumb__separator">/</span>';
            }
        }
        $html .= '</nav>';
        return $html;
    }

    /**
     * Build a word cloud from admin-configured words with randomized importance.
     *
     * Words and weights are stored in config key 'word_cloud_words' as JSON:
     * [{"word": "chute", "weight": 10}, {"word": "incendie", "weight": 8}, ...]
     *
     * Each page load randomizes weights by ±30% for a dynamic visual effect.
     *
     * @return string HTML word cloud, or empty string if no data
     */
    public function buildWordCloud(): string
    {
        $configService = ConfigService::getInstance();
        $wordsJson = $configService->get('word_cloud_words', '[]');
        $words = json_decode($wordsJson, true) ?? [];

        if (empty($words)) {
            return '';
        }

        // Randomize weights by ±30% for dynamic rendering
        $randomized = [];
        if (is_array($words)) {
            foreach ($words as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $word = (string) ($entry['word'] ?? '');
                $baseWeight = (int) ($entry['weight'] ?? 10);
                if ($word === '' || $baseWeight < 1) {
                    continue;
                }
                $variation = (int) ($baseWeight * 0.3);
                $randMin = -$variation;
                $randMax = $variation;
                $randomWeight = $baseWeight + mt_rand($randMin, $randMax) / 10.0;
                $randomWeight = max(1.0, min(20.0, $randomWeight));
                $randomized[] = ['w' => $word, 'p' => $randomWeight];
            }
        }

        if (empty($randomized)) {
            return '';
        }

        // Sort by randomized weight descending
        usort($randomized, fn(array $a, array $b): int => $b['p'] <=> $a['p']);

        $jsonEncoded = json_encode($randomized, JSON_UNESCAPED_UNICODE);
        $json = htmlspecialchars($jsonEncoded !== false ? $jsonEncoded : '[]', ENT_QUOTES, 'UTF-8');
        $html = '<div class="word-cloud" role="img" aria-label="Nuage de mots" data-words="' . $json . '">';
        $html .= '<p class="text-muted text-small mb-2">Nuage de mots — Mots les plus fréquents</p>';
        $html .= '<noscript>';
        foreach ($randomized as $entry) {
            $size = 0.65 + ($entry['p'] * 0.05);
            $html .= '<span class="word-cloud__word" style="font-size:' . number_format($size, 1) . 'rem;">' . $this->e($entry['w']) . '</span> ';
        }
        $html .= '</noscript>';
        $html .= '</div>';
        return $html;
    }
}
