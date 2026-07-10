<?php

use App\Services\HttpService;

/**
 * HTTP & Response Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\HttpService.
 */

function url(string $page, array $params = []): string
{
    return (new HttpService())->url($page, $params);
}

function redirect(string $url): void
{
    (new HttpService())->redirect($url);
}

function setCookieSafe(string $name, string $value = '', int $expires = 0, string $path = '/', bool $httpOnly = true, string $sameSite = 'Lax'): void
{
    (new HttpService())->setCookieSafe($name, $value, $expires, $path, $httpOnly, $sameSite);
}

function removeUnwantedHeaders(): void
{
    (new HttpService())->removeUnwantedHeaders();
}

function sendFileDownload(string $content, string $filename, string $contentType, string $disposition = 'attachment'): void
{
    (new HttpService())->sendFileDownload($content, $filename, $contentType, $disposition);
}

function validatePostRequest(string $fallbackUrl, ?array $roles = null, ?string $csrfToken = null): void
{
    (new HttpService())->validatePostRequest($fallbackUrl, $roles, $csrfToken);
}

function flashAndRedirect(string $type, string $message, string $url): void
{
    (new HttpService())->flashAndRedirect($type, $message, $url);
}
