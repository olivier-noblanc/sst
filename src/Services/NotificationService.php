<?php

/**
 * NotificationService — Couche service pour les notifications e-mail.
 *
 * Encapsule les fonctions globales de notification (mail_notifications.php)
 * et les notifications inline des handlers (abandon, réouverture).
 */

namespace App\Services;

use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use PDO;

// Audit #79 — NotificationService délègue chaque méthode à une fonction
// globale de mail_notifications.php (notifyNewReport, notifyReportResponse,
// etc.), qui elles-mêmes appellent sendMail()/sendViaSMTP() définies dans
// mail.php. Rien ici ne garantissait que mail.php soit chargé avant qu'un
// listener d'event (event_listeners.php, enregistré dans
// bootstrap_services.php dès le démarrage de la requête) n'appelle une de
// ces méthodes. Chaque handler faisait son propre `require_once mail.php`,
// mais toujours APRÈS avoir appelé le Service qui déclenche l'event
// (report_create_handler.php, report_respond_handler.php,
// report_reopen_handler.php, user_edit_handler.php) — donc toujours trop
// tard pour le listener. Résultat en production : notifyNewReport()
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
    }

    /**
     * Notify the declarant and linked agents that their report has received a response.
     */
    public function notifyReportResponse(string $reportUuid, int $userId): void
    {
        notifyReportResponse($this->pdo, $reportUuid, $userId);
    }

    /**
     * Notify supervisors that a report has been abandoned.
     */
    public function notifyReportAbandon(string $reportUuid, int $userId): void
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
            sendMail($email, $subject, $body);
        }
    }

    /**
     * Notify the declarant and linked agents that their report has been reopened.
     */
    public function notifyReportReopen(string $reportUuid, int $userId): void
    {
        $report = ReportRepository::instance()->findById($reportUuid);
        if ($report === null) {
            return;
        }

        require_once __DIR__ . '/../mail.php';

        /** @var string */
        $type = $report->type;
        $registryLabel = getRegistryShortLabel($type);

        // Notify declarant
        /** @var int */
        $declarantId = $report->declarantId;
        $declarant = UserRepository::instance()->findById($declarantId);
        if ($declarant !== null && !empty($declarant['email']) && $declarantId !== $userId) {
            $subject = "Signalement réouvert $registryLabel — {$report->reference}";
            $body = '<html><body>';
            $body .= '<h2>Votre signalement a été réouvert</h2>';
            $body .= '<p><strong>Référence :</strong> ' . e($report->reference) . '</p>';
            $body .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
            $body .= '</body></html>';
            sendMail($declarant['email'], $subject, $body);
        }

        // Also notify linked agents
        $linkedAgents = ReportRepository::instance()->getLinkedAgents($reportUuid);
        foreach ($linkedAgents as $linkedAgent) {
            if (!empty($linkedAgent['email']) && $linkedAgent['email'] !== ($declarant['email'] ?? '')) {
                $linkedSubject = "Signalement réouvert $registryLabel — {$report->reference}";
                $linkedBody = '<html><body>';
                $linkedBody .= '<h2>Signalement réouvert</h2>';
                $linkedBody .= '<p>Bonjour ' . e($linkedAgent['prenom']) . ',</p>';
                $linkedBody .= '<p>Le signalement <strong>' . e($report->reference) . '</strong> auquel vous êtes rattaché(e) a été réouvert.</p>';
                $linkedBody .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
                $linkedBody .= '</body></html>';
                sendMail($linkedAgent['email'], $linkedSubject, $linkedBody);
            }
        }
    }

    /**
     * Notify a user that their role has been changed.
     */
    public function notifyRoleChange(int $userId, string $oldRole, string $newRole): void
    {
        notifyRoleChange($this->pdo, $userId, $oldRole, $newRole);
    }

}
