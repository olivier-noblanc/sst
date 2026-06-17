<?php
/**
 * Access Control Helpers — Application SST DREETS BFC
 *
 * Report access control, visibility mode, and role-based checks.
 * Extracted from helpers.php for single-responsibility clarity.
 */

/**
 * Centralize access control for a report.
 * Combines role, site, visibility mode and confidentiality checks.
 *
 * @param array $report  Row from `reports` (site_id, declarant_id, is_confidential, type)
 * @param array $user    $_SESSION['user'] (id, site_id, role)
 * @return bool
 */
function canAccessReport(array $report, array $user, ?string $forcedVisibility = null): bool {
    // Superviseur/CSA/CHSCT can always see everything
    if (in_array($user['role'], [ROLE_SUPERVISEUR, ROLE_CHSCT], true)) {
        return true;
    }

    // Agent can never see reports from other sites
    if ((int) $report['site_id'] !== (int) $user['site_id']) {
        return false;
    }

    // Use forced visibility mode (for tests) or read from config
    $visibility = $forcedVisibility ?? getReportVisibilityMode($report['type'] ?? null);

    if ($visibility === 'confidential' && (int) $report['declarant_id'] !== (int) $user['id']) {
        // In confidential mode, agent can ONLY see their own reports
        return false;
    }

    if ($visibility === 'agent_choice' && (int) $report['is_confidential'] === 1 && (int) $report['declarant_id'] !== (int) $user['id']) {
        // In agent_choice mode, agent cannot see other agents' confidential reports
        return false;
    }

    return true;
}

/**
 * Log access to a confidential report by supervisor/CSA/CHSCT.
 * Only logs when a superviseur/chsct consults a report with is_confidential=1
 * that they did not file themselves.
 *
 * @param PDO   $pdo     Database connection
 * @param array $report  Row from `reports`
 * @param array $user    $_SESSION['user']
 */
function logConfidentialReportAccess(PDO $pdo, array $report, array $user): void {
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
        $stmt = $pdo->prepare("
            INSERT INTO report_access_log (report_uuid, user_id, role)
            VALUES (:report_uuid, :user_id, :role)
        ");
        $stmt->execute([
            ':report_uuid' => $report['uuid'],
            ':user_id'     => (int) $user['id'],
            ':role'        => $user['role'],
        ]);
    } catch (Exception $e) {
        // Logging must NEVER break the application
        error_log('[SST-ACCESS-LOG] Failed to log report access: ' . $e->getMessage());
    }
}

/**
 * Check if the current user can see all sites.
 */
function canSeeAllSites(): bool {
    $role = currentUserRole();
    if (empty($role)) {
        return false;
    }
    return in_array($role, [ROLE_SUPERVISEUR, ROLE_CHSCT]);
}

/**
 * Normalize a raw config value into a valid visibility mode.
 */
function normalizeVisibilityValue(string $value): string {
    if ($value === '0' || $value === 'site') return 'public';
    if ($value === '1' || $value === 'own') return 'confidential';
    if (in_array($value, ['confidential', 'agent_choice', 'public'])) {
        return $value;
    }
    return 'agent_choice';
}

/**
 * Get the raw report visibility mode from config (role-agnostic).
 * Supports per-registry keys (app_report_visibility_rsst/rami/dgi)
 * with fallback to the global key.
 *
 * @param string|null $type  Registry type ('rsst', 'rami', 'dgi') or null for global
 */
function getReportVisibilityMode(?string $type = null): string {
    if ($type !== null) {
        $key = 'app_report_visibility_' . $type;
        $value = getConfig($key, '');
        if ($value !== '') {
            return normalizeVisibilityValue($value);
        }
        // Fallback to global key if per-registry key is empty/not set
    }
    $value = getConfig('app_report_visibility', 'agent_choice');
    return normalizeVisibilityValue($value);
}

/**
 * Get the report visibility for the current user (for reading/filtering).
 *
 * @param string|null $type  Registry type ('rsst', 'rami', 'dgi') or null for global
 */
function getReportVisibility(?string $type = null): string {
    $role = currentUserRole();
    if (empty($role) || $role !== ROLE_AGENT) {
        return 'all';
    }
    return getReportVisibilityMode($type);
}

function reportVisibilityIsConfidential(?string $type = null): bool {
    return getReportVisibilityMode($type) === 'confidential';
}

function reportVisibilityIsAgentChoice(?string $type = null): bool {
    return getReportVisibilityMode($type) === 'agent_choice';
}

function reportVisibilityIsPublic(?string $type = null): bool {
    return getReportVisibilityMode($type) === 'public';
}

// ============================================================================
// Report Action Helpers
// ============================================================================

/**
 * Check if a user can edit a report (must be the declarant AND report must be editable).
 *
 * Replaces the duplicated inline logic:
 *   $canEdit = $isDeclarant && in_array($report['etat'], ['nouveau', 'en_cours']);
 *
 * @param array $report  Report data from DB
 * @param int   $userId  Current user's ID
 * @return bool
 */
function canEditReport(array $report, int $userId): bool {
    $isDeclarant = ((int) $report['declarant_id'] === $userId);
    return $isDeclarant && in_array($report['etat'], [ETAT_NOUVEAU, ETAT_EN_COURS]);
}

/**
 * Check if a user can respond to a report (must be superviseur AND report must be editable).
 *
 * Replaces the duplicated inline logic:
 *   $canRespond = in_array($userRole, ['superviseur']) && in_array($report['etat'], ['nouveau', 'en_cours']);
 *
 * @param array $report  Report data from DB
 * @param string $role   Current user's role
 * @return bool
 */
function canRespondToReport(array $report, string $role): bool {
    return in_array($role, [ROLE_SUPERVISEUR]) && in_array($report['etat'], [ETAT_NOUVEAU, ETAT_EN_COURS, ETAT_REOUVERT]);
}
