<?php
/**
 * TransmissionLabelTest — libellé de transmission du signalement.
 *
 * Exigence : aucun texte UI ne doit afficher « CHSCT » en dur. Le libellé
 * de la ligne/colonne « Transmission » suit le nom de rôle configurable
 * (app_role_label_chsct) et reste grammaticalement invariable : pas de
 * « s » de pluriel concaténé au libellé, pas de préfixe CSE/ en dur.
 */

use App\Enum\UserRole;
use App\Services\ConfigService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class TransmissionLabelTest extends TestCase
{
    private PDO $pdo;
    private ConfigService $service;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();
        $this->service = new ConfigService();
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM config_app');
        clearConfigCache();
    }

    public function testDefaultLabelUsesChsctDefaultAndInvariableForm(): void
    {
        $this->assertSame('Membre FS/CSA', UserRole::Chsct->defaultLabel());

        $this->assertSame(
            'Transmission — Membre FS/CSA',
            $this->service->transmissionLabel(),
            'default → "Transmission — " + UserRole::Chsct->defaultLabel(), no trailing "s"'
        );
    }

    public function testCustomRoleLabelIsReflected(): void
    {
        $this->service->set('app_role_label_chsct', 'Délégué FS/CSA');

        $this->assertSame('Transmission — Délégué FS/CSA', $this->service->transmissionLabel());
    }

    public function testHelperDelegatesToConfigService(): void
    {
        $this->assertSame($this->service->transmissionLabel(), transmissionLabel());
    }

    public function testHelperReflectsCustomRoleLabel(): void
    {
        $config = getConfigService();
        $config->set('app_role_label_chsct', 'Commission CSA');
        $config->clearCache();

        $this->assertSame('Transmission — Commission CSA', transmissionLabel());
    }

    public function testLabelNeverContainsHardcodedChsctNorTrailingPlural(): void
    {
        $label = $this->service->transmissionLabel();

        $this->assertStringNotContainsString('CHSCT', $label);
        $this->assertStringNotContainsString('CSA/CHSCT', $label);
        $this->assertSame(
            'Transmission — Membre FS/CSA',
            $label,
            'the role label must not gain a plural "s"'
        );
    }
}