<?php
/**
 * Tests AuthService static helpers exhaustively — kills Infection mutants on:
 *   - extractUsername() (trim, str_contains, explode, strtolower, array_last)
 *   - parseSuperviseurUsernames() (strtolower, array_map, array_filter, trim)
 */

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;

class AuthServiceHelpersMutationTest extends TestCase
{
    // ═══ extractUsername() ═══

    public function testExtractUsernameReturnsEmptyForEmptyInput(): void
    {
        // Kill ReturnRemoval mutant on `return ''`
        $this->assertSame('', AuthService::extractUsername(''));
    }

    public function testExtractUsernameReturnsEmptyForWhitespaceOnly(): void
    {
        // Kill UnwrapTrim mutant — trim('   ') = '' → empty → return ''
        $this->assertSame('', AuthService::extractUsername('   '));
        $this->assertSame('', AuthService::extractUsername("\t\n"));
    }

    public function testExtractUsernameTrimsInput(): void
    {
        // Kill UnwrapTrim mutant on trim($authUser)
        $this->assertSame('jean.dupont', AuthService::extractUsername('  jean.dupont  '));
    }

    public function testExtractUsernameLowercasesResult(): void
    {
        // Kill strtolower mutant
        $this->assertSame('jean.dupont', AuthService::extractUsername('JEAN.DUPONT'));
        $this->assertSame('jean', AuthService::extractUsername('JEAN'));
    }

    public function testExtractUsernameHandlesDomainBackslashFormat(): void
    {
        // Kill str_contains('\\') mutant
        $this->assertSame('dupont', AuthService::extractUsername('DOMAIN\dupont'));
        $this->assertSame('user', AuthService::extractUsername('CORP\user'));
    }

    public function testExtractUsernameHandlesDomainBackslashWithSpaces(): void
    {
        // Kill trim inside explode path
        $this->assertSame('dupont', AuthService::extractUsername('DOMAIN\  dupont  '));
    }

    public function testExtractUsernameHandlesEmailFormat(): void
    {
        // Kill str_contains('@') mutant
        $this->assertSame('jean', AuthService::extractUsername('jean@gouv.fr'));
        $this->assertSame('user', AuthService::extractUsername('user@domain.com'));
    }

    public function testExtractUsernameHandlesEmailWithSpacesAroundUser(): void
    {
        // Kill UnwrapTrim on trim($parts[0])
        $this->assertSame('jean', AuthService::extractUsername('  jean  @gouv.fr'));
    }

    public function testExtractUsernameReturnsLastPartForMultiBackslash(): void
    {
        // Kill array_last mutant — DOMAIN\SUB\user must return 'user'
        $this->assertSame('user', AuthService::extractUsername('DOMAIN\SUB\user'));
    }

    public function testExtractUsernameReturnsPlainUsernameForNoSpecialChars(): void
    {
        $this->assertSame('jean.dupont', AuthService::extractUsername('jean.dupont'));
        $this->assertSame('user123', AuthService::extractUsername('user123'));
    }

    public function testExtractUsernameLowercasesDomainFormat(): void
    {
        // Kill strtolower on the backslash path
        $this->assertSame('dupont', AuthService::extractUsername('DOMAIN\DUPONT'));
    }

    public function testExtractUsernameLowercasesEmailFormat(): void
    {
        // Kill strtolower on the @ path
        $this->assertSame('jean', AuthService::extractUsername('JEAN@gouv.fr'));
    }

    public function testExtractUsernameBackslashTakesPrecedenceOverAt(): void
    {
        // DOMAIN\user@domain → should extract 'user@domain' (backslash wins)
        $result = AuthService::extractUsername('DOMAIN\user@domain');
        $this->assertSame('user@domain', $result);
    }

    // ═══ parseSuperviseurUsernames() ═══

    public function testParseSuperviseurUsernamesReturnsEmptyForEmptyString(): void
    {
        // Kill array_filter mutant — empty entries must be filtered out
        $this->assertSame([], AuthService::parseSuperviseurUsernames(''));
    }

    public function testParseSuperviseurUsernamesReturnsSingleUsername(): void
    {
        $this->assertSame(['jean.dupont'], AuthService::parseSuperviseurUsernames('jean.dupont'));
    }

    public function testParseSuperviseurUsernamesParsesMultipleUsernames(): void
    {
        // Kill explode mutant
        $result = AuthService::parseSuperviseurUsernames('jean.dupont,marie.martin,pierre.durand');
        $this->assertSame(['jean.dupont', 'marie.martin', 'pierre.durand'], $result);
    }

    public function testParseSuperviseurUsernamesTrimsEachEntry(): void
    {
        // Kill array_map trim mutant
        $result = AuthService::parseSuperviseurUsernames('  jean  ,  marie  ,  pierre  ');
        $this->assertSame(['jean', 'marie', 'pierre'], $result);
    }

    public function testParseSuperviseurUsernamesLowercasesAll(): void
    {
        // Kill strtolower mutant on $list
        $result = AuthService::parseSuperviseurUsernames('JEAN.DUPONT,MARIE.MARTIN');
        $this->assertSame(['jean.dupont', 'marie.martin'], $result);
    }

    public function testParseSuperviseurUsernamesFiltersEmptyEntries(): void
    {
        // Kill array_filter mutant on $u !== ''
        $result = AuthService::parseSuperviseurUsernames('jean,,marie, ,pierre');
        $this->assertSame(['jean', 'marie', 'pierre'], $result, 'empty entries must be filtered');
    }

    public function testParseSuperviseurUsernamesFiltersAllWhitespaceEntries(): void
    {
        // Kill mutant on trim + filter — '   ' trims to '' → filtered
        $result = AuthService::parseSuperviseurUsernames('jean,   ,marie');
        $this->assertSame(['jean', 'marie'], $result);
    }

    public function testParseSuperviseurUsernamesReturnsEmptyArrayForAllEmpty(): void
    {
        $this->assertSame([], AuthService::parseSuperviseurUsernames(',,, ,'));
        $this->assertSame([], AuthService::parseSuperviseurUsernames('   '));
    }

    public function testParseSuperviseurUsernamesReturnsListWithNumericKeys(): void
    {
        // Kill array_values mutant — keys must be sequential 0,1,2...
        $result = AuthService::parseSuperviseurUsernames('a,,b');
        $this->assertSame([0 => 'a', 1 => 'b'], $result);
        $this->assertSame([0, 1], array_keys($result));
    }

    public function testParseSuperviseurUsernamesHandlesTrailingComma(): void
    {
        $result = AuthService::parseSuperviseurUsernames('jean,marie,');
        $this->assertSame(['jean', 'marie'], $result, 'trailing comma must not add empty entry');
    }

    public function testParseSuperviseurUsernamesHandlesLeadingComma(): void
    {
        $result = AuthService::parseSuperviseurUsernames(',jean,marie');
        $this->assertSame(['jean', 'marie'], $result);
    }
}
