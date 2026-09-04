<?php
/**
 * Notifications Single Path Test — Application SST DREETS BFC
 *
 * Fiabilisation (council) — garantit UNE SEULE notification par événement :
 * chaque émission d'e-mail métier a UN seul chemin (soit l'event listener,
 * soit le handler — jamais les deux).
 *
 * Avant ce fix, 4 doubles chemins envoyaient des e-mails en double (ou
 * ignoraient la checkbox notify_role_change) :
 * - report_respond_handler.php appelait notifyReportResponse() en direct
 *   ALORS que ReportService::respond() dispatche déjà 'report.responded'
 * - report_abandon_handler.php envoyait les e-mails en direct ALORS que
 *   ReportService::abandon() dispatche déjà 'report.abandoned'
 * - report_reopen_handler.php envoyait les e-mails en direct (déclarant +
 *   rattachés) ALORS que ReportService::reopen() dispatche déjà
 *   'report.reopened'
 * - event_listeners.php appelait notifyRoleChange() sur 'user.role_changed'
 *   sans respecter la checkbox notify_role_change du formulaire d'édition,
 *   en plus de l'envoi direct (et audité) du handler user_edit_handler.php
 *
 * Le changement de rôle est notifié UNIQUEMENT par le handler (qui respecte
 * la checkbox et trace email_sent/email_error dans l'audit — Bug #30).
 */

use PHPUnit\Framework\TestCase;

class NotificationsSinglePathTest extends TestCase
{
    private function source(string $rel): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $rel);
        $this->assertNotFalse($content, 'Fichier source introuvable : ' . $rel);
        return (string) $content;
    }

    public function testRespondHandlerDoesNotSendNotificationDirectly(): void
    {
        $src = $this->source('handlers/report_respond_handler.php');
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*notifyReportResponse\s*\(/m',
            $src,
            'report_respond_handler ne doit PAS appeler notifyReportResponse en direct : '
            . 'ReportService::respond() dispatche déjà report.responded → double e-mail au déclarant.'
        );
    }

    public function testAbandonHandlerDoesNotSendMailDirectly(): void
    {
        $src = $this->source('handlers/report_abandon_handler.php');
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*sendMail\s*\(/m',
            $src,
            'report_abandon_handler ne doit PAS envoyer d\'e-mail en direct : '
            . 'ReportService::abandon() dispatche déjà report.abandoned (NotificationService::notifyReportAbandon).'
        );
    }

    public function testReopenHandlerDoesNotSendMailDirectly(): void
    {
        $src = $this->source('handlers/report_reopen_handler.php');
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*sendMail\s*\(/m',
            $src,
            'report_reopen_handler ne doit PAS envoyer d\'e-mail en direct : '
            . 'ReportService::reopen() dispatche déjà report.reopened (NotificationService::notifyReportReopen).'
        );
    }

    public function testRoleChangeNotificationHasSingleHandlerPath(): void
    {
        $listeners = $this->source('src/Event/event_listeners.php');
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*notifyRoleChange\s*\(/m',
            $listeners,
            'Le listener user.role_changed ne doit PAS notifier : la notification de changement '
            . 'de rôle est gérée par user_edit_handler.php seul, qui respecte la checkbox '
            . 'notify_role_change et trace email_sent/email_error dans l\'audit (Bug #30).'
        );

        $handler = $this->source('handlers/user_edit_handler.php');
        $this->assertMatchesRegularExpression(
            '/notifyRoleChange\s*\(/',
            $handler,
            'user_edit_handler.php doit rester le chemin unique de notification du changement de rôle (via NotificationService).'
        );
    }
}
