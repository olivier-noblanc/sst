<?php

/**
 * Error Handler — Application SST DREETS BFC
 *
 * Custom error handler that:
 * 1. Logs all errors to the PHP error log (default behavior preserved)
 * 2. Sends email notifications to the technical admin for critical errors
 *
 * Rate limiting: max 1 email per error type per 5 minutes to avoid flooding.
 * The throttle state is stored in data/error-throttle.json.
 *
 * Critical errors that trigger an email:
 * - E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR (fatal)
 * - E_USER_ERROR (triggered by trigger_error)
 * - E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING (warnings)
 * - E_NOTICE, E_USER_NOTICE, E_STRICT, E_DEPRECATED are NOT emailed (too noisy)
 */

/** Throttle file path */
define('ERROR_THROTTLE_FILE', __DIR__ . '/../data/error-throttle.json');

/** Minimum seconds between identical error emails */
define('ERROR_THROTTLE_SECONDS', 300); // 5 minutes

/**
 * Custom error handler for non-fatal errors.
 * Fatal errors are handled by sstShutdownHandler().
 *
 * @param int    $errno   Error level
 * @param string $errstr  Error message
 * @param string $errfile File where error occurred
 * @param int    $errline Line number
 * @return bool  True to prevent PHP's default handler
 */
function sstErrorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
{
    // Respect @ error suppression operator
    if (!(error_reporting() & $errno)) {
        return false;
    }

    // Log to PHP error log (standard format)
    $levelName = match($errno) {
        E_ERROR, E_USER_ERROR            => 'Fatal error',
        E_WARNING, E_USER_WARNING        => 'Warning',
        E_PARSE                          => 'Parse error',
        E_NOTICE, E_USER_NOTICE          => 'Notice',
        E_STRICT                         => 'Strict Standards',
        E_DEPRECATED, E_USER_DEPRECATED  => 'Deprecated',
        E_CORE_ERROR                     => 'Core error',
        E_CORE_WARNING                   => 'Core warning',
        E_COMPILE_ERROR                  => 'Compile error',
        E_COMPILE_WARNING                => 'Compile warning',
        E_RECOVERABLE_ERROR              => 'Recoverable error',
        default                          => 'Unknown error',
    };

    $logMessage = "[$levelName] $errstr in $errfile on line $errline";
    error_log($logMessage);

    // Email only for critical errors (not notices, deprecated, strict)
    $shouldEmail = in_array($errno, [
        E_ERROR, E_USER_ERROR, E_PARSE,
        E_CORE_ERROR, E_COMPILE_ERROR,
        E_RECOVERABLE_ERROR,
    ]);

    if ($shouldEmail) {
        sstNotifyAdminError($levelName, $errstr, $errfile, $errline, $errno);
    }

    // Return true to prevent PHP's default error handler for warnings/notices
    // For fatal errors, let PHP handle its own shutdown
    return true;
}

/**
 * Shutdown handler for fatal errors that bypass the error handler.
 * Catches E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR.
 * In production (display_errors=Off), renders a clean HTML error page.
 */
function sstShutdownHandler(): void
{
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    // Only handle truly fatal errors
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array($error['type'], $fatalTypes)) {
        return;
    }

    $levelName = match($error['type']) {
        E_ERROR             => 'Fatal error',
        E_PARSE             => 'Parse error',
        E_CORE_ERROR        => 'Core error',
        E_COMPILE_ERROR     => 'Compile error',
        E_RECOVERABLE_ERROR => 'Recoverable error',
    };

    sstNotifyAdminError(
        $levelName,
        $error['message'],
        $error['file'],
        $error['line'],
        $error['type']
    );

    // In production, render a clean error page for the user
    // BUT: if app_display_errors is '1' (admin toggle), show the real error instead
    $adminDisplayErrors = false;
    if (function_exists('getConfig')) {
        try {
            $adminDisplayErrors = (getConfig('app_display_errors', '') === '1');
        } catch (Exception $e) {
        }
    }
    if ((!defined('DEV_MODE') || !DEV_MODE) && !$adminDisplayErrors) {
        sstRenderProductionErrorPage($levelName);
    }
}

/**
 * Render a clean HTML error page for production users.
 * Called by sstShutdownHandler when display_errors is Off.
 * This avoids the "white screen of death" and provides a user-friendly message.
 */
function sstRenderProductionErrorPage(string $levelName): void
{
    // Don't render if headers already sent (output buffering may have started)
    if (headers_sent()) {
        // Try to close any open buffers cleanly
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    $appName = defined('APP_NAME') ? htmlspecialchars(APP_NAME) : 'Application SST';

    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$appName} — Erreur</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .error-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 40px; max-width: 500px; text-align: center; }
        .error-icon { font-size: 48px; margin-bottom: 16px; }
        h1 { color: #c0392b; font-size: 20px; margin: 0 0 12px 0; }
        p { color: #666; line-height: 1.6; margin: 0 0 20px 0; }
        a { color: #2980b9; text-decoration: none; } a:hover { text-decoration: underline; }
        .small { font-size: 13px; color: #999; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">&#9888;</div>
        <h1>Une erreur est survenue</h1>
        <p>Une erreur interne a emp&ecirc;ch&eacute; cette page de s'afficher correctement.<br>
        L'administrateur technique a &eacute;t&eacute; notifi&eacute; automatiquement.</p>
        <a href="javascript:history.back()">&#8592; Retour &agrave; la page pr&eacute;c&eacute;dente</a>
        <p class="small">Si le probl&egrave;me persiste, contactez votre administrateur.</p>
    </div>
</body>
</html>
HTML;
}

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
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
    $httpMethod = $_SERVER['REQUEST_METHOD'] ?? '';
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

    // Try getConfig() if available (database is initialized)
    if (function_exists('getConfig')) {
        $email = getConfig('app_admin_email', '');
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

    $data = @json_decode(file_get_contents(ERROR_THROTTLE_FILE), true);
    if (!is_array($data) || !isset($data[$errorKey])) {
        return false;
    }

    $lastSent = (float) $data[$errorKey];
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
    foreach ($data as $key => $timestamp) {
        if ((float) $timestamp < $cutoff) {
            unset($data[$key]);
        }
    }

    @file_put_contents(ERROR_THROTTLE_FILE, json_encode($data, JSON_PRETTY_PRINT));
}
