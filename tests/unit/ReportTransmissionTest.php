<?php

/**
 * Transmission CSA/CHSCT — décision métier (Oracle).
 *
 * Contrat verrouillé ici :
 *   - la création d'un signalement n'envoie / n'enqueue AUCUN e-mail CSA/CHSCT
 *     automatique (le consentement est une consigne pour le superviseur) ;
 *   - un superviseur peut déclencher MANUELLEMENT la transmission vers les
 *     membres CSA/CHSCT via l'outbox, dédupliquée par (signalement × destinataire) ;
 *   - les membres CSA/CHSCT voient toujours les signalements, indépendamment du
 *     consentement syndical.
 */

use App\DTO\ReportFilter;
use App\Enum\OutboxEvent;
use App\Enum\UserRole;
use App\Repository\ReportRepository;
use App\Services\AccessService;
use App\DTO\SessionUser;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class ReportTransmissionTest extends TestCase
{
    private PDO $pdo;
    private int $siteId;
    private int $declarantId;
    private int $csaUserId;
    private string $reportUuid;

    private const CSA_EMAIL = 'csa.transmission@dreets-bfc.gouv.fr';
    private const DECLARANT_EMAIL = 'declarant.transmission@dreets-bfc.gouv.fr';

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM email_outbox');
        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UDTR', 'Site Transmission', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UDTR'")->fetchColumn();

        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('test.trans.decl', 'Decl', 'Arant', 'agent', {$this->siteId}, 1, '" . self::DECLARANT_EMAIL . "')");
        $this->declarantId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'test.trans.decl'")->fetchColumn();

        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active, email) VALUES ('test.trans.csa', 'Csa', 'Membre', 'chsct', {$this->siteId}, 1, '" . self::CSA_EMAIL . "')");
        $this->csaUserId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'test.trans.csa'")->fetchColumn();

        // Le registre notifie le CSA à la création — mais la création ne doit
        // plus générer d'e-mail automatique (contrat).
        $this->pdo->exec("UPDATE registries SET notify_chsct = 1 WHERE code = 'rsst'");

        $this->reportUuid = 'abcdabcd-1111-2222-3333-444444444444';
        $stmt = $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement,
                                 declarant_id, declarant_nom, declarant_prenom, site_id,
                                 is_confidential, consent_syndicat, etat)
            VALUES (:uuid, :ref, :type, :objet, :descr, :date, :declarant_id, :nom, :prenom, :site_id, 0, 0, :etat)
        ');
        $stmt->execute([
            ':uuid' => $this->reportUuid,
            ':ref' => 'RSST-26-TR1',
            ':type' => 'rsst',
            ':objet' => 'Objet transmission',
            ':descr' => 'Description transmission',
            ':date' => '2026-02-01',
            ':declarant_id' => $this->declarantId,
            ':nom' => 'Decl',
            ':prenom' => 'Arant',
            ':site_id' => $this->siteId,
            ':etat' => 'nouveau',
        ]);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("UPDATE registries SET notify_chsct = 0 WHERE code = 'rsst'");
        $this->pdo->exec('DELETE FROM email_outbox');
        getConfigService()->set('app_chsct_report_scope', 'consent_only');
        clearConfigCache();
        cleanupAllForTest($this->pdo);
    }

    private function csaOutboxCount(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM email_outbox WHERE recipient = :email');
        $stmt->execute([':email' => self::CSA_EMAIL]);
        return (int) $stmt->fetchColumn();
    }

    private function transmittedDedupKey(): string
    {
        return OutboxEvent::ReportTransmitted->value . ':' . $this->reportUuid . ':' . self::CSA_EMAIL;
    }

    // ═══ 1. Création : aucun e-mail CSA/CHSCT automatique ═══════════════════

    public function testCreationDoesNotEnqueueAutomaticCsaEmail(): void
    {
        notifyNewReport($this->pdo, $this->reportUuid, 'rsst', $this->siteId);

        $this->assertSame(
            0,
            $this->csaOutboxCount(),
            'La création ne doit plus générer d\'e-mail CSA/CHSCT automatique : consent_syndicat est une consigne pour le superviseur.'
        );
        $this->assertSame(
            0,
            (int) $this->pdo->query("SELECT COUNT(*) FROM email_outbox WHERE dedup_key LIKE 'report_transmitted:%'")->fetchColumn(),
            'Aucune transmission (même différée) ne doit être enqueue à la création.'
        );
    }

    // ══ 2/3. Action superviseur : enqueue + déduplication (signalement × destinataire)

    public function testSupervisorTransmissionEnqueuesCsaMember(): void
    {
        $enqueued = notifyReportTransmitted($this->pdo, $this->reportUuid);

        $this->assertSame(1, $enqueued, 'Un membre CSA actif avec email doit être mis en file');
        $this->assertSame(1, $this->csaOutboxCount());

        $stmt = $this->pdo->prepare('SELECT subject, body, status FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => $this->transmittedDedupKey()]);
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertStringContainsString('Objet transmission', (string) $row['body']);
        $this->assertStringContainsString('RSST-26-TR1', (string) $row['subject']);
        $this->assertSame('pending', (string) $row['status']);
    }

    public function testSupervisorTransmissionIsDeduplicatedByReportAndRecipient(): void
    {
        notifyReportTransmitted($this->pdo, $this->reportUuid);
        $second = notifyReportTransmitted($this->pdo, $this->reportUuid);

        $this->assertSame(0, $second, 'Rejouer la transmission ne doit pas ré-enqueue un message identique');
        $this->assertSame(1, $this->csaOutboxCount(), 'Déduplication par (signalement × destinataire)');
    }

    // ═══ 4. Accès CSA indépendant du consentement ══════════════════════════

    public function testCsaMemberSeesReportRegardlessOfConsentScopeAndValue(): void
    {
        getConfigService()->set('app_chsct_report_scope', 'consent_only');
        clearConfigCache();

        $report = ReportRepository::instance()->findById($this->reportUuid);
        $this->assertNotNull($report);
        $this->assertSame(0, $report->consentSyndicat, 'Fixture : signalement sans consentement');

        $user = SessionUser::fromArray([
            'id' => $this->csaUserId,
            'role' => UserRole::Chsct->value,
            'site_id' => $this->siteId,
        ]);

        $this->assertTrue(
            new AccessService()->canAccessReport($report, $user),
            'Un membre CSA/CHSCT voit toujours le signalement, même sans consentement.'
        );

        $filter = new ReportFilter(type: 'rsst', seeAllSites: true, chsctConsentOnly: true);
        $uuids = array_map(fn($r) => $r->uuid, ReportRepository::instance()->findPaginated($filter, 1, 100)->reports);
        $this->assertContains(
            $this->reportUuid,
            $uuids,
            'La liste CSA/CHSCT ne doit plus être restreinte par le consentement.'
        );
    }
}