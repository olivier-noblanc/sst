<?php

/** AccessService — Report access control, visibility mode, and role-based checks. */

namespace App\Services;

use PDO;
use Exception;

class AccessService
{
    /**
     * Centralize access control for a report.
     * Combines role, site, visibility mode and confidentiality checks.
     *
     * @param array<string, mixed> $report  Row from `reports`
     * @param array<string, mixed> $user    $_SESSION['user']
     */
    public function canAccessReport(array $report, array $user, ?string $forcedVisibility = null): bool
    {
        if ($user['role'] === ROLE_SUPERVISEUR) {
            return true;
        }
        if ($user['role'] === ROLE_CHSCT) {
            return ($report['consent_syndicat'] ?? 0) == 1;
        }

        if ((int) $report['site_id'] !== (int) $user['site_id']) {
            return false;
        }

        $visibility = $forcedVisibility ?? $this->getReportVisibilityMode($report['type'] ?? null);

        if ($visibility === 'confidential' && (int) $report['declarant_id'] !== (int) $user['id']) {
            return false;
        }

        if ($visibility === 'agent_choice' && (int) $report['is_confidential'] === 1 && (int) $report['declarant_id'] !== (int) $user['id']) {
            return false;
        }

        return true;
    }

    /**
     * Log access to a confidential report by supervisor/CSA/CHSCT.
     */
    public function logConfidentialReportAccess(PDO $pdo, array $report, array $user): void
    {
        if ((int) $report['is_confidential'] !== 1) {
            return;
        }
        if (!in_array($user['role'], [ROLE_SUPERVISEUR, ROLE_CHSCT], true)) {
            return;
        }
        if ((int) $report['declarant_id'] === (int) $user['id']) {
            return;
        }
        try {
            $stmt = $pdo->prepare('
                INSERT INTO report_access_log (report_uuid, user_id, role)
                VALUES (:report_uuid, :user_id, :role)
            ');
            $stmt->execute([
                ':report_uuid' => $report['uuid'],
                ':user_id'     => (int) $user['id'],
                ':role'        => $user['role'],
            ]);
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
        return in_array($role, [ROLE_SUPERVISEUR, ROLE_CHSCT]);
    }

    /**
     * Normalize a raw config value into a valid visibility mode.
     */
    public function normalizeVisibilityValue(string $value): string
    {
        if ($value === '0' || $value === 'site') {
            return 'public';
        }
        if ($value === '1' || $value === 'own') {
            return 'confidential';
        }
        if (in_array($value, ['confidential', 'agent_choice', 'public'])) {
            return $value;
        }
        return 'agent_choice';
    }

    /**
     * Get the raw report visibility mode from config (role-agnostic).
     */
    public function getReportVisibilityMode(?string $type = null): string
    {
        if ($type !== null) {
            $key = 'app_report_visibility_' . $type;
            $value = \getConfig($key, '');
            if ($value !== '') {
                return $this->normalizeVisibilityValue($value);
            }
        }
        $value = \getConfig('app_report_visibility', 'agent_choice');
        return $this->normalizeVisibilityValue($value);
    }

    /**
     * Get the report visibility for the current user (for reading/filtering).
     */
    public function getReportVisibility(?string $type = null, ?string $role = null): string
    {
        $role ??= \currentUserRole();
        if (empty($role) || $role !== ROLE_AGENT) {
            return 'all';
        }
        return $this->getReportVisibilityMode($type);
    }

    /**
     * Check if report visibility is confidential.
     */
    public function reportVisibilityIsConfidential(?string $type = null): bool
    {
        return $this->getReportVisibilityMode($type) === 'confidential';
    }

    /**
     * Check if report visibility is agent_choice.
     */
    public function reportVisibilityIsAgentChoice(?string $type = null): bool
    {
        return $this->getReportVisibilityMode($type) === 'agent_choice';
    }

    /**
     * Check if report visibility is public.
     */
    public function reportVisibilityIsPublic(?string $type = null): bool
    {
        return $this->getReportVisibilityMode($type) === 'public';
    }

    /**
     * Check if a user can edit a report (must be the declarant AND report must be editable).
     */
    public function canEditReport(array $report, int $userId): bool
    {
        $isDeclarant = ((int) $report['declarant_id'] === $userId);
        return $isDeclarant && in_array($report['etat'], [ETAT_NOUVEAU, ETAT_EN_COURS]);
    }

    /**
     * Check if a user can respond to a report (must be superviseur AND report must be editable).
     */
    public function canRespondToReport(array $report, string $role): bool
    {
        return in_array($role, [ROLE_SUPERVISEUR]) && in_array($report['etat'], [ETAT_NOUVEAU, ETAT_EN_COURS, ETAT_REOUVERT]);
    }
}
