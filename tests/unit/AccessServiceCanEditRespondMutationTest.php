<?php
/**
 * Tests AccessService::canEditReport() / canRespondToReport() exhaustively
 * — kills Infection mutants on:
 *   - Identical / LogicalAnd on declarant_id === userId check
 *   - in_array() strict mode mutants
 *   - CastInt on (int) $report['declarant_id']
 *   - LogicalAndNegation on the && combination
 *
 * Strategy : exhaustive truth table for canEdit (declarant × etat) and
 * canRespond (role × etat). Each combo kills the corresponding mutant.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\AccessService;
use App\DTO\ReportData;
use App\Enum\ReportState;
use App\Enum\UserRole;

class AccessServiceCanEditRespondMutationTest extends TestCase
{
    private AccessService $service;

    protected function setUp(): void
    {
        $this->service = new AccessService();
    }

    private function makeReport(array $overrides = []): ReportData
    {
        $defaults = [
            'uuid' => 'test-uuid',
            'reference' => 'RSST-25-001',
            'type' => 'rsst',
            'objet' => '',
            'description' => '',
            'dateEvenement' => '',
            'heureEvenement' => '',
            'lieu' => '',
            'declarantId' => 0,
            'declarantNom' => '',
            'declarantPrenom' => '',
            'pourCompteDe' => '',
            'pourCompteNom' => '',
            'pourComptePrenom' => '',
            'natureAuteur' => '',
            'typeActe' => '',
            'siteId' => 0,
            'siteText' => '',
            'pole' => '',
            'serviceAffectation' => '',
            'telephoneMobile' => '',
            'isConfidential' => 0,
            'consentSyndicat' => 0,
            'etat' => 'nouveau',
            'repondantId' => null,
            'dateReponse' => null,
            'reponse' => null,
            'attachmentName' => null,
            'attachmentMime' => null,
            'createdAt' => '',
            'updatedAt' => '',
            'siteCode' => '',
            'siteNom' => '',
            'repondantNom' => null,
            'repondantPrenom' => null,
        ];
        return new ReportData(...array_merge($defaults, $overrides));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // canEditReport() — truth table
    // ═══════════════════════════════════════════════════════════════════════════════

    #[DataProvider('provideCanEditCases')]
    public function testCanEditReportTruthTable(bool $isDeclarant, string $etat, bool $expected): void
    {
        $report = $this->makeReport([
            'declarantId' => $isDeclarant ? 42 : 99,
            'etat' => $etat,
        ]);
        $this->assertSame($expected, $this->service->canEditReport($report, 42));
    }

    /** @return array<string, array{bool, string, bool}> */
    public static function provideCanEditCases(): array
    {
        $cases = [];
        $editableStates = [ReportState::Nouveau->value, ReportState::EnCours->value];
        $nonEditableStates = [ReportState::Traite->value, ReportState::Abandonne->value, ReportState::Reouvert->value];

        foreach ([true, false] as $isDeclarant) {
            foreach ($editableStates as $etat) {
                $key = 'declarant=' . ($isDeclarant ? 'yes' : 'no') . ',etat=' . $etat;
                $cases[$key] = [$isDeclarant, $etat, $isDeclarant];
            }
            foreach ($nonEditableStates as $etat) {
                $key = 'declarant=' . ($isDeclarant ? 'yes' : 'no') . ',etat=' . $etat;
                $cases[$key] = [$isDeclarant, $etat, false];
            }
        }
        return $cases;
    }

    /**
     * Kill CastInt mutant on (int) $report['declarant_id'] — string ids must work.
     */
    public function testCanEditReportHandlesStringDeclarantId(): void
    {
        $report = $this->makeReport(['declarantId' => '42', 'etat' => ReportState::Nouveau->value]);
        $this->assertTrue($this->service->canEditReport($report, 42), 'string "42" must equal int 42 after cast');
    }

    /**
     * Kill mutant that would coerce null declarant_id to current user.
     * With typed DTO, declarantId is always int — test with non-matching declarant instead.
     */
    public function testCanEditReportWithNonDeclarantIdNeverAllowsEdit(): void
    {
        $report = $this->makeReport(['declarantId' => 0, 'etat' => ReportState::Nouveau->value]);
        $this->assertFalse($this->service->canEditReport($report, 42));
    }

    /**
     * Kill LogicalAnd mutant — non-declarant CANNOT edit even in editable state.
     */
    public function testCanEditReportRequiresDeclarantAndEditableState(): void
    {
        // declarant only (not editable state)
        $this->assertFalse($this->service->canEditReport(
            $this->makeReport(['declarantId' => 42, 'etat' => ReportState::Traite->value]),
            42,
        ));
        // editable state only (not declarant)
        $this->assertFalse($this->service->canEditReport(
            $this->makeReport(['declarantId' => 99, 'etat' => ReportState::Nouveau->value]),
            42,
        ));
        // both — allowed
        $this->assertTrue($this->service->canEditReport(
            $this->makeReport(['declarantId' => 42, 'etat' => ReportState::Nouveau->value]),
            42,
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // canRespondToReport() — truth table
    // ═══════════════════════════════════════════════════════════════════════════════

    #[DataProvider('provideCanRespondCases')]
    public function testCanRespondToReportTruthTable(string $role, string $etat, bool $expected): void
    {
        $report = $this->makeReport(['etat' => $etat]);
        $this->assertSame($expected, $this->service->canRespondToReport($report, $role));
    }

    /** @return array<string, array{string, string, bool}> */
    public static function provideCanRespondCases(): array
    {
        $cases = [];
        $respondableStates = [ReportState::Nouveau->value, ReportState::EnCours->value, ReportState::Reouvert->value];
        $nonRespondableStates = [ReportState::Traite->value, ReportState::Abandonne->value];
        $roles = [UserRole::Agent->value, UserRole::Superviseur->value, UserRole::Chsct->value, 'unknown'];

        foreach ($roles as $role) {
            foreach ($respondableStates as $etat) {
                $key = 'role=' . $role . ',etat=' . $etat;
                $cases[$key] = [$role, $etat, $role === UserRole::Superviseur->value];
            }
            foreach ($nonRespondableStates as $etat) {
                $key = 'role=' . $role . ',etat=' . $etat;
                $cases[$key] = [$role, $etat, false];
            }
        }
        return $cases;
    }

    /**
     * Kill mutant where CHSCT could respond — only superviseur can.
     */
    public function testCanRespondToReportChsctCannotRespond(): void
    {
        // Even in respondable state, CHSCT cannot respond
        $this->assertFalse($this->service->canRespondToReport(
            $this->makeReport(['etat' => ReportState::Nouveau->value]),
            UserRole::Chsct->value,
        ));
        $this->assertFalse($this->service->canRespondToReport(
            $this->makeReport(['etat' => ReportState::Reouvert->value]),
            UserRole::Chsct->value,
        ));
    }

    /**
     * Kill mutant where agent could respond — only superviseur can.
     */
    public function testCanRespondToReportAgentCannotRespond(): void
    {
        $this->assertFalse($this->service->canRespondToReport(
            $this->makeReport(['etat' => ReportState::Nouveau->value]),
            UserRole::Agent->value,
        ));
    }

    /**
     * Kill mutant on Reouvert state — superviseur can respond to reouvert reports.
     */
    public function testCanRespondToReportSuperviseurCanRespondToReouvert(): void
    {
        $this->assertTrue($this->service->canRespondToReport(
            $this->makeReport(['etat' => ReportState::Reouvert->value]),
            UserRole::Superviseur->value,
        ));
    }

    /**
     * Kill mutant on Traite/Abandonne — superviseur cannot respond to final states.
     */
    public function testCanRespondToReportSuperviseurCannotRespondToFinalStates(): void
    {
        $this->assertFalse($this->service->canRespondToReport(
            $this->makeReport(['etat' => ReportState::Traite->value]),
            UserRole::Superviseur->value,
        ));
        $this->assertFalse($this->service->canRespondToReport(
            $this->makeReport(['etat' => ReportState::Abandonne->value]),
            UserRole::Superviseur->value,
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // normalizeVisibilityValue() — exhaustive mapping
    // ═══════════════════════════════════════════════════════════════════════════════

    #[DataProvider('provideNormalizeVisibilityCases')]
    public function testNormalizeVisibilityValueMapping(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->service->normalizeVisibilityValue($input));
    }

    /** @return array<string, array{string, string}> */
    public static function provideNormalizeVisibilityCases(): array
    {
        return [
            '0 → public' => ['0', 'public'],
            'site → public' => ['site', 'public'],
            '1 → confidential' => ['1', 'confidential'],
            'own → confidential' => ['own', 'confidential'],
            'confidential → confidential' => ['confidential', 'confidential'],
            'agent_choice → agent_choice' => ['agent_choice', 'agent_choice'],
            'public → public' => ['public', 'public'],
            'unknown → agent_choice (fallback)' => ['unknown_value', 'agent_choice'],
            'empty → agent_choice (fallback)' => ['', 'agent_choice'],
        ];
    }

    /**
     * Kill LogicalOr mutant on `$value === '0' || $value === 'site'`.
     */
    public function testNormalizeVisibilityValueDistinguishes0AndSite(): void
    {
        // Both should map to public, but the path matters for mutants
        $this->assertSame('public', $this->service->normalizeVisibilityValue('0'));
        $this->assertSame('public', $this->service->normalizeVisibilityValue('site'));
        // '1' and 'own' should NOT be public
        $this->assertNotSame('public', $this->service->normalizeVisibilityValue('1'));
        $this->assertNotSame('public', $this->service->normalizeVisibilityValue('own'));
    }
}
