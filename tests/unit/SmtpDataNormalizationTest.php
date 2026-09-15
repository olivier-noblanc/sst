<?php
/**
 * SMTP DATA normalization — pure helper tests.
 *
 * Décision Oracle SMTP — le payload DATA SMTP doit être normalisé (CRLF) puis
 * « dot-stuffé » (RFC 5321 §4.5.2) : une ligne commençant par '.' doit être
 * préfixée d'un '.' supplémentaire, sinon une ligne de corps valant '.' est
 * interprétée par le serveur comme la fin du message (troncature ou injection
 * de contenu). Le helper est PUR (aucune socket) pour être testable seul.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class SmtpDataNormalizationTest extends TestCase
{
    public function testLoneLfIsNormalizedToCrlf(): void
    {
        $this->assertSame("a\r\nb", normalizeSmtpData("a\nb"));
    }

    public function testLoneCrIsNormalizedToCrlf(): void
    {
        $this->assertSame("a\r\nb", normalizeSmtpData("a\rb"));
    }

    public function testCrlfIsPreservedWithoutDoubling(): void
    {
        $this->assertSame("a\r\nb\r\nc", normalizeSmtpData("a\r\nb\r\nc"));
    }

    public function testLineStartingWithDotIsDotStuffed(): void
    {
        $this->assertSame("..hidden\r\nnext", normalizeSmtpData(".hidden\nnext"));
    }

    public function testStandaloneDotLineIsDotStuffed(): void
    {
        $this->assertSame("a\r\n..\r\nb", normalizeSmtpData("a\n.\nb"));
    }

    public function testDotInsideLineIsNotStuffed(): void
    {
        $this->assertSame("a.b\r\nc", normalizeSmtpData("a.b\nc"));
    }

    public function testEmptyStaysEmpty(): void
    {
        $this->assertSame('', normalizeSmtpData(''));
    }

    public function testMixedLineEndingsAndDotStuffingCombined(): void
    {
        // RFC 5321 : dot-stuffing s'applique à TOUTE ligne commençant par '.',
        // y compris une ligne déjà préfixée ('..secret' → '...secret').
        $this->assertSame(
            "header\r\n...secret\r\n..end\r\nend",
            normalizeSmtpData("header\r..secret\n.end\rend")
        );
    }

    public function testMultipleConsecutiveDotsAreAllStuffed(): void
    {
        $this->assertSame("...x\r\n..", normalizeSmtpData("..x\n."));
    }
}