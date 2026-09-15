<?php
/**
 * sendMail() contract — best-effort bool, no transport throw, seam injectable.
 *
 * Décision Oracle SMTP :
 * - sendMail() retourne TOUJOURS un bool (jamais d'exception transport) ;
 * - un seam mailer injectable permet de tester le verdict sans socket ;
 * - le repli PHP mail() peut être désactivé (sendSmtpTest) pour que les
 *   boutons « tester la configuration SMTP » reflètent le verdict SMTP réel.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class MailSendContractTest extends TestCase
{
    protected function setUp(): void
    {
        setMailerSeam(null);
    }

    protected function tearDown(): void
    {
        setMailerSeam(null);
    }

    public function testSeamFalseVerdictIsReturned(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => false);
        $this->assertFalse(sendMail('dest@dreets-bfc.gouv.fr', 'Sujet', 'Corps'));
    }

    public function testSeamTrueVerdictIsReturned(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => true);
        $this->assertTrue(sendMail('dest@dreets-bfc.gouv.fr', 'Sujet', 'Corps'));
    }

    public function testTransportExceptionNeverEscapesAndYieldsFalse(): void
    {
        setMailerSeam(static function (string $to, string $subject, string $body, string $from = ''): bool {
            throw new RuntimeException('SMTP transport boom');
        });

        $this->assertFalse(
            sendMail('dest@dreets-bfc.gouv.fr', 'Sujet', 'Corps'),
            'sendMail est best-effort : une exception transport ne doit pas remonter'
        );
    }

    public function testSentinelGuardShortCircuitsBeforeSeam(): void
    {
        $called = false;
        setMailerSeam(static function (string $to, string $subject, string $body, string $from = '') use (&$called): bool {
            $called = true;
            return false;
        });

        $this->assertTrue(
            sendMail(\App\Repository\AnonymizationPolicy::ANONYMIZED_EMAIL, 'Sujet', 'Corps'),
            'La sentinelle reste un succès sémantique (aucun envoi requis)'
        );
        $this->assertFalse($called, 'Le seam ne doit jamais être appelé pour la sentinelle');
    }

    public function testSmtpTestDoesNotFallBackWhenSmtpUnconfigured(): void
    {
        getConfigService()->set('smtp_host', '');
        setMailerSeam(null);

        $this->assertFalse(
            sendSmtpTest('dest@dreets-bfc.gouv.fr', 'Sujet', 'Corps'),
            'sendSmtpTest ne doit jamais se replier sur mail() : verdict SMTP strict'
        );
    }

    public function testBuildMailHeadersIsSharedAndWellFormed(): void
    {
        getConfigService()->set('smtp_from', 'noreply@dreets-bfc.gouv.fr');
        getConfigService()->set('app_nom_organisation', 'DREETS BFC');

        $headers = buildMailHeaders();

        $this->assertStringContainsString("From: DREETS BFC <noreply@dreets-bfc.gouv.fr>\r\n", $headers);
        $this->assertStringContainsString("Reply-To: noreply@dreets-bfc.gouv.fr\r\n", $headers);
        $this->assertStringContainsString("MIME-Version: 1.0\r\n", $headers);
        $this->assertStringContainsString("Content-Type: text/html; charset=UTF-8\r\n", $headers);
        $this->assertStringContainsString('X-Mailer: PHP/', $headers);
        $this->assertStringEndsNotWith("\r\n", $headers, 'Le bloc d\'en-têtes ne doit pas porter de CRLF terminal (le DATA l\'ajoute)');
    }

    public function testBuildMailHeadersStripsCrlfFromAppName(): void
    {
        getConfigService()->set('smtp_from', 'noreply@dreets-bfc.gouv.fr');
        getConfigService()->set('app_nom_organisation', "E\r\nvil");

        $headers = buildMailHeaders();

        $this->assertStringNotContainsString('E\r\nvil', $headers, 'CRLF injection dans le nom d\'application neutralisée');
        $this->assertStringContainsString('From: Evil <noreply@dreets-bfc.gouv.fr>', $headers);
    }
}