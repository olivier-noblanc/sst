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

    private function getAppVersion(): string
    {
        $configService = new ConfigService();
        return $configService->getAppVersion();
    }
}
