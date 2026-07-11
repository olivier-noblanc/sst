<?php

use App\Services\AssetService;

/**
 * Asset Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\AssetService.
 */

function getAssetService(): AssetService
{
    if (function_exists('getContainer') && getContainer()->has(AssetService::class)) {
        return getContainer()->get(AssetService::class);
    }
    return new AssetService();
}

function cssLink(string $path): string
{
    return getAssetService()->cssLink($path);
}

function assetUrl(string $path): string
{
    return getAssetService()->assetUrl($path);
}

function inlineDataUri(string $path): string
{
    return getAssetService()->inlineDataUri($path);
}
