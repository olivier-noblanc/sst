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
 * @param array<string, mixed> $params
 */
function url(string $page, array $params = []): string
{
    return getHttpService()->url($page, $params);
}

function redirect(string $url): void
{
    getHttpService()->redirect($url);
}

function setCookieSafe(string $name, string $value = '', int $expires = 0, string $path = '/', bool $httpOnly = true, string $sameSite = 'Lax'): void
{
    getHttpService()->setCookieSafe($name, $value, $expires, $path, $httpOnly, $sameSite);
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

function flashAndRedirect(string $type, string $message, string $url): void
{
    getHttpService()->flashAndRedirect($type, $message, $url);
}
