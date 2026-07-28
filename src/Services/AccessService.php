<?php

/** AccessService — Report access control, visibility mode, and role-based checks. */

namespace App\Services;

use App\Enum\ReportState;
use App\Enum\ReportType;
use App\Enum\VisibilityMode;
use App\Enum\UserRole;
use PDO;
use Exception;
use App\Repository\ReportRepository;
use App\Repository\RegistryRepository;

class AccessService
{
    /**
     * Normalize a raw config value into a valid CHSCT report scope.
     */
    public function normalizeChsctScope(string $value): string
    {
        if (in_array($value, ['consent_only', 'all'], true)) {
            return $value;
        }
        return 'consent_only';
    }

    /**
     * Get the CHSCT report scope from config: 'consent_only' or 'all'.
     */
    public function getChsctReportScope(): string
    {
        $value = getConfigService()->get('app_chsct_report_scope', 'consent_only');
        return $this->normalizeChsctScope($value);
    }

    /**
     * Centralize access control for a report.
     * Combines role, site, visibility mode and confidentiality checks.
     *
     * @param array<string, mixed> $report  Row from `reports`
     * @param array<string, mixed> $user    $_SESSION['user']
     */
    public function canAccessReport(array $report, array $user, ?string $forcedVisibility = null): bool
    {
        if ($user['role'] === UserRole::Superviseur->value) {
            return true;
        }
        if ($user['role'] === UserRole::Chsct->value) {
            if ($this->getChsctReportScope() === 'all') {
                return true;
            }
            return (int) ($report['consent_syndicat'] ?? 0) === 1;
        }

        $visibility = $forcedVisibility ?? $this->getReportVisibilityMode($report['type'] ?? null);
        $reportDeclarantId = (int) ($report['declarant_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        // Linked agents have the same read access as the declarant
        $isLinkedAgent = isset($report['uuid'])
            ? ReportRepository::instance()->isLinkedAgent((string) $report['uuid'], $userId)
            : false;

        if ($visibility === VisibilityMode::Confidential->value && $reportDeclarantId !== $userId && !$isLinkedAgent) {
            return false;
        }

        $isConfidential = (int) ($report['is_confidential'] ?? 0);
        if ($visibility === VisibilityMode::AgentChoice->value && $isConfidential === 1 && $reportDeclarantId !== $userId && !$isLinkedAgent) {
            return false;
        }

        return true;
    }

    /**
     * Log access to a confidential report by supervisor/CSA/CHSCT.
     *
     * @param array<string, mixed> $report
     * @param array<string, mixed> $user
     */
    public function logConfidentialReportAccess(PDO $pdo, array $report, array $user): void
    {
        $isConfidential = (int) ($report['is_confidential'] ?? 0);
        if ($isConfidential !== 1) {
            return;
        }
        if (!in_array($user['role'], [UserRole::Superviseur->value, UserRole::Chsct->value], true)) {
            return;
        }
        $reportDeclarantId = (int) ($report['declarant_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        if ($reportDeclarantId === $userId) {
            return;
        }
        try {
            ReportRepository::instance()->logAccess((string) $report['uuid'], $userId, (string) $user['role']);
        } catch (Exception $e) {
            error_log('[SST-ACCESS-LOG] Failed to log report access: ' . $e->getMessage());
        }
    }

    /**
     * Check if the current user can see all sites.
     */
    public function canSeeAllSites(?string $role = null): bool
    {
        $role ??= \currentUserRole();
        if (empty($role)) {
            return false;
        }
        return UserRole::tryFrom($role)?->canSeeAllSites() ?? false;
    }

    /**
     * Normalize a raw config value into a valid visibility mode.
     */
    public function normalizeVisibilityValue(string $value): string
    {
        if ($value === '0' || $value === 'site') {
            return VisibilityMode::Public->value;
        }
        if ($value === '1' || $value === 'own') {
            return VisibilityMode::Confidential->value;
        }
        $mode = VisibilityMode::tryFrom($value);
        return $mode !== null ? $mode->value : VisibilityMode::AgentChoice->value;
    }

    /**
     * Get the raw report visibility mode from config (role-agnostic).
     *
     * Custom registries (any code outside the fixed rsst/rami/dgi ReportType
     * enum) have no entry in Paramètres > Signalements (that tab only lists
     * the 3 ReportType cases), so an 'app_report_visibility_{code}' config key
     * is never created for them via any admin screen. Their only real visibility control is "Visibilité par
     * défaut" (registries.default_visibility), set in Paramètres > Registres.
     * Without the fallback below, that setting was silently ignored and every
     * custom registry always inherited the global default regardless of what
     * the admin configured there — including "confidentiel".
     */
    public function getReportVisibilityMode(?string $type = null): string
    {
        if ($type !== null) {
            $key = 'app_report_visibility_' . $type;
            $value = getConfigService()->get($key, '');
            if ($value !== '') {
                return $this->normalizeVisibilityValue($value);
            }

            $registry = $this->findCustomRegistry($type);
            if ($registry !== null && !empty($registry['default_visibility'])) {
                return $this->normalizeVisibilityValue((string) $registry['default_visibility']);
            }
        }
        $value = getConfigService()->get('app_report_visibility', VisibilityMode::AgentChoice->value);
        return $this->normalizeVisibilityValue($value);
    }

    /**
     * @return array{id: int, code: string, label: string, short_label: string, description: string, icon: string, color_theme: string, btn_label: ?string, is_enabled: int, is_system: int, sort_order: int, default_visibility: string, notify_chsct: int, legal_note: string, requires_pour_compte: int, has_dgi_warning: int, lieu_label_override: ?string, created_at: string, updated_at: ?string}|null
     */
    private function findCustomRegistry(string $type): ?array
    {
        static $cache = [];
        if (array_key_exists($type, $cache)) {
            return $cache[$type];
        }
        if (ReportType::tryFrom($type) !== null) {
            $cache[$type] = null; // rsst/rami/dgi: always governed by Paramètres > Signalements
            return null;
        }
        $registry = RegistryRepository::instance()->findByCode($type);
        $cache[$type] = $registry;
        return $registry;
    }

    /**
     * Get the report visibility for the current user (for reading/filtering).
     */
    public function getReportVisibility(?string $type = null, ?string $role = null): string
    {
        $role ??= \currentUserRole();
        if (empty($role) || $role !== UserRole::Agent->value) {
            return 'all';
        }
        return $this->getReportVisibilityMode($type);
    }

    /**
     * Check if report visibility is confidential.
     */
    public function reportVisibilityIsConfidential(?string $type = null): bool
    {
        return $this->getReportVisibilityMode($type) === VisibilityMode::Confidential->value;
    }

    /**
     * Check if report visibility is agent_choice.
     */
    public function reportVisibilityIsAgentChoice(?string $type = null): bool
    {
        return $this->getReportVisibilityMode($type) === VisibilityMode::AgentChoice->value;
    }

    /**
     * Check if report visibility is public.
     */
    public function reportVisibilityIsPublic(?string $type = null): bool
    {
        return $this->getReportVisibilityMode($type) === VisibilityMode::Public->value;
    }

    /**
     * Check if a user can edit a report (must be the declarant AND report must be editable).
     *
     * @param array<string, mixed> $report
     */
    public function canEditReport(array $report, int $userId): bool
    {
        $isDeclarant = ($report['declarant_id'] === $userId);
        return $isDeclarant && in_array($report['etat'], [ReportState::Nouveau->value, ReportState::EnCours->value], true);
    }

    /**
     * Check if a user can respond to a report (must be superviseur AND report must be editable).
     *
     * @param array<string, mixed> $report
     */
    public function canRespondToReport(array $report, string $role): bool
    {
        return in_array($role, [UserRole::Superviseur->value], true) && in_array($report['etat'], [ReportState::Nouveau->value, ReportState::EnCours->value, ReportState::Reouvert->value], true);
    }
}
