<?php

use App\Services\AssetService;

/**
 * Asset Helpers — Application SST DREETS BFC
 *
 * Delegates to App\Services\AssetService.
 */

function cssLink(string $path): string
{
    return (new AssetService())->cssLink($path);
}

function assetUrl(string $path): string
{
    return (new AssetService())->assetUrl($path);
}

function inlineDataUri(string $path): string
{
    return (new AssetService())->inlineDataUri($path);
}
