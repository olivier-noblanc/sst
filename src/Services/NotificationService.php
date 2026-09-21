<?php

/**
 * NotificationService — Couche service pour les notifications e-mail.
 *
 * Encapsule les fonctions globales de notification (mail_notifications.php)
 * et les notifications inline des handlers (abandon, réouverture).
 */

namespace App\Services;

use App\Enum\OutboxEvent;
use App\Repository\EmailOutboxRepository;
use App\Repository\ReportAgentRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use PDO;

// Audit #79 — NotificationService délègue chaque méthode à une fonction
// globale de mail_notifications.php (notifyNewReport, notifyReportResponse,
// notifyRoleChange), qui mettent désormais les messages EN FILE dans l'outbox
// (le transport est porté par EmailOutboxWorker). Rien ici ne garantissait que
// mail.php soit chargé avant qu'un listener d'event (event_listeners.php,
// enregistré dans bootstrap_services.php dès le démarrage de la requête)
// n'appelle une de ces méthodes. Chaque handler faisait son propre
// `require_once mail.php`, mais toujours APRÈS avoir appelé le Service qui
// déclenche l'event (report_create_handler.php, report_respond_handler.php,
// report_reopen_handler.php, user_edit_handler.php) — donc toujours trop tard
// pour le listener. Résultat en production : notifyNewReport()
// (notification légale L4131-2 pour les DGI), notifyReportResponse(),
// notifyReportReopen() et notifyRoleChange() échouaient silencieusement à
// CHAQUE appel ("Call to undefined function App\Services\notifyNewReport()"
// — PHP essaie d'abord App\Services\notifyNewReport avant le fallback
// global, et sans mail.php chargé, le fallback ne trouve rien non plus),
// avalées par le try/catch de event_listeners.php et juste loggées.
// Fix : charger la dépendance ici, au niveau du Service lui-même, plutôt
// que de compter sur chaque appelant pour le faire au bon moment.
require_once __DIR__ . '/../mail.php';

class NotificationService
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Notify relevant people about a new report.
     */
    public function notifyNewReport(string $reportUuid, string $type, int $siteId): void
    {
        notifyNewReport($this->pdo, $reportUuid, $type, $siteId);
        $this->flushOutbox();
    }

    /**
     * Notify the declarant and linked agents that their report has received a response.
     *
     * $responseId (report_responses.id) est l'identité d'OCCURRENCE : deux
     * réponses successives du même répondant au même signalement ont des id
     * distincts, donc deux dedup_key distincts — aucune notification perdue.
     */
    public function notifyReportResponse(string $reportUuid, int $userId, int $responseId): void
    {
        notifyReportResponse($this->pdo, $reportUuid, $userId, $responseId);
        $this->flushOutbox();
    }

    /**
     * Notify supervisors that a report has been abandoned.
     *
     * $stateHistoryId (report_state_history.id) est l'identité d'OCCURRENCE :
     * deux abandons successifs (cycles reopen→abandon) ne partagent pas la clé.
     */
    public function notifyReportAbandon(string $reportUuid, int $userId, int $stateHistoryId): void
    {
        $report = ReportRepository::instance()->findById($reportUuid);
        if ($report === null) {
            return;
        }

        // AGENTS.md §"Mode sans site" : ReportData::siteId est ?int nullable,
        // ne jamais coercer null → 0. getNotificationRecipients accepte ?int
        // et skipe la requête per-site quand null (retourne les globaux).
        $recipients = getNotificationRecipients($this->pdo, $report->siteId);
        if (empty($recipients)) {
            return;
        }

        require_once __DIR__ . '/../mail.php';

        /** @var string */
        $type = $report->type;
        $registryLabel = getRegistryShortLabel($type);
        $subject = "Signalement abandonné $registryLabel — {$report->reference}";
        $body = '<html><body>';
        $body .= '<h2>Signalement abandonné</h2>';
        $body .= '<p><strong>Référence :</strong> ' . e($report->reference) . '</p>';
        $body .= "<p><strong>Registre :</strong> $registryLabel</p>";
        $body .= '<p><strong>Objet :</strong> ' . e($report->objet) . '</p>';
        $body .= '<p><strong>Déclarant :</strong> ' . e($report->declarantPrenom . ' ' . $report->declarantNom) . '</p>';
        $body .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
        $body .= '</body></html>';

        foreach ($recipients as $email) {
            enqueueNotification($this->pdo, OutboxEvent::ReportAbandoned, $reportUuid . ':' . $stateHistoryId, $email, $subject, $body);
        }
        $this->flushOutbox();
    }

    /**
     * Notify the declarant and linked agents that their report has been reopened.
     *
     * Fiabilisation (council) — $motif préserve le contenu de l'ancien envoi
     * direct du handler (le motif figurait dans l'e-mail).
     */
    public function notifyReportReopen(string $reportUuid, int $userId, ?string $motif, int $stateHistoryId): void
    {
        $report = ReportRepository::instance()->findById($reportUuid);
        if ($report === null) {
            return;
        }

        require_once __DIR__ . '/../mail.php';

        /** @var string */
        $type = $report->type;
        $registryLabel = getRegistryShortLabel($type);

        $motifHtml = ($motif !== null && $motif !== '')
            ? '<p><strong>Motif :</strong> ' . e($motif) . '</p>'
            : '';

        // Notify declarant
        /** @var int */
        $declarantId = $report->declarantId;
        $declarant = UserRepository::instance()->findById($declarantId);
        if ($declarant !== null && !empty($declarant->email) && $declarantId !== $userId) {
            $subject = "Signalement réouvert $registryLabel — {$report->reference}";
            $body = '<html><body>';
            $body .= '<h2>Votre signalement a été réouvert</h2>';
            $body .= '<p><strong>Référence :</strong> ' . e($report->reference) . '</p>';
            $body .= $motifHtml;
            $body .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
            $body .= '</body></html>';
            enqueueNotification(
                $this->pdo,
                OutboxEvent::ReportReopened,
                $reportUuid . ':' . $stateHistoryId,
                $declarant->email,
                $subject,
                $body,
            );
        }

        // Also notify linked agents
        $linkedAgents = ReportAgentRepository::instance()->getLinkedAgents($reportUuid);
        foreach ($linkedAgents as $linkedAgent) {
            if (!empty($linkedAgent['email']) && $linkedAgent['email'] !== ($declarant->email ?? '')) {
                $linkedSubject = "Signalement réouvert $registryLabel — {$report->reference}";
                $linkedBody = '<html><body>';
                $linkedBody .= '<h2>Signalement réouvert</h2>';
                $linkedBody .= '<p>Bonjour ' . e($linkedAgent['prenom']) . ',</p>';
                $linkedBody .= '<p>Le signalement <strong>' . e($report->reference) . '</strong> auquel vous êtes rattaché(e) a été réouvert.</p>';
                $linkedBody .= $motifHtml;
                $linkedBody .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
                $linkedBody .= '</body></html>';
                enqueueNotification(
                    $this->pdo,
                    OutboxEvent::ReportReopened,
                    $reportUuid . ':' . $stateHistoryId,
                    $linkedAgent['email'],
                    $linkedSubject,
                    $linkedBody,
                );
            }
        }
        $this->flushOutbox();
    }

    /**
     * Notify a user that their role has been changed.
     *
     * Option A outbox — délègue l'enqueue durable et retourne la mise en file
     * (true) ou son impossibilité. Le transport appartient au worker outbox.
     *
     * @return bool True si le message a été mis en file, false sinon
     */
    public function notifyRoleChange(int $userId, string $oldRole, string $newRole, string $eventKey): bool
    {
        $enqueued = notifyRoleChange($this->pdo, $userId, $oldRole, $newRole, $eventKey);
        $this->flushOutbox();

        return $enqueued;
    }

    /**
     * Transmet manuellement un signalement aux membres CSA/CHSCT (superviseur).
     *
     * Décision métier (Oracle) : aucune transmission automatique à la création.
     * L'action est mise en file dans l'outbox, dédupliquée par signalement ×
     * destinataire. Retourne le nombre de messages réellement enqueue (0 si
     * doublons ou aucun membre CSA/CHSCT joignable).
     */
    public function notifyReportTransmitted(string $reportUuid): int
    {
        $enqueued = notifyReportTransmitted($this->pdo, $reportUuid);
        $this->flushOutbox();

        return $enqueued;
    }

    /**
     * Drain opportuniste post-enqueue.
     *
     * En SAPI web uniquement (jamais en CLI — tests et scripts s'appuient sur
     * le lazy cron `mail_drain`), et hors transaction : un run borné du worker
     * vide la file sans attendre la fenêtre de 5 min. Un échec transport reste
     * porté par la ligne outbox (retry/backoff) — jamais perdu.
     */
    public function flushOutbox(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if ($this->pdo->inTransaction()) {
            return;
        }

        new EmailOutboxWorker(new EmailOutboxRepository($this->pdo))->run();
    }

}
