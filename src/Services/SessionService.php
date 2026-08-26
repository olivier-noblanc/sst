<?php

/** SessionService — Session management, CSRF, flash messages, form data, impersonation. */

namespace App\Services;

use App\DTO\FlashMessage;
use App\DTO\FormData;
use App\DTO\SessionUser;

class SessionService
{
    private static ?self $instance = null;

    private readonly CookieService $cookieService;
    private readonly SessionDataService $dataService;
    private readonly SessionTokenService $tokenService;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->cookieService = CookieService::getInstance();
        $this->dataService = SessionDataService::getInstance();
        $this->tokenService = SessionTokenService::getInstance();
    }
    // ═══════════════════════════════════════════════════════════════════════════════
    // Session startup
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Start the PHP session with secure settings.
     *
     * Uses SQLiteSessionHandler in DEV_MODE/CI for reliable session storage.
     * Clears any legacy PHPSESSID cookie to prevent session fragmentation
     * when the canonical session name is SST_SESSION.
     */
    public function startSession(): void
    {
        $this->cookieService->clearLegacySessionCookie('PHPSESSID');

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            $this->cookieService->configureSessionCookie();
            // Force garbage collection settings explicitly — don't rely on
            // the server's php.ini. Debian/Ubuntu-packaged PHP sets
            // gc_probability=0 by default (cleanup handled by an external
            // cron job instead, see /etc/cron.d/php*) — this app runs on
            // Windows/IIS with no such cron, so if the deployed php.ini
            // ever mirrors that convention (or is otherwise misconfigured),
            // SQLiteSessionHandler::gc() would simply never run and the
            // sessions table would grow forever, silently, with no
            // application-level signal that anything is wrong.
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
            ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24)); // 24h
            session_name('SST_SESSION');

            // Use SQLite session handler in DEV_MODE/CI for reliable session storage
            // (avoids file permission issues with file-based sessions in CI)
            if ((defined('DEV_MODE') && DEV_MODE) || getenv('CI') === 'true') {
                if (!function_exists('getDB')) {
                    require_once __DIR__ . '/../database.php';
                }
                $pdo = getDB();
                $handler = new SQLiteSessionHandler($pdo);
                session_set_save_handler($handler, true);
            }

            session_start();

            // Debug logging for E2E troubleshooting
            if ((defined('DEV_MODE') && DEV_MODE) || getenv('CI') === 'true') {
                $incomingCookie = $_COOKIE['SST_SESSION'] ?? 'none';
                $sessionId = session_id();
                $isNewSession = !isset($_SESSION['csrf_tokens']) && !isset($_SESSION['user']);
                error_log('[SST-SESSION] startSession - incoming_cookie=' . substr((string) $incomingCookie, 0, 16) . '..., new_session_id=' . $sessionId . ', is_new=' . ($isNewSession ? 'yes' : 'no') . ', path=' . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ', handler=SQLite');
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // User session — authentication state
    // ═══════════════════════════════════════════════════════════════════════════════

    public function isUserLoggedIn(): bool
    {
        return $this->dataService->isUserLoggedIn();
    }

    public function setUserSession(SessionUser $user): void
    {
        $this->dataService->setUserSession($user);
    }

    public function getUserSession(): ?SessionUser
    {
        return $this->dataService->getUserSession();
    }

    public function clearSession(): void
    {
        $this->dataService->clearSession();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Intended URL — redirect after login
    // ═══════════════════════════════════════════════════════════════════════════════

    public function setIntendedUrl(string $url): void
    {
        $this->dataService->setIntendedUrl($url);
    }

    public function getIntendedUrl(): ?string
    {
        return $this->dataService->getIntendedUrl();
    }

    public function clearIntendedUrl(): ?string
    {
        return $this->dataService->clearIntendedUrl();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Impersonation — role switching by superviseur
    // ═══════════════════════════════════════════════════════════════════════════════

    public function startImpersonation(string $realRole, string $targetRole): void
    {
        $this->dataService->startImpersonation($realRole, $targetRole);
    }

    public function stopImpersonation(): ?string
    {
        return $this->dataService->stopImpersonation();
    }

    public function isImpersonatingRole(): bool
    {
        return $this->dataService->isImpersonatingRole();
    }

    public function getImpersonatedRole(): ?string
    {
        return $this->dataService->getImpersonatedRole();
    }

    public function getRealRole(): ?string
    {
        return $this->dataService->getRealRole();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // CSRF token — delegated to SessionTokenService
    // ═══════════════════════════════════════════════════════════════════════════════

    public function generateCsrfToken(): string
    {
        return $this->tokenService->generateCsrfToken();
    }

    public function validateCsrfToken(string $token): bool
    {
        return $this->tokenService->validateCsrfToken($token);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Flash messages
    // ═══════════════════════════════════════════════════════════════════════════════

    public function setFlash(string $type, string $message): void
    {
        $this->dataService->setFlash($type, $message);
    }

    public function getFlash(): ?FlashMessage
    {
        return $this->dataService->getFlash();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Form data & errors (from session_form.php)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function setFormData(FormData $data): void
    {
        $this->dataService->setFormData($data);
    }

    public function getFormData(): FormData
    {
        return $this->dataService->getFormData();
    }

    /**
     * @param array<string, string> $errors
     */
    public function setFormErrors(array $errors): void
    {
        $this->dataService->setFormErrors($errors);
    }

    /**
     * @return array<string, string>
     */
    public function getFormErrors(): array
    {
        return $this->dataService->getFormErrors();
    }

    /**
     * @param array<string, string|null> $errors
     */
    public function getFieldError(array $errors, string $field): ?string
    {
        return $this->dataService->getFieldError($errors, $field);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Session patch (from session_patch.php)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function safeSessionRegenerate(): void
    {
        $this->tokenService->refreshSessionId();
    }
}
