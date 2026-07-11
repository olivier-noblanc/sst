<?php

use App\Services\CryptoService;

/**
 * Crypto Helpers — Application SST DREETS BFC
 *
 * AES-256-CBC encryption/decryption for sensitive config values.
 * Delegates to App\Services\CryptoService.
 */

function getCryptoService(): CryptoService
{
    if (function_exists('getContainer') && getContainer()->has(CryptoService::class)) {
        return getContainer()->get(CryptoService::class);
    }
    return new CryptoService();
}

function encryptConfigValue(string $plaintext): string
{
    return getCryptoService()->encrypt($plaintext);
}

function decryptConfigValue(string $value): string
{
    return getCryptoService()->decrypt($value);
}
