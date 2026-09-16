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
 * Un échec de mise en file (enqueue outbox) doit-il être repropagé au boundary ?
 *
 * Dans une transaction métier ouverte, OUI : le listener participe à l'action
 * (l'enqueue est atomique avec elle), donc l'échec doit déclencher le rollback.
 * Hors transaction, NON : l'action est déjà committée, on reste best-effort
 * (un mail en échec ne doit pas casser une requête dont l'action a abouti).
 */
function listenerMustPropagate(?PDO $pdo): bool
{
    return $pdo !== null && $pdo->inTransaction();
}

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
            if (listenerMustPropagate($data->pdo)) {
                // Atomicité : dans la transaction métier, un échec d'enqueue doit
                // déclencher le rollback (pas d'action sans sa notification).
                throw $e;
            }
            // @silent-ok: hors transaction, notifications must not break the request.
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
            $notifications->notifyReportResponse($reportUuid, $userId, $data->actionId ?? 0);
        } catch (Throwable $e) {
            if (listenerMustPropagate($data->pdo)) {
                throw $e;
            }
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
            // Fiabilisation (council) — le motif de réouverture transite par
            // ReportEventData::motif pour préserver le contenu de l'e-mail
            // (l'ancien envoi direct du handler l'incluait). actionId (id de la
            // transition d'état) rend le dedup_key unique par occurrence.
            $notifications->notifyReportReopen($reportUuid, $userId, $data->motif, $data->actionId ?? 0);
        } catch (Throwable $e) {
            if (listenerMustPropagate($data->pdo)) {
                throw $e;
            }
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
            $notifications->notifyReportAbandon($reportUuid, $userId, $data->actionId ?? 0);
        } catch (Throwable $e) {
            if (listenerMustPropagate($data->pdo)) {
                throw $e;
            }
            // @silent-ok: best-effort notification after main action succeeded.
            error_log('[SST-EVENT] notifyReportAbandon failed: ' . $e->getMessage());
        }
    });

    // Fiabilisation (council) — PAS de listener 'user.role_changed' : le
    // changement de rôle est notifié par user_edit_handler.php SEUL, qui
    // respecte la checkbox notify_role_change du formulaire et trace
    // email_sent/email_error dans l'audit (Bug #30). Un listener ici envoyait
    // un e-mail inconditionnel (checkbox ignorée) EN PLUS de celui du handler.
}
