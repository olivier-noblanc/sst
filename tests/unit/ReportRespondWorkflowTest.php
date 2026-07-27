<?php
/**
 * Report Respond Workflow Test — Application SST DREETS BFC
 *
 * Audit #4 — workflow réouvrir→répondre cassé.
 *
 * Avant le fix :
 *   - canRespondToReport() accepte l'état 'reouvert' (AccessService.php:200)
 *   - mais requireReportEditable() (utilisée par report_respond.php) le rejette
 *   - → un superviseur pouvait réouvrir un signalement mais ne pouvait plus
 *     y répondre (redirigé avec flash "Ce signalement ne peut plus être répondu")
 *
 * Ce test vérifie que requireReportRespondable() (nouvelle fonction extraite
 * de requireReportEditable) accepte bien l'état 'reouvert'.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ReportRespondWorkflowTest extends TestCase
{
    private static bool $bootstrapped = false;

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        require_once __DIR__ . '/../../src/config.php';
        require_once __DIR__ . '/../../src/helpers.php';
        require_once __DIR__ . '/../../src/Enum/ReportState.php';
        require_once __DIR__ . '/../../src/DTO/ReportData.php';
        require_once __DIR__ . '/../../src/Services/SessionService.php';
        require_once __DIR__ . '/../../src/Services/HttpService.php';
        require_once __DIR__ . '/../../src/validation.php';
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php?page=report_respond&uuid=test';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = '';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
    }

    private function buildReport(string $etat): \App\DTO\ReportData
    {
        return new \App\DTO\ReportData(
            uuid: 'test-uuid',
            reference: 'RSST-25-001',
            type: 'rsst',
            objet: 'Objet test',
            description: 'Description test',
            dateEvenement: '2025-01-01',
            heureEvenement: '',
            lieu: '',
            declarantId: 1,
            declarantNom: 'Dupont',
            declarantPrenom: 'Jean',
            pourCompteDe: '',
            pourCompteNom: '',
            pourComptePrenom: '',
            natureAuteur: '',
            typeActe: '',
            siteId: 1,
            siteText: '',
            pole: '',
            serviceAffectation: '',
            telephoneMobile: '',
            isConfidential: 0,
            consentSyndicat: 0,
            etat: $etat,
            repondantId: null,
            dateReponse: null,
            reponse: null,
            attachmentName: null,
            attachmentMime: null,
            createdAt: '2025-01-01 10:00:00',
            updatedAt: '2025-01-01 10:00:00',
            siteCode: 'UR21',
            siteNom: 'UR Test',
            repondantNom: null,
            repondantPrenom: null,
        );
    }

    public function testRequireReportRespondableAcceptsReouvert(): void
    {
        // Audit #4 — Le bug : requireReportEditable rejetait 'reouvert'
        // Le fix : requireReportRespondable accepte 'reouvert'
        $report = $this->buildReport(\App\Enum\ReportState::Reouvert->value);

        // Si la fonction ne redirige pas, le test passe
        // (redirect appelle exit(), qui serait attrapé par ob_end_clean)
        ob_start();
        try {
            requireReportRespondable($report, 'test-uuid', 'répondu');
            $output = ob_get_clean();
            $this->assertEmpty($output, 'requireReportRespondable should not redirect for reouvert');
            $flash = \App\Services\SessionService::getInstance()->getFlash();
            $this->assertNull($flash, 'No error flash should be set for reouvert');
        } catch (\Throwable $e) {
            ob_end_clean();
            // Si redirect appelle exit(), PHPUnit transforme en Exception
            $this->fail('requireReportRespondable threw for reouvert state: ' . $e->getMessage());
        }
    }

    public function testRequireReportRespondableAcceptsNouveauAndEnCours(): void
    {
        foreach ([\App\Enum\ReportState::Nouveau->value, \App\Enum\ReportState::EnCours->value] as $etat) {
            $report = $this->buildReport($etat);
            ob_start();
            try {
                requireReportRespondable($report, 'test-uuid', 'répondu');
                ob_end_clean();
                $this->assertTrue(true, "State $etat is respondable");
            } catch (\Throwable $e) {
                ob_end_clean();
                $this->fail("requireReportRespondable threw for state $etat: " . $e->getMessage());
            }
        }
    }

    public function testRequireReportRespondableRejectsTraite(): void
    {
        $report = $this->buildReport(\App\Enum\ReportState::Traite->value);
        ob_start();
        try {
            requireReportRespondable($report, 'test-uuid', 'répondu');
            $output = ob_get_clean();
            // Should redirect (output the redirect headers/location)
            $flash = \App\Services\SessionService::getInstance()->getFlash();
            $this->assertNotNull($flash, 'A flash error should be set for traite');
            $this->assertSame('error', $flash['type']);
        } catch (\Throwable $e) {
            ob_end_clean();
            // redirect exit was caught — also OK
            $flash = \App\Services\SessionService::getInstance()->getFlash();
            $this->assertNotNull($flash, 'A flash error should be set for traite (via exit)');
            $this->assertSame('error', $flash['type']);
        }
    }

    public function testRequireReportRespondableRejectsAbandonne(): void
    {
        $report = $this->buildReport(\App\Enum\ReportState::Abandonne->value);
        ob_start();
        try {
            requireReportRespondable($report, 'test-uuid', 'répondu');
            ob_end_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
        }
        $flash = \App\Services\SessionService::getInstance()->getFlash();
        $this->assertNotNull($flash, 'A flash error should be set for abandonne');
        $this->assertSame('error', $flash['type']);
    }

    public function testRequireReportEditableStillRejectsReouvert(): void
    {
        // requireReportEditable doit rester stricte — edit/abandon sur 'reouvert' interdit
        $report = $this->buildReport(\App\Enum\ReportState::Reouvert->value);
        ob_start();
        try {
            requireReportEditable($report, 'test-uuid', 'modifié');
            ob_end_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
        }
        $flash = \App\Services\SessionService::getInstance()->getFlash();
        $this->assertNotNull($flash, 'requireReportEditable should reject reouvert');
        $this->assertSame('error', $flash['type']);
    }
}
