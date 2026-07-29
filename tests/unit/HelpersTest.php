<?php
/**
 * Helpers Unit Tests — Application SST DREETS BFC
 *
 * Tests the helper functions (validatePostRequest, canAccessReport,
 * userSelectWithSite, etc.) that don't require a database.
 */

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    /**
     * Audit #85 — canAccessReport() attend un objet ReportData en natif
     * (typage strict), pas un array<string, mixed>. Les 8 tests de ce
     * fichier construisaient un array à la main avec seulement 4-5 clés
     * (site_id, declarant_id, is_confidential, type, consent_syndicat) —
     * ça plantait (TypeError) dès que ce fichier était lancé seul, jamais
     * remarqué jusqu'à ce qu'Infection le fasse (ordre aléatoire).
     */
    private function makeReport(int $siteId, int $declarantId, bool $isConfidential, string $type = 'rsst', bool $consentSyndicat = false): \App\DTO\ReportData
    {
        return new \App\DTO\ReportData(
            uuid: 'test-uuid', reference: 'RSST-25-001', type: $type,
            objet: 'Objet test', description: 'Description test',
            dateEvenement: '2025-01-01', heureEvenement: '', lieu: '',
            declarantId: $declarantId, declarantNom: 'Dupont', declarantPrenom: 'Jean',
            pourCompteDe: '', pourCompteNom: '', pourComptePrenom: '',
            natureAuteur: '', typeActe: '', siteId: $siteId, siteText: '',
            pole: '', serviceAffectation: '', telephoneMobile: '',
            isConfidential: $isConfidential ? 1 : 0, consentSyndicat: $consentSyndicat ? 1 : 0,
            etat: 'nouveau', repondantId: null, dateReponse: null, reponse: null,
            attachmentName: null, attachmentMime: null,
            createdAt: '2025-01-01 10:00:00', updatedAt: '2025-01-01 10:00:00',
            siteCode: 'UR21', siteNom: 'UR Test', repondantNom: null, repondantPrenom: null,
        );
    }

    // ─── canAccessReport() ────────────────────────────────────────────────────

    public function testSuperviseurCanAccessAnyReport(): void
    {
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst');
        $user   = ['id' => 1, 'site_id' => 2, 'role' => 'superviseur'];

        $this->assertTrue(canAccessReport($report, $user));
    }

    public function testChsctCanAccessReportWithConsent(): void
    {
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst', (bool) 1);
        $user   = ['id' => 1, 'site_id' => 2, 'role' => 'chsct'];

        $this->assertTrue(canAccessReport($report, $user));
    }

    public function testChsctCannotAccessReportWithoutConsent(): void
    {
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst', (bool) 0);
        $user   = ['id' => 1, 'site_id' => 2, 'role' => 'chsct'];

        $this->assertFalse(canAccessReport($report, $user));
    }

    public function testAgentCanAccessOtherSiteReport(): void
    {
        $report = $this->makeReport(2, 99, (bool) 0, 'rsst');
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];

        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentCanAccessOwnConfidentialReport(): void
    {
        $report = $this->makeReport(1, 1, (bool) 1, 'rsst');
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];

        $this->assertTrue(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCannotAccessOtherAgentConfidentialReport(): void
    {
        $report = $this->makeReport(1, 99, (bool) 1, 'rsst');
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];

        $this->assertFalse(canAccessReport($report, $user, 'confidential'));
    }

    public function testAgentCanAccessPublicReportOnSameSite(): void
    {
        $report = $this->makeReport(1, 99, (bool) 0, 'rsst');
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];

        $this->assertTrue(canAccessReport($report, $user, 'public'));
    }

    public function testAgentChoiceModeRespectsIsConfidential(): void
    {
        // Agent can see other agent's non-confidential report in agent_choice mode
        $report = $this->makeReport(1, 99, false, 'rsst');
        $user   = ['id' => 1, 'site_id' => 1, 'role' => 'agent'];
        $this->assertTrue(canAccessReport($report, $user, 'agent_choice'));

        // Agent cannot see other agent's confidential report in agent_choice mode
        // (ReportData is readonly — a second object, not a mutation)
        $confidentialReport = $this->makeReport(1, 99, true, 'rsst');
        $this->assertFalse(canAccessReport($confidentialReport, $user, 'agent_choice'));
    }

    // ─── formatDateFR() ───────────────────────────────────────────────────────

    public function testFormatDateFR(): void
    {
        $this->assertEquals('15/03/2025', formatDateFR('2025-03-15'));
    }

    public function testFormatDateFRReturnsDashForEmpty(): void
    {
        $this->assertEquals('—', formatDateFR(null));
        $this->assertEquals('—', formatDateFR(''));
    }

    public function testFormatDateFRReturnsOriginalForInvalid(): void
    {
        $this->assertEquals('not-a-date', formatDateFR('not-a-date'));
    }

    // ─── e() escaping ─────────────────────────────────────────────────────────

    public function testHtmlEscaping(): void
    {
        $this->assertEquals('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        $this->assertEquals('O&#039;Reilly', e("O'Reilly"));
        $this->assertEquals('', e(null));
    }

    // ─── generateReference() ──────────────────────────────────────────────────

    public function testGenerateReference(): void
    {
        $this->assertEquals('rsst-25-001', generateReference('rsst', '25', 1));
        $this->assertEquals('rami-26-042', generateReference('rami', '26', 42));
        $this->assertEquals('dgi-24-999', generateReference('dgi', '24', 999));
    }

    // ─── truncate() ───────────────────────────────────────────────────────────

    public function testTruncateShortString(): void
    {
        $this->assertEquals('Hello', truncate('Hello', 10));
    }

    public function testTruncateLongString(): void
    {
        $result = truncate('Un très long texte qui dépasse la limite', 20);
        $this->assertEquals(20 + mb_strlen('…', 'UTF-8'), mb_strlen($result, 'UTF-8'));
        $this->assertStringEndsWith('…', $result);
    }

    // ─── normalizeVisibilityValue() ───────────────────────────────────────────

    public function testNormalizeVisibilityValue(): void
    {
        $this->assertEquals('public', normalizeVisibilityValue('0'));
        $this->assertEquals('public', normalizeVisibilityValue('site'));
        $this->assertEquals('confidential', normalizeVisibilityValue('1'));
        $this->assertEquals('confidential', normalizeVisibilityValue('own'));
        $this->assertEquals('agent_choice', normalizeVisibilityValue('agent_choice'));
        $this->assertEquals('agent_choice', normalizeVisibilityValue('unknown_value'));
    }

    // ─── getRegistryColor() / getEtatBadgeClass() / etc. ─────────────────────

    public function testGetRegistryColor(): void
    {
        $this->assertEquals('var(--rsst-color)', getRegistryColor('rsst'));
        $this->assertEquals('var(--rami-color)', getRegistryColor('rami'));
        $this->assertEquals('var(--dgi-color)', getRegistryColor('dgi'));
    }

    public function testGetEtatBadgeClass(): void
    {
        $this->assertEquals('badge--nouveau', getEtatBadgeClass('nouveau'));
        $this->assertEquals('badge--en-cours', getEtatBadgeClass('en_cours'));
        $this->assertEquals('badge--traite', getEtatBadgeClass('traite'));
        $this->assertEquals('badge--abandonne', getEtatBadgeClass('abandonne'));
        $this->assertEquals('', getEtatBadgeClass('unknown'));
    }

    public function testGetRoleBadgeClass(): void
    {
        $this->assertEquals('badge--agent', getRoleBadgeClass('agent'));
        $this->assertEquals('badge--superviseur', getRoleBadgeClass('superviseur'));
        $this->assertEquals('badge--chsct', getRoleBadgeClass('chsct'));
    }
}
