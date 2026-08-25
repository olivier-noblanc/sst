<?php

/** SessionTokenService — CSRF tokens and session id regeneration. */

namespace App\Services;

class SessionTokenService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate a CSRF token for form protection.
     * Reuses the most recent valid token if present to avoid accumulation on page refresh.
     * New token is generated only if no valid token exists (all consumed or expired).
     * Max 50 tokens to support multiple tabs/forms simultaneously.
     *
     * Audit #28 — Before this fix, a NEW token was generated on EVERY GET request,
     * causing accumulation even with a single tab (refresh = new token).
     * Now: reuses most recent valid token → no spam on simple refresh, but still
     * supports multiple tabs/forms (up to 50).
     */
    public function generateCsrfToken(): string
    {
        SessionService::getInstance()->startSession();
        /** @var array<string, int> $tokens */
        $tokens = is_array($_SESSION['csrf_tokens'] ?? null) ? $_SESSION['csrf_tokens'] : [];

        // Reuse the most recent valid token if present (avoids accumulation on refresh)
        if (!empty($tokens)) {
            // Get the most recent token (last one in array, as arrays maintain insertion order)
            $mostRecentToken = array_key_last($tokens);
            $mostRecentTimestamp = $tokens[$mostRecentToken];
            if (time() - $mostRecentTimestamp < 3600) { // 1 hour validity
                return $mostRecentToken;
            }
        }

        // No valid token exists, generate new one
        $token = bin2hex(random_bytes(32));
        $tokens[$token] = time();

        // Enforce limit of 50 tokens (for multiple tabs/forms support)
        $limit = 50;
        if (count($tokens) > $limit) {
            $evicted = count($tokens) - $limit;
            $tokens = array_slice($tokens, -$limit, null, true);
            // Log eviction warning only once per session to avoid log spam
            if (empty($_SESSION['csrf_eviction_logged'])) {
                error_log("[SST-CSRF] Evicting {$evicted} old CSRF token(s) — limit={$limit}. User may have many tabs open.");
                $_SESSION['csrf_eviction_logged'] = true;
            }
        }

        $_SESSION['csrf_tokens'] = $tokens;
        return $token;
    }

    /**
     * Validate a CSRF token and consume it (one-time use).
     */
    public function validateCsrfToken(string $token): bool
    {
        SessionService::getInstance()->startSession();
        /** @var array<string, int> $tokens */
        $tokens = is_array($_SESSION['csrf_tokens'] ?? null) ? $_SESSION['csrf_tokens'] : [];
        if (empty($token) || !isset($tokens[$token])) {
            return false;
        }
        unset($tokens[$token]);
        $_SESSION['csrf_tokens'] = $tokens;
        return true;
    }

    /**
     * Safe session regeneration that works with PHP built-in server.
     */
    public function refreshSessionId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(!DEV_MODE);
        }
    }
}
