<?php

/** CryptoService — AES-256-CBC encryption/decryption for sensitive config values. */

namespace App\Services;

class CryptoService
{
    /**
     * Check if encryption is available (SST_SECRET_KEY is configured and valid).
     */
    public function isEncryptionAvailable(): bool
    {
        $key = getenv('SST_SECRET_KEY');
        return $key !== false && strlen($key) >= 32;
    }

    /**
     * Encrypt a value with AES-256-CBC using SST_SECRET_KEY env var.
     * Returns "enc:base64(iv + ciphertext)". Returns plain value unchanged if encryption unavailable.
     */
    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = getenv('SST_SECRET_KEY');
        if ($key === false || strlen($key) < 32) {
            error_log('[SST-CRYPTO] SST_SECRET_KEY missing or too short — cannot encrypt. Set a 32+ character key in IIS environment variables.');
            return $plaintext;
        }
        $key = substr($key, 0, 32);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            error_log('[SST-CRYPTO] openssl_encrypt failed — returning plaintext.');
            return $plaintext;
        }
        return 'enc:' . base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypt a value encrypted by encrypt().
     * Detects the "enc:" prefix. Returns plain value unchanged if not encrypted.
     */
    public function decrypt(string $value): string
    {
        if ($value === '' || !str_starts_with($value, 'enc:')) {
            return $value;
        }
        $key = getenv('SST_SECRET_KEY');
        if ($key === false || strlen($key) < 32) {
            error_log('[SST-CRYPTO] SST_SECRET_KEY missing or too short — cannot decrypt encrypted value.');
            return $value;
        }
        $key = substr($key, 0, 32);
        $decoded = base64_decode(substr($value, 4), true);
        if ($decoded === false || strlen($decoded) < 17) {
            error_log('[SST-CRYPTO] Invalid encrypted value — base64 decode failed or too short.');
            return $value;
        }
        $iv = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            error_log('[SST-CRYPTO] openssl_decrypt failed — wrong key or corrupted data.');
            return $value;
        }
        return $decrypted;
    }

}
