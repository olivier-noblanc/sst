<?php

/**
 * Event Listeners Registration — Application SST DREETS BFC
 *
 * Audit #1 — Wire les EventDispatcher listeners en production.
 *
 * Avant ce fix :
 * - EventDispatcher était instancié et 11 dispatch() étaient appelés depuis
 *   ReportService/UserService/AuthService
 * - Mais AUCUN listener n'était enregistré en production → abstraction morte
 * - Conséquence directe : notifyNewReport() n'était JAMAIS appelée après
 *   la création d'un signalement → les superviseurs ne recevaient jamais
 *   d'email à la création → obligation légale L4131-2 (DGI) non honorée
 *
 * Ce fichier centralise "quand X arrive, faire Y" en un seul point.
 * Les listeners sont enregistrés dans le container DI (bootstrap_services.php).
 */

use App\Container\Container;
use App\Event\EventDispatcher;
use App\Repository\ReportRepository;
use App\Services\NotificationService;

/**
 * Register all production event listeners on the dispatcher.
 *
 * Called from bootstrap_services.php after the container is built.
 */
function registerEventListeners(EventDispatcher $events, Container $c): void
{
    /** @var NotificationService $notifications */
    $notifications = $c->get(NotificationService::class);

    // ── report.created → notifyNewReport ────────────────────────────────────
    // Audit #1 — Critical bug : notifications de création jamais envoyées.
    // Pour les DGI, le CSE/CSA doit être informé (article L4131-2 Code du travail).
    $events->addListener('report.created', function (array $data) use ($notifications): void {
        /** @var array<string, mixed> $report */
        $report = $data['report'] ?? [];
        $reportUuid = (string) ($report['uuid'] ?? '');
        $type = (string) ($report['type'] ?? '');
        $siteId = (int) ($report['site_id'] ?? 0);

        if ($reportUuid === '' || $type === '') {
            return;
        }

        try {
            $notifications->notifyNewReport($reportUuid, $type, $siteId);
        } catch (Throwable $e) {
            // Notifications must not break the request — log and continue.
            // The report is already in DB, the user sees a success page.
            error_log('[SST-EVENT] notifyNewReport failed: ' . $e->getMessage());
        }
    });

    // ── report.responded → notifyReportResponse ─────────────────────────────
    $events->addListener('report.responded', function (array $data) use ($notifications): void {
        /** @var array<string, mixed> $report */
        $report = $data['report'] ?? [];
        $reportUuid = (string) ($report['uuid'] ?? '');
        $userId = (int) ($data['userId'] ?? 0);

        if ($reportUuid === '' || $userId === 0) {
            return;
        }

        try {
            $notifications->notifyReportResponse($reportUuid, $userId);
        } catch (Throwable $e) {
            error_log('[SST-EVENT] notifyReportResponse failed: ' . $e->getMessage());
        }
    });

    // ── report.reopened → notifyReportReopen ────────────────────────────────
    $events->addListener('report.reopened', function (array $data) use ($notifications): void {
        /** @var array<string, mixed> $report */
        $report = $data['report'] ?? [];
        $reportUuid = (string) ($report['uuid'] ?? '');
        $userId = (int) ($data['userId'] ?? 0); // not in current dispatch, kept for future

        if ($reportUuid === '') {
            return;
        }

        try {
            $notifications->notifyReportReopen($reportUuid, $userId);
        } catch (Throwable $e) {
            error_log('[SST-EVENT] notifyReportReopen failed: ' . $e->getMessage());
        }
    });

    // ── report.updated → no listener (silent) ───────────────────────────────
    // Updates are not notified by default — supervisors see them in the dashboard.

    // ── user.created → no email (user is provisioned, not invited) ──────────

    // ── user.role_changed → notifyRoleChange ────────────────────────────────
    // Audit #23 — should also invalidate the user's other sessions (handled in Batch 6).
    $events->addListener('user.role_changed', function (array $data) use ($notifications): void {
        /** @var array<string, mixed> $user */
        $user = $data['user'] ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $oldRole = (string) ($data['oldRole'] ?? '');
        $newRole = (string) ($data['newRole'] ?? '');

        if ($userId === 0) {
            return;
        }

        try {
            $notifications->notifyRoleChange($userId, $oldRole, $newRole);
        } catch (Throwable $e) {
            error_log('[SST-EVENT] notifyRoleChange failed: ' . $e->getMessage());
        }
    });

    // ── user.deactivated → no email by default ──────────────────────────────
    // Audit #9 + #22 — session invalidation handled in Batch 6 via SessionInvalidator.
}
