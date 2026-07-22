<?php

/**
 * NotificationService — Couche service pour les notifications e-mail.
 *
 * Encapsule les fonctions globales de notification (mail_notifications.php)
 * et les notifications inline des handlers (abandon, réouverture).
 */

namespace App\Services;

use PDO;

class NotificationService
{
    private readonly PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDB();
    }

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
        $report = getReportByUuid($this->pdo, $reportUuid);
        if ($report === null) {
            return;
        }

        /** @var int */
        $siteId = $report['site_id'] ?? 0;
        $recipients = getNotificationRecipients($this->pdo, $siteId);
        if (empty($recipients)) {
            return;
        }

        require_once __DIR__ . '/../mail.php';

        /** @var string */
        $type = $report['type'] ?? '';
        $registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);
        $subject = "Signalement abandonné $registryLabel — {$report['reference']}";
        $body = '<html><body>';
        $body .= '<h2>Signalement abandonné</h2>';
        $body .= '<p><strong>Référence :</strong> ' . e($report['reference']) . '</p>';
        $body .= "<p><strong>Registre :</strong> $registryLabel</p>";
        $body .= '<p><strong>Objet :</strong> ' . e($report['objet']) . '</p>';
        $body .= '<p><strong>Déclarant :</strong> ' . e($report['declarant_prenom'] . ' ' . $report['declarant_nom']) . '</p>';
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
        $report = getReportByUuid($this->pdo, $reportUuid);
        if ($report === null) {
            return;
        }

        require_once __DIR__ . '/../mail.php';

        /** @var string */
        $type = $report['type'] ?? '';
        $registryLabel = REGISTRY_SHORT_LABELS[$type] ?? strtoupper($type);

        // Notify declarant
        /** @var int */
        $declarantId = $report['declarant_id'] ?? 0;
        $declarant = getUserById($this->pdo, $declarantId);
        if ($declarant !== null && !empty($declarant['email']) && $declarantId !== $userId) {
            $subject = "Signalement réouvert $registryLabel — {$report['reference']}";
            $body = '<html><body>';
            $body .= '<h2>Votre signalement a été réouvert</h2>';
            $body .= '<p><strong>Référence :</strong> ' . e($report['reference']) . '</p>';
            $body .= '<p><a href="' . absoluteUrl('report_view', ['uuid' => $reportUuid]) . '">Consulter le signalement</a></p>';
            $body .= '</body></html>';
            sendMail($declarant['email'], $subject, $body);
        }

        // Also notify linked agents
        $linkedAgents = getLinkedAgents($this->pdo, $reportUuid);
        foreach ($linkedAgents as $linkedAgent) {
            if (!empty($linkedAgent['email']) && $linkedAgent['email'] !== ($declarant['email'] ?? '')) {
                $linkedSubject = "Signalement réouvert $registryLabel — {$report['reference']}";
                $linkedBody = '<html><body>';
                $linkedBody .= '<h2>Signalement réouvert</h2>';
                $linkedBody .= '<p>Bonjour ' . e($linkedAgent['prenom'] ?? '') . ',</p>';
                $linkedBody .= '<p>Le signalement <strong>' . e($report['reference']) . '</strong> auquel vous êtes rattaché(e) a été réouvert.</p>';
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

    /**
     * Send delay notifications for overdue reports (lazy cron check_delays).
     */
    public function sendDelayNotifications(): void
    {
        if (!function_exists('lazyCronCheckDelays')) {
            require_once __DIR__ . '/../cron.php';
        }
        lazyCronCheckDelays($this->pdo);
    }
}
