<?php

/**
 * Event Listeners Registration — Application SST DREETS BFC
 *
 * Audit #1 — Wire les EventDispatcher listeners en production.
 *
 * Tous les listeners utilisent maintenant des DTOs typés (ReportEventData,
 * UserEventData) au lieu d'array<string, mixed>. Cela élimine les mutants
 * Infection sur les casts (string), (int) et les ?? coalesce.
 */

use App\Container\Container;
use App\DTO\ReportEventData;
use App\DTO\UserEventData;
use App\Event\EventDispatcher;
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
    $events->addListener('report.created', function (ReportEventData|UserEventData $data) use ($notifications): void {
        if (!$data instanceof ReportEventData) {
            return;
        }
        $reportUuid = $data->uuid();
        $type = $data->typeString();
        $siteId = $data->siteIdInt();

        if ($reportUuid === '' || $type === '') {
            return;
        }

        try {
            $notifications->notifyNewReport($reportUuid, $type, $siteId);
        } catch (Throwable $e) {
            // @silent-ok: notifications must not break the request — log and continue.
            // The report is already in DB, the user sees a success page.
            error_log('[SST-EVENT] notifyNewReport failed: ' . $e->getMessage());
        }
    });

    // ── report.responded → notifyReportResponse ─────────────────────────────
    $events->addListener('report.responded', function (ReportEventData|UserEventData $data) use ($notifications): void {
        if (!$data instanceof ReportEventData) {
            return;
        }
        $reportUuid = $data->uuid();
        $userId = $data->userIdInt();

        if ($reportUuid === '' || $userId === 0) {
            return;
        }

        try {
            $notifications->notifyReportResponse($reportUuid, $userId);
        } catch (Throwable $e) {
            // @silent-ok: best-effort notification after main action succeeded.
            error_log('[SST-EVENT] notifyReportResponse failed: ' . $e->getMessage());
        }
    });

    // ── report.reopened → notifyReportReopen ────────────────────────────────
    $events->addListener('report.reopened', function (ReportEventData|UserEventData $data) use ($notifications): void {
        if (!$data instanceof ReportEventData) {
            return;
        }
        $reportUuid = $data->uuid();
        $userId = $data->userIdInt();

        if ($reportUuid === '') {
            return;
        }

        try {
            $notifications->notifyReportReopen($reportUuid, $userId);
        } catch (Throwable $e) {
            // @silent-ok: best-effort notification after main action succeeded.
            error_log('[SST-EVENT] notifyReportReopen failed: ' . $e->getMessage());
        }
    });

    // ── report.abandoned → notifyReportAbandon ──────────────────────────────
    $events->addListener('report.abandoned', function (ReportEventData|UserEventData $data) use ($notifications): void {
        if (!$data instanceof ReportEventData) {
            return;
        }
        $reportUuid = $data->uuid();
        $userId = $data->userIdInt();

        if ($reportUuid === '') {
            return;
        }

        try {
            $notifications->notifyReportAbandon($reportUuid, $userId);
        } catch (Throwable $e) {
            // @silent-ok: best-effort notification after main action succeeded.
            error_log('[SST-EVENT] notifyReportAbandon failed: ' . $e->getMessage());
        }
    });

    // ── user.role_changed → notifyRoleChange ────────────────────────────────
    $events->addListener('user.role_changed', function (ReportEventData|UserEventData $data) use ($notifications): void {
        if (!$data instanceof UserEventData) {
            return;
        }
        $userId = $data->userIdInt();
        $oldRole = $data->oldRoleString();
        $newRole = $data->newRoleString();

        if ($userId === 0) {
            return;
        }

        try {
            $notifications->notifyRoleChange($userId, $oldRole, $newRole);
        } catch (Throwable $e) {
            // @silent-ok: best-effort notification after main action succeeded.
            error_log('[SST-EVENT] notifyRoleChange failed: ' . $e->getMessage());
        }
    });
}
