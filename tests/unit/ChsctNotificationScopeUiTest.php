<?php

/**
 * CHSCT notification vs consultation scope — UI clarification tests.
 *
 * TDD: these tests were written BEFORE the tab_app.php clarification.
 *
 * Two independent mechanisms are involved:
 *   - `registries.notify_chsct` decides WHO receives the CSA/CHSCT
 *     notification e-mail when a report is created (per-registry, Registres tab).
 *   - `app_chsct_report_scope` decides WHICH reports the CSA/CHSCT may consult
 *     (consent_only | all, app tab).
 *
 * Because they are independent, `consent_only` can notify the CSA/CHSCT about
 * a report they cannot open. The app tab must therefore separate the two notions
 * and surface a warning when that mismatch is active.
 *
 * Scope is deliberately UI-only: no sending logic and no default business rule
 * is modified here. The warning states it is a local business choice and makes
 * no legal-compliance claim.
 */

use PHPUnit\Framework\TestCase;

class ChsctNotificationScopeUiTest extends TestCase
{
    private PDO $pdo;

    /** @var array{notify_chsct: int, is_enabled: int}|null */
    private ?array $dgiOriginal = null;

    protected function setUp(): void
    {
        $this->pdo = getDB();

        $row = $this->pdo->query("SELECT notify_chsct, is_enabled FROM registries WHERE code = 'dgi'")->fetch();
        if (is_array($row)) {
            $this->dgiOriginal = [
                'notify_chsct' => (int) $row['notify_chsct'],
                'is_enabled' => (int) $row['is_enabled'],
            ];
        }

        getConfigService()->set('app_chsct_report_scope', 'consent_only');
        clearConfigCache();
    }

    protected function tearDown(): void
    {
        // The in-memory SQLite DB is a process-wide singleton shared with every
        // other test class — restore exactly what we captured, never the seed
        // assumption.
        if ($this->dgiOriginal !== null) {
            $stmt = $this->pdo->prepare(
                "UPDATE registries SET notify_chsct = :n, is_enabled = :e WHERE code = 'dgi'"
            );
            $stmt->execute([
                ':n' => $this->dgiOriginal['notify_chsct'],
                ':e' => $this->dgiOriginal['is_enabled'],
            ]);
        }

        getConfigService()->set('app_chsct_report_scope', 'consent_only');
        clearConfigCache();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function setDgiNotification(int $notify, int $enabled): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE registries SET notify_chsct = :n, is_enabled = :e WHERE code = 'dgi'"
        );
        $stmt->execute([':n' => $notify, ':e' => $enabled]);
    }

    private function renderAppTab(): string
    {
        $csrfToken = 'test-csrf-token';

        ob_start();
        require __DIR__ . '/../../pages/settings/tab_app.php';

        return (string) ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Separation of the two notions
    // ═══════════════════════════════════════════════════════════════════════════

    public function testTabSeparatesNotificationRecipientsFromConsultationScope(): void
    {
        $this->setDgiNotification(1, 1);

        $html = $this->renderAppTab();

        $short = getRoleLabelShort(\App\Enum\UserRole::Chsct->value);
        $this->assertStringContainsString(
            'Qui reçoit la notification ' . $short,
            $html,
            'The tab must state who receives the role notification'
        );
        $this->assertStringContainsString(
            'Quels signalements le ' . $short . ' peut consulter',
            $html,
            'The tab must state which reports the role can consult'
        );
        $this->assertStringContainsString(
            'Consentement uniquement',
            $html,
            'The current consultation scope must be recapped'
        );
        $this->assertStringContainsString(
            'tab=registres',
            $html,
            'The notification recap must link to the Registres tab where notify_chsct is edited'
        );
        $this->assertStringNotContainsString(
            'style="',
            $html,
            'No inline style may be introduced (CSS belongs in public/css/style.css)'
        );
    }

    public function testRecipientRecapListsNotifyingRegistries(): void
    {
        $this->setDgiNotification(1, 1);

        $html = $this->renderAppTab();

        $this->assertStringContainsString(
            'data-registry="dgi"',
            $html,
            'A registry with notify_chsct=1 must appear in the recipient recap'
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DGI global toggle vs per-registry setting
    // ═══════════════════════════════════════════════════════════════════════════

    public function testDgiGlobalToggleIsDocumentedAsFallbackOnly(): void
    {
        $html = $this->renderAppTab();

        $this->assertStringContainsString(
            'est prioritaire',
            $html,
            'The DGI global toggle must state that the per-registry setting takes precedence'
        );
        $this->assertStringContainsString(
            'compatibilité',
            $html,
            'The DGI global toggle must be documented as a compatibility fallback'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Conflict warning
    // ═══════════════════════════════════════════════════════════════════════════

    public function testWarningWhenConsentOnlyScopeBlocksNotifiedReports(): void
    {
        $this->setDgiNotification(1, 1);

        $html = $this->renderAppTab();

        $this->assertStringContainsString(
            'Notification possiblement inaccessible',
            $html,
            'A notified-but-unopenable report must be flagged'
        );
        $this->assertStringContainsString(
            'choix métier à valider localement',
            $html,
            'The mismatch must be presented as a local business choice to validate'
        );
        $this->assertStringContainsString(
            'conformité juridique',
            $html,
            'The warning must not claim legal compliance'
        );
    }

    public function testNoWarningWhenScopeIsAll(): void
    {
        $this->setDgiNotification(1, 1);
        getConfigService()->set('app_chsct_report_scope', 'all');
        clearConfigCache();

        $html = $this->renderAppTab();

        $this->assertStringNotContainsString(
            'Notification possiblement inaccessible',
            $html,
            'With scope=all the notified report is openable: no conflict warning expected'
        );
    }

    public function testNoWarningWhenNoEnabledRegistryNotifies(): void
    {
        $this->setDgiNotification(1, 0);

        $html = $this->renderAppTab();

        $this->assertStringNotContainsString(
            'Notification possiblement inaccessible',
            $html,
            'A disabled registry cannot generate notifications: no conflict warning expected'
        );
        $this->assertStringContainsString(
            'data-registry="dgi"',
            $html,
            'The disabled notifying registry is still shown in the recap'
        );
        $this->assertStringContainsString(
            'désactivé',
            $html,
            'A disabled notifying registry must be marked as such'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Defaults preserved
    // ═══════════════════════════════════════════════════════════════════════════

    public function testRenderingDoesNotMutateRegistryNotificationDefaults(): void
    {
        $before = [
            'rsst' => $this->pdo->query("SELECT notify_chsct FROM registries WHERE code = 'rsst'")->fetchColumn(),
            'rami' => $this->pdo->query("SELECT notify_chsct FROM registries WHERE code = 'rami'")->fetchColumn(),
            'dgi' => $this->pdo->query("SELECT notify_chsct FROM registries WHERE code = 'dgi'")->fetchColumn(),
        ];

        $this->renderAppTab();

        $after = [
            'rsst' => $this->pdo->query("SELECT notify_chsct FROM registries WHERE code = 'rsst'")->fetchColumn(),
            'rami' => $this->pdo->query("SELECT notify_chsct FROM registries WHERE code = 'rami'")->fetchColumn(),
            'dgi' => $this->pdo->query("SELECT notify_chsct FROM registries WHERE code = 'dgi'")->fetchColumn(),
        ];

        $this->assertEquals($before, $after, 'Rendering the settings tab must not mutate registry notification flags');
        $this->assertSame(0, (int) $after['rsst'], 'RSST must not be forced to notify the CSA/CHSCT');
        $this->assertSame(0, (int) $after['rami'], 'RAMI must not be forced to notify the CSA/CHSCT');
    }
}
