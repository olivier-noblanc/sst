<?php

/** SessionDataService — user session state, impersonation, flash messages, form data. */

namespace App\Services;

use App\DTO\FlashMessage;
use App\DTO\FormData;
use App\DTO\SessionUser;

class SessionDataService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // User session — authentication state
    // ═══════════════════════════════════════════════════════════════════════════════

    public function isUserLoggedIn(): bool
    {
        return isset($_SESSION['user']);
    }

    public function setUserSession(SessionUser $user): void
    {
        $_SESSION['user'] = $user->toArray();
    }

    public function getUserSession(): ?SessionUser
    {
        $data = $_SESSION['user'] ?? null;
        if (!is_array($data)) {
            return null;
        }
        return SessionUser::fromSession($data);
    }

    public function clearSession(): void
    {
        $_SESSION = [];
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Intended URL — redirect after login
    // ═══════════════════════════════════════════════════════════════════════════════

    public function setIntendedUrl(string $url): void
    {
        $_SESSION['intended_url'] = $url;
    }

    public function getIntendedUrl(): ?string
    {
        $url = $_SESSION['intended_url'] ?? null;
        return $url;
    }

    public function clearIntendedUrl(): ?string
    {
        $url = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);
        return $url;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Impersonation — role switching by superviseur
    // ═══════════════════════════════════════════════════════════════════════════════

    public function startImpersonation(string $realRole, string $targetRole): void
    {
        $_SESSION['real_role'] = $realRole;
        $_SESSION['impersonated_role'] = $targetRole;
        $data = $_SESSION['user'] ?? null;
        if (is_array($data)) {
            $user = SessionUser::fromSession($data);
            $_SESSION['user'] = $user->withRole($targetRole)->toArray();
        }
        // Audit #33 — regenerate session ID on impersonation start.
        // Prevents session fixation: if an attacker steals the session cookie
        // before impersonation starts, they can't hijack the impersonated role.
        // Same logic as login — session ID changes, old cookie is invalidated.
        SessionTokenService::getInstance()->refreshSessionId();
    }

    public function stopImpersonation(): ?string
    {
        if (!isset($_SESSION['real_role'])) {
            return null;
        }
        $realRole = $_SESSION['real_role'];
        $data = $_SESSION['user'] ?? null;
        if (is_array($data)) {
            $user = SessionUser::fromSession($data);
            $_SESSION['user'] = $user->withRole($realRole)->toArray();
        }
        unset($_SESSION['real_role']);
        unset($_SESSION['impersonated_role']);
        return $realRole;
    }

    public function isImpersonatingRole(): bool
    {
        return isset($_SESSION['real_role']);
    }

    public function getImpersonatedRole(): ?string
    {
        $role = $_SESSION['impersonated_role'] ?? null;
        return $role;
    }

    public function getRealRole(): ?string
    {
        $role = $_SESSION['real_role'] ?? null;
        return $role;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Flash messages
    // ═══════════════════════════════════════════════════════════════════════════════

    public function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = new FlashMessage($type, $message)->toArray();
    }

    public function getFlash(): ?FlashMessage
    {
        if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
            /** @var array{type?: mixed, message?: mixed} $raw */
            $raw = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return FlashMessage::fromSession($raw);
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Form data & errors (from session_form.php)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function setFormData(FormData $data): void
    {
        $_SESSION['form_data'] = $data->toArray();
    }

    public function getFormData(): FormData
    {
        if (isset($_SESSION['form_data']) && is_array($_SESSION['form_data'])) {
            /** @var array<string, mixed> $data */
            $data = $_SESSION['form_data'];
            unset($_SESSION['form_data']);
            return FormData::fromSession($data);
        }
        return new FormData();
    }

    /**
     * @param array<string, string> $errors
     */
    public function setFormErrors(array $errors): void
    {
        $_SESSION['form_errors'] = $errors;
    }

    /**
     * @return array<string, string>
     */
    public function getFormErrors(): array
    {
        if (isset($_SESSION['form_errors'])) {
            /** @var array<string, string> $errors */
            $errors = $_SESSION['form_errors'];
            unset($_SESSION['form_errors']);
            return $errors;
        }
        return [];
    }

    /**
     * @param array<string, string|null> $errors
     */
    public function getFieldError(array $errors, string $field): ?string
    {
        return $errors[$field] ?? null;
    }
}
