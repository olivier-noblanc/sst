<?php

use App\Services\HttpService;

/**
 * HTTP & Response Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\HttpService.
 */

function getHttpService(): HttpService
{
    if (function_exists('getContainer') && getContainer()->has(HttpService::class)) {
        return getContainer()->get(HttpService::class);
    }
    return new HttpService();
}

/**
 * @param array<string, string|int|null> $params
 */
function url(string $page, array $params = []): string
{
    return getHttpService()->url($page, $params);
}

function redirect(string $url): void
{
    getHttpService()->redirect($url);
}

function removeUnwantedHeaders(): void
{
    getHttpService()->removeUnwantedHeaders();
}

function sendFileDownload(string $content, string $filename, string $contentType, string $disposition = 'attachment'): void
{
    getHttpService()->sendFileDownload($content, $filename, $contentType, $disposition);
}

/**
 * @param list<string>|null $roles
 */
function validatePostRequest(string $fallbackUrl, ?array $roles = null, ?string $csrfToken = null): void
{
    getHttpService()->validatePostRequest($fallbackUrl, $roles, $csrfToken);
}

/**
 * Debug helper - log session state for E2E debugging
 */
function logSessionState(string $context): void
{
    if (defined('DEV_MODE') && DEV_MODE) {
        $sessionId = session_id() ?: 'no-session-id';
        $sessionName = session_name();
        $sessionStatus = session_status();
        $csrfTokens = $_SESSION['csrf_tokens'] ?? [];
        $user = $_SESSION['user'] ?? null;
        $flash = $_SESSION['flash'] ?? null;
        error_log("[SST-DEBUG] $context - session_name=$sessionName, session_id=$sessionId, status=$sessionStatus, csrf_count=" . count($csrfTokens) . ", user=" . ($user ? $user['username'] : 'none') . ", flash=" . ($flash ? $flash['type'] : 'none'));
    }
}

function flashAndRedirect(string $type, string $message, string $url): void
{
    getHttpService()->flashAndRedirect($type, $message, $url);
}
