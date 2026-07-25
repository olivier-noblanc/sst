<?php

/**
 * Error Notification & Throttle — Application SST DREETS BFC
 *
 * Admin email notification for critical errors and rate-limiting (throttle).
 * Split from error_handler.php for readability.
 *
 * Rate limiting: max 1 email per error type per 5 minutes to avoid flooding.
 * The throttle state is stored in data/error-throttle.json.
 */

/** Throttle file path */
define('ERROR_THROTTLE_FILE', __DIR__ . '/../data/error-throttle.json');

/** Minimum seconds between identical error emails */
define('ERROR_THROTTLE_SECONDS', 300); // 5 minutes

/**
 * Send an error notification email to the technical admin.
 *
 * @param string $levelName  Error level name (e.g. "Fatal error")
 * @param string $message    Error message
 * @param string $file       File path
 * @param int    $line       Line number
 * @param int    $errno      Error number
 */
function sstNotifyAdminError(string $levelName, string $message, string $file, int $line, int $errno): void
{
    // Get admin email from config (if available)
    $adminEmail = sstGetAdminEmail();
    if (empty($adminEmail)) {
        return; // No admin email configured — skip notification
    }

    // Rate limit: check if we already sent this error recently
    $errorKey = md5($levelName . $message . basename($file) . $line);
    if (sstIsThrottled($errorKey)) {
        return;
    }

    // Mark as sent (throttle)
    sstMarkThrottled($errorKey);

    // Build email content
    $appName = defined('APP_NAME') ? APP_NAME : 'Application SST';
    $appVersion = function_exists('getAppVersion') ? getAppVersion() : 'inconnue';
    /** @var string */
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
    /** @var string */
    $httpMethod = $_SERVER['REQUEST_METHOD'] ?? '';
    /** @var string */
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $timestamp = date('d/m/Y H:i:s');

    $subject = "[$appName] Alerte : $levelName détecté";

    $body = '<html><body>';
    $body .= "<h2 style=\"color:#c0392b;\">⚠ Alerte erreur — $levelName</h2>";
    $body .= '<table style="border-collapse:collapse; font-family:sans-serif; font-size:14px;">';
    $body .= '<tr><td style="padding:4px 12px; font-weight:bold; color:#555;">Application</td><td style="padding:4px 12px;">' . htmlspecialchars($appName) . " (v$appVersion)</td></tr>";
    $body .= "<tr><td style=\"padding:4px 12px; font-weight:bold; color:#555;\">Type d'erreur</td><td style=\"padding:4px 12px; color:#c0392b;\"><strong>$levelName</strong> (code $errno)</td></tr>";
    $body .= '<tr><td style="padding:4px 12px; font-weight:bold; color:#555;">Message</td><td style="padding:4px 12px;">' . htmlspecialchars($message) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 12px; font-weight:bold; color:#555;">Fichier</td><td style="padding:4px 12px; font-family:monospace; font-size:13px;">' . htmlspecialchars($file) . " (ligne $line)</td></tr>";
    $body .= '<tr><td style="padding:4px 12px; font-weight:bold; color:#555;">URL</td><td style="padding:4px 12px; font-family:monospace; font-size:13px;">' . htmlspecialchars($httpMethod . ' ' . $requestUri) . '</td></tr>';
    $body .= '<tr><td style="padding:4px 12px; font-weight:bold; color:#555;">Adresse IP</td><td style="padding:4px 12px;">' . htmlspecialchars($remoteAddr) . '</td></tr>';
    $body .= "<tr><td style=\"padding:4px 12px; font-weight:bold; color:#555;\">Date/Heure</td><td style=\"padding:4px 12px;\">$timestamp</td></tr>";
    $body .= '</table>';

    $body .= '<hr style="margin:16px 0; border:none; border-top:1px solid #ddd;">';
    $body .= "<p style=\"font-size:12px; color:#888;\">Cet e-mail a été envoyé automatiquement par l'application SST car une erreur critique a été détectée. ";
    $body .= "Pour limiter le spam, une même erreur ne déclenche qu'un seul e-mail toutes les " . (ERROR_THROTTLE_SECONDS / 60) . ' minutes. ';
    $body .= 'Consultez le <a href="' . htmlspecialchars($requestUri) . "\">journal d'erreurs</a> dans l'interface pour voir toutes les entrées.</p>";
    $body .= '</body></html>';

    // Send email (defer to mail module if available, otherwise use error_log)
    if (function_exists('sendMail')) {
        sendMail($adminEmail, $subject, $body);
    } else {
        // Mail module not loaded yet (early bootstrap) — log instead
        error_log("[SST-ERROR-MAIL] Would notify admin $adminEmail: $levelName — $message in $file:$line");
    }

    error_log("[SST-ERROR-MAIL] Notification sent to $adminEmail for $levelName in " . basename($file) . ":$line");
}

/**
 * Get the admin email from config.
 * Uses a static cache to avoid repeated DB queries.
 *
 * @return string Admin email or empty string
 */
function sstGetAdminEmail(): string
{
    static $cachedEmail = null;
    if ($cachedEmail !== null) {
        return $cachedEmail;
    }

    // Try ConfigService if available (database is initialized)
    if (class_exists(\App\Services\ConfigService::class)) {
        $email = \App\Services\ConfigService::getInstance()->get('app_admin_email', '');
        $cachedEmail = $email;
        return $email;
    }

    $cachedEmail = '';
    return '';
}

/**
 * Check if an error key is currently throttled (recently emailed).
 *
 * @param string $errorKey Unique key for this error (md5 hash)
 * @return bool True if throttled (should not send email)
 */
function sstIsThrottled(string $errorKey): bool
{
    if (!file_exists(ERROR_THROTTLE_FILE)) {
        return false;
    }

    $raw = @file_get_contents(ERROR_THROTTLE_FILE);
    if ($raw === false) {
        return false;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data[$errorKey])) {
        return false;
    }

    /** @var float */
    $lastSent = $data[$errorKey] ?? 0.0;
    return (microtime(true) - $lastSent) < ERROR_THROTTLE_SECONDS;
}

/**
 * Mark an error key as sent (update throttle timestamp).
 *
 * @param string $errorKey Unique key for this error
 */
function sstMarkThrottled(string $errorKey): void
{
    $data = [];
    if (file_exists(ERROR_THROTTLE_FILE)) {
        $raw = @file_get_contents(ERROR_THROTTLE_FILE);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    $data[$errorKey] = microtime(true);

    // Clean up old entries (older than 1 hour) to prevent file bloat
    $cutoff = microtime(true) - 3600;
    foreach ($data as $key => $ts) {
        /** @var float */
        $tsVal = $ts ?? 0.0;
        if ($tsVal < $cutoff) {
            unset($data[$key]);
        }
    }

    @file_put_contents(ERROR_THROTTLE_FILE, json_encode($data, JSON_PRETTY_PRINT));
}
