<?php
/**
 * Auth Unit Tests — extractUsername, parseSuperviseurUsernames
 *
 * Tests authentication functions from src/auth.php:
 * - extractUsername()
 * - parseSuperviseurUsernames()
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/auth.php';

class AuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM notification_settings');
        $this->pdo->exec('DELETE FROM sites');
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();
    }

    // ─── extractUsername ───────────────────────────────────────────────────

    public function testExtractUsernameWithDomainBackslashFormat(): void
    {
        $this->assertEquals('jean.martin', extractUsername('DREETS-BFC\jean.martin'));
    }

    public function testExtractUsernameWithDomainBackslashUppercase(): void
    {
        $this->assertEquals('admin.super', extractUsername('DREETS-BFC\ADMIN.SUPER'));
    }

    public function testExtractUsernameWithAtFormat(): void
    {
        $this->assertEquals('jean.martin', extractUsername('jean.martin@dreets-bfc.gouv.fr'));
    }

    public function testExtractUsernameWithAtFormatUppercase(): void
    {
        $this->assertEquals('jean.martin', extractUsername('JEAN.MARTIN@DREETS-BFC.GOUV.FR'));
    }

    public function testExtractUsernamePlainUsername(): void
    {
        $this->assertEquals('jean.martin', extractUsername('jean.martin'));
    }

    public function testExtractUsernamePlainUppercase(): void
    {
        $this->assertEquals('admin.super', extractUsername('ADMIN.SUPER'));
    }

    public function testExtractUsernameEmptyString(): void
    {
        $this->assertEquals('', extractUsername(''));
    }

    public function testExtractUsernameWhitespaceOnly(): void
    {
        $this->assertEquals('', extractUsername('   '));
    }

    public function testExtractUsernameWithLeadingTrailingWhitespace(): void
    {
        $this->assertEquals('jean.martin', extractUsername('  jean.martin  '));
    }

    public function testExtractUsernameWithBackslashAndWhitespace(): void
    {
        $this->assertEquals('jean.martin', extractUsername('DOMAIN\  jean.martin  '));
    }

    // ─── parseSuperviseurUsernames ──────────────────────────────────────────

    public function testParseSuperviseurUsernamesCommaSeparated(): void
    {
        $result = parseSuperviseurUsernames('jean.martin, sophie.dupont, admin.super');
        $this->assertEquals(['jean.martin', 'sophie.dupont', 'admin.super'], $result);
    }

    public function testParseSuperviseurUsernamesSingleEntry(): void
    {
        $result = parseSuperviseurUsernames('jean.martin');
        $this->assertEquals(['jean.martin'], $result);
    }

    public function testParseSuperviseurUsernamesEmpty(): void
    {
        $result = parseSuperviseurUsernames('');
        $this->assertEquals([], $result);
    }

    public function testParseSuperviseurUsernamesWhitespacePadding(): void
    {
        $result = parseSuperviseurUsernames('  jean.martin  ,  sophie.dupont  ');
        $this->assertEquals(['jean.martin', 'sophie.dupont'], $result);
    }

    public function testParseSuperviseurUsernamesLowercased(): void
    {
        $result = parseSuperviseurUsernames('JEAN.MARTIN, SOPHIE.DUPONT');
        $this->assertEquals(['jean.martin', 'sophie.dupont'], $result);
    }

    public function testParseSuperviseurUsernamesTrailingComma(): void
    {
        $result = parseSuperviseurUsernames('jean.martin,');
        $this->assertEquals(['jean.martin'], $result);
    }
}
