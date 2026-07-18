<?php

/** AssetService — CSS serving via PHP and asset URL building. */

namespace App\Services;

class AssetService
{
    /**
     * Generate a <link> tag for a CSS file served through css.php.
     *
     * @param string $path CSS path relative to public/ (e.g. 'css/style.css')
     * @return string HTML <link> tag
     */
    public function cssLink(string $path): string
    {
        $version = $this->getAppVersion();
        $href = 'css.php?f=' . urlencode($path) . '&v=' . urlencode($version);
        return '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Build a URL for a static asset (images, fonts, attachments, exports).
     *
     * @param string $path Asset path relative to public/ (e.g. 'img/logo.png')
     * @return string
     */
    public function assetUrl(string $path): string
    {
        $version = $this->getAppVersion();
        return 'asset.php?f=' . urlencode($path) . '&v=' . urlencode($version);
    }

    /**
     * Generate a data URI for a binary file (favicon, logo, etc.).
     *
     * @param string $path File path relative to public/ (e.g. 'favicon.ico')
     * @return string data URI (e.g. 'data:image/png;base64,...')
     */
    public function inlineDataUri(string $path): string
    {
        $filePath = dirname(__DIR__, 2) . '/public/' . $path;
        if (!file_exists($filePath)) {
            return '';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'webp' => 'image/webp',
        ];

        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        $content = file_get_contents($filePath);
        if ($content === false) {
            return '';
        }
        $data = base64_encode($content);

        return 'data:' . $mime . ';base64,' . $data;
    }

    /**
     * Get the icon HTML for an entity type.
     */
    public function getIcon(string $type): string
    {
        return match ($type) {
            'report'  => '<span class="icon icon--report" aria-hidden="true">📋</span>',
            'user'    => '<span class="icon icon--user" aria-hidden="true">👤</span>',
            'site'    => '<span class="icon icon--site" aria-hidden="true">🏢</span>',
            'config'  => '<span class="icon icon--config" aria-hidden="true">⚙️</span>',
            'logout'  => '<span class="icon icon--logout" aria-hidden="true">🚪</span>',
            'search'  => '<span class="icon icon--search" aria-hidden="true">🔍</span>',
            default   => '',
        };
    }

    /**
     * Get CSS class for a visual state (used by badges, buttons, etc.).
     */
    public function getCssClass(string $context, string $value): string
    {
        return match ($context) {
            'etat'     => $this->getEtatCssClass($value),
            'registry' => $this->getRegistryCssClass($value),
            'role'     => $this->getRoleCssClass($value),
            default    => '',
        };
    }

    private function getEtatCssClass(string $etat): string
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

    private function getRegistryCssClass(string $type): string
    {
        return match ($type) {
            'rsst'  => 'badge--rsst',
            'rami'  => 'badge--rami',
            'dgi'   => 'badge--dgi',
            default => '',
        };
    }

    private function getRoleCssClass(string $role): string
    {
        return match ($role) {
            'agent'       => 'badge--agent',
            'superviseur' => 'badge--superviseur',
            'chsct'       => 'badge--chsct',
            default       => '',
        };
    }

    private function getAppVersion(): string
    {
        $configService = new ConfigService();
        return $configService->getAppVersion();
    }
}
