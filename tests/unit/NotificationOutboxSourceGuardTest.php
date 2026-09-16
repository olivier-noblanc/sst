<?php

/**
 * Notification outbox migration — gardes de source (pas de double chemin).
 *
 * Une fois les notifications métier migrées vers l'outbox, AUCUN envoi direct
 * (sendMail/sendSmtpTest) ne doit subsister dans les sources de notification :
 * le seul chemin de transport est EmailOutboxWorker (via sendMail), pas les
 * builders de messages.
 *
 * sendSmtpTest reste réservé au bouton « tester SMTP » (hors périmètre) et
 * error_notify.php (alerte admin) n'est pas migré — ces deux fichiers sont
 * donc explicitement HORS de ces gardes.
 */

use PHPUnit\Framework\TestCase;

class NotificationOutboxSourceGuardTest extends TestCase
{
    /** @return list<string> */
    private const MIGRATED_SOURCES = [
        'src/mail_notifications.php',
        'src/Services/NotificationService.php',
        'src/Event/event_listeners.php',
    ];

    private function source(string $rel): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $rel);
        $this->assertNotFalse($content, 'Fichier source introuvable : ' . $rel);
        return (string) $content;
    }

    public function testMigratedSourcesContainNoDirectSendMail(): void
    {
        foreach (self::MIGRATED_SOURCES as $rel) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bsendMail\s*\(/',
                $this->source($rel),
                $rel . ' ne doit plus appeler sendMail() directement : le transport est la responsabilité du worker outbox.'
            );
        }
    }

    public function testMigratedSourcesDoNotBypassOutboxWithSendSmtpTest(): void
    {
        foreach (self::MIGRATED_SOURCES as $rel) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bsendSmtpTest\s*\(/',
                $this->source($rel),
                $rel . ' ne doit pas utiliser sendSmtpTest (réservé au bouton de test SMTP).'
            );
        }
    }

    public function testMigratedSourcesEnqueueThroughOutboxRepository(): void
    {
        // Preuve positive : les builders écrivent bien dans l'outbox.
        $this->assertMatchesRegularExpression(
            '/EmailOutboxRepository/',
            $this->source('src/mail_notifications.php'),
            'mail_notifications.php doit mettre en file via EmailOutboxRepository'
        );
    }
}
