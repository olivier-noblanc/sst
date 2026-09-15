<?php
/**
 * Mail verdict consumption — gardes de source.
 *
 * Décision Oracle SMTP — les verdicts sendMail()/notifyRoleChange() doivent
 * être CONSOMMÉS partout où ils sont produits :
 * - smtp_test_handler et settings_handler testent le SMTP SANS repli mail()
 *   (sendSmtpTest) pour que le flash reflète le verdict SMTP réel ;
 * - user_edit_handler affecte $emailSent depuis le bool retourné par
 *   notifyRoleChange() (il ne le suppose plus vrai).
 *
 * Ces garanties sont structurelles (chemins uniques / verdicts consommés) et
 * suivent le pattern des gardes existantes (NotificationsSinglePathTest).
 */

use PHPUnit\Framework\TestCase;

class MailVerdictConsumptionGuardTest extends TestCase
{
    private function source(string $rel): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $rel);
        $this->assertNotFalse($content, 'Fichier source introuvable : ' . $rel);
        return (string) $content;
    }

    public function testSmtpTestHandlerUsesDirectSmtpTestWithoutFallback(): void
    {
        $src = $this->source('handlers/smtp_test_handler.php');

        $this->assertMatchesRegularExpression(
            '/sendSmtpTest\s*\(/',
            $src,
            'smtp_test_handler doit tester le SMTP directement (sendSmtpTest), sans repli mail()'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/sendMail\s*\(/',
            $src,
            'smtp_test_handler ne doit PAS appeler sendMail() : le repli mail() masquerait un échec SMTP'
        );
    }

    public function testSettingsHandlerUsesDirectSmtpTestWithoutFallback(): void
    {
        $src = $this->source('handlers/settings_handler.php');

        $this->assertMatchesRegularExpression(
            '/sendSmtpTest\s*\(/',
            $src,
            'settings_handler doit tester le SMTP directement (sendSmtpTest), sans repli mail()'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/sendMail\s*\(/',
            $src,
            'settings_handler ne doit PAS appeler sendMail() : le repli mail() masquerait un échec SMTP'
        );
    }

    public function testUserEditHandlerConsumesNotifyRoleChangeVerdict(): void
    {
        $src = $this->source('handlers/user_edit_handler.php');

        $this->assertMatchesRegularExpression(
            '/\$emailSent\s*=\s*[^;]*notifyRoleChange\s*\(/s',
            $src,
            '$emailSent doit être affecté depuis le bool retourné par notifyRoleChange()'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\$emailSent\s*=\s*true;/',
            $src,
            'user_edit_handler ne doit plus supposer l\'envoi réussi ($emailSent = true)'
        );
    }
}