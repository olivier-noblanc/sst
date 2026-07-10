<?php

use App\Services\CryptoService;

/**
 * Crypto Helpers — Application SST DREETS BFC
 *
 * AES-256-CBC encryption/decryption for sensitive config values.
 * Delegates to App\Services\CryptoService.
 */

function encryptConfigValue(string $plaintext): string
{
    return (new CryptoService())->encrypt($plaintext);
}

function decryptConfigValue(string $value): string
{
    return (new CryptoService())->decrypt($value);
}
