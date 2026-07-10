<?php

/**
 * Error Handler — Application SST DREETS BFC
 *
 * Custom error handler that:
 * 1. Logs all errors to the PHP error log (default behavior preserved)
 * 2. Sends email notifications to the technical admin for critical errors
 *
 * Admin notification and throttle functions are in error_notify.php.
 */

require_once __DIR__ . '/error_notify.php';

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
    $levelName = match ($errno) {
        E_ERROR, E_USER_ERROR            => 'Fatal error',
        E_WARNING, E_USER_WARNING        => 'Warning',
        E_PARSE                          => 'Parse error',
        E_NOTICE, E_USER_NOTICE          => 'Notice',
        // E_STRICT deprecated since PHP 8.4 – omitted
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

    $levelName = match ($error['type']) {
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
        } catch (Exception) {
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
