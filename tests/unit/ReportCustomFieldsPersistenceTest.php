<?php
/**
 * Report Custom Fields Persistence Test — Application SST DREETS BFC
 *
 * TDD: tests written BEFORE implementation.
 *
 * Intégration bout-en-bout de la persistance des champs dynamiques des
 * registres personnalisés :
 *   validation serveur → persistance atomique → relecture → mise à jour
 *   → compatibilité RAMI/DGI (champs legacy en colonnes physiques reports).
 */

use App\DTO\CreateReportCommand;
use App\DTO\SiteId;
use App\DTO\UpdateReportCommand;
use App\Enum\ReportType;
use App\Event\EventDispatcher;
use App\Repository\RegistryFieldRepository;
use App\Repository\RegistryFieldValueRepository;
use App\Repository\RegistryRepository;
use App\Repository\ReportRepository;
use App\Services\CustomFieldsService;
use App\Services\ReportService;
use App\Services\ReportStateMachine;
use PHPUnit\Framework\TestCase;

class ReportCustomFieldsPersistenceTest extends TestCase
{
    private PDO $pdo;
    private ReportService $service;
    private CustomFieldsService $customFields;
    private int $siteId;
    private int $userId;
    private int $registryId;

    protected function setUp(): void
    {
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id IN (SELECT id FROM registries WHERE code LIKE 'cfp_%' OR code = 'rami')");
        $this->pdo->exec("DELETE FROM registries WHERE code LIKE 'cfp_%' OR code = 'rami'");

        $this->service = new ReportService(
            new ReportRepository($this->pdo),
            new EventDispatcher(),
            new ReportStateMachine(),
        );
        $this->customFields = new CustomFieldsService(
            new RegistryFieldRepository($this->pdo),
            new RegistryRepository($this->pdo),
        );

        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UD21_CFP', 'Cote-d-Or CFP', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD21_CFP'")->fetchColumn();
        $this->pdo->exec("INSERT INTO users (username, nom, prenom, role, site_id, is_active, email)
            VALUES ('test.cfp.agent', 'Martin', 'Jean', 'agent', {$this->siteId}, 1, 'fixture@dreets-bfc.gouv.fr')");
        $this->userId = (int) $this->pdo->lastInsertId();

        // Registre custom avec les 4 types de champs, dont un obligatoire
        $this->pdo->exec("INSERT INTO registries (code, label, short_label, color_theme, is_enabled, is_system, default_visibility)
            VALUES ('cfp_reg', 'Registre persistance test', 'CFP', 'vert', 1, 0, 'agent_choice')");
        $this->registryId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order) VALUES
            ({$this->registryId}, 'cfp_texte', 'Contexte', 'text', NULL, 1, 1),
            ({$this->registryId}, 'cfp_select', 'Nature', 'select', '{\"usager\":\"Usager\",\"collegue\":\"Collègue\"}', 0, 2),
            ({$this->registryId}, 'cfp_case', 'Accord', 'checkbox', NULL, 0, 3),
            ({$this->registryId}, 'cfp_zone', 'Détails', 'textarea', NULL, 0, 4)
        ");
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM registry_field_values');
        $this->pdo->exec("DELETE FROM registry_fields WHERE registry_id IN (SELECT id FROM registries WHERE code LIKE 'cfp_%' OR code = 'rami')");
        $this->pdo->exec("DELETE FROM registries WHERE code LIKE 'cfp_%' OR code = 'rami'");
    }

    private function createCommand(array $customFields = [], string $type = 'cfp_reg'): CreateReportCommand
    {
        return new CreateReportCommand(
            type: $type,
            objet: 'Objet test custom',
            description: 'Description suffisamment longue pour la validation.',
            dateEvenement: '2026-01-15',
            heureEvenement: '10:30',
            lieu: 'Bureau 204',
            declarantId: $this->userId,
            declarantNom: 'Martin',
            declarantPrenom: 'Jean',
            siteId: SiteId::fromInput($this->siteId),
            siteText: null,
            pole: 'Pôle A',
            serviceAffectation: null,
            telephoneMobile: '0601020304',
            isConfidential: false,
            consentSyndicat: false,
            natureAuteur: null,
            typeActe: null,
            pourCompteNom: null,
            pourComptePrenom: null,
            attachmentBlob: null,
            attachmentName: null,
            attachmentMime: null,
            customFields: $customFields,
        );
    }

    private function countValueRows(string $uuid): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM registry_field_values WHERE report_uuid = ?');
        $stmt->execute([$uuid]);
        return (int) $stmt->fetchColumn();
    }

    // ─── CRÉATION → PERSISTANCE → RELECTURE ──────────────────────────────────

    public function testCreatePersistsCustomFieldValuesAndReadsThemBack(): void
    {
        $cmd = $this->createCommand([
            'cfp_texte' => 'Risque électrique au 2e étage',
            'cfp_select' => 'usager',
            'cfp_case' => '1',
            'cfp_zone' => "Ligne 1 du détail\nLigne 2 du détail",
        ]);

        $report = $this->service->create($cmd);
        $this->assertNotEmpty($report->uuid);

        $values = RegistryFieldValueRepository::instance()->findByReport($report->uuid);
        // Ordre déterministe : field_code ASC
        $this->assertSame([
            'cfp_case' => '1',
            'cfp_select' => 'usager',
            'cfp_texte' => 'Risque électrique au 2e étage',
            'cfp_zone' => "Ligne 1 du détail\nLigne 2 du détail",
        ], $values);
        $this->assertSame(4, $this->countValueRows($report->uuid));
    }

    public function testCreateWithoutCustomFieldsLeavesNoValueRows(): void
    {
        $report = $this->service->create($this->createCommand([], 'rsst'));
        $this->assertSame(0, $this->countValueRows($report->uuid));
    }

    // ─── ÉDITION → REMPLACEMENT → NULL EXPLICITE ─────────────────────────────

    public function testUpdateReplacesValuesAndClearsNullifiedField(): void
    {
        $report = $this->service->create($this->createCommand([
            'cfp_texte' => 'Valeur initiale',
            'cfp_select' => 'usager',
            'cfp_case' => '1',
            'cfp_zone' => 'Détails initiaux',
        ]));

        $updateCmd = new UpdateReportCommand(
            objet: 'Objet modifié',
            description: 'Description modifiée suffisamment longue.',
            dateEvenement: '2026-01-16',
            heureEvenement: '11:00',
            lieu: 'Bureau 205',
            siteText: null,
            pole: 'Pôle B',
            serviceAffectation: null,
            telephoneMobile: '0601020304',
            isConfidential: false,
            consentSyndicat: false,
            customFields: [
                'cfp_texte' => 'Valeur modifiée',
                'cfp_select' => 'collegue',
                'cfp_case' => null,          // décoché → suppression explicite
                'cfp_zone' => null,          // vidé → suppression explicite
            ],
        );
        $this->assertTrue($this->service->update($report->uuid, $updateCmd, $this->userId));

        $values = RegistryFieldValueRepository::instance()->findByReport($report->uuid);
        $this->assertSame([
            'cfp_select' => 'collegue',
            'cfp_texte' => 'Valeur modifiée',
        ], $values);
        $this->assertSame(2, $this->countValueRows($report->uuid));
    }

    public function testRepositoryUpdateWithoutRegistryCodeKeepsExistingValues(): void
    {
        // Compat des anciens appels à 3 arguments : la persistance custom
        // ne doit PAS être effacée par un appel qui ne connaît pas les champs.
        $report = $this->service->create($this->createCommand(['cfp_texte' => 'À conserver']));

        $updateCmd = new UpdateReportCommand(
            objet: 'Objet modifié',
            description: 'Description modifiée suffisamment longue.',
            dateEvenement: '2026-01-16',
            heureEvenement: null,
            lieu: null,
            siteText: null,
            pole: null,
            serviceAffectation: null,
            telephoneMobile: null,
            isConfidential: false,
            consentSyndicat: false,
        );
        $this->assertTrue((new ReportRepository($this->pdo))->update($report->uuid, $updateCmd, $this->userId));

        $values = RegistryFieldValueRepository::instance()->findByReport($report->uuid);
        $this->assertSame(['cfp_texte' => 'À conserver'], $values);
    }

    // ─── VALIDATION SERVEUR (refus avant toute écriture) ─────────────────────

    public function testCreateFailsWhenRequiredCustomFieldMissing(): void
    {
        $reportsBefore = (int) $this->pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();

        try {
            $this->service->create($this->createCommand([
                'cfp_texte' => null, // obligatoire absent
                'cfp_select' => 'usager',
            ]));
            $this->fail('InvalidArgumentException attendu : champ custom obligatoire absent.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('obligatoire', $e->getMessage());
        }

        $reportsAfter = (int) $this->pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
        $this->assertSame($reportsBefore, $reportsAfter, 'Aucun signalement ne doit être créé quand la validation custom échoue.');
    }

    public function testCreateFailsOnInvalidSelectValue(): void
    {
        $reportsBefore = (int) $this->pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();

        try {
            $this->service->create($this->createCommand([
                'cfp_texte' => 'ok',
                'cfp_select' => 'valeur_pirate',
            ]));
            $this->fail('InvalidArgumentException attendu : valeur de select invalide.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('invalide', $e->getMessage());
        }

        $reportsAfter = (int) $this->pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
        $this->assertSame($reportsBefore, $reportsAfter);
    }

    // ─── COMPATIBILITÉ RAMI / CHAMPS PHYSIQUES ───────────────────────────────

    public function testRamiLegacyFieldsGoToPhysicalColumnsOnlyNoDoubleTruth(): void
    {
        // Définitions RAMI legacy (comme le seed production)
        $this->pdo->exec("INSERT INTO registries (code, label, short_label, color_theme, is_enabled, is_system, default_visibility, requires_pour_compte)
            VALUES ('rami', 'Agressions, Menaces et Incivilités', 'RAMI', 'rami', 1, 0, 'agent_choice', 1)");
        $ramiId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO registry_fields (registry_id, field_code, label, field_type, options, is_required, sort_order) VALUES
            ({$ramiId}, 'nature_auteur', 'Nature de l''auteur', 'select', '{\"usager\":\"Usager\"}', 0, 1),
            ({$ramiId}, 'type_acte', 'Type d''acte', 'select', '{\"verbal\":\"Verbal\"}', 0, 2)
        ");

        $cmd = $this->createCommand([], ReportType::Rami->value);
        $cmdData = array_merge($cmd->toArray(), [
            'natureAuteur' => 'usager',
            'typeActe' => 'verbal',
            'siteId' => SiteId::fromInput($this->siteId),
        ]);
        $cmd = new CreateReportCommand(...$cmdData);

        $report = $this->service->create($cmd);

        // Les valeurs legacy vivent dans les colonnes physiques reports…
        $stmt = $this->pdo->prepare('SELECT nature_auteur, type_acte FROM reports WHERE uuid = ?');
        $stmt->execute([$report->uuid]);
        $row = $stmt->fetch();
        $this->assertSame('usager', $row['nature_auteur']);
        $this->assertSame('verbal', $row['type_acte']);

        // …et ne sont JAMAIS dupliquées dans registry_field_values.
        $this->assertSame(0, $this->countValueRows($report->uuid));
    }

    public function testDgiRegistryUnaffectedByCustomFields(): void
    {
        $report = $this->service->create($this->createCommand([], ReportType::Rsst->value));
        $this->assertNotEmpty($report->uuid);
        $this->assertSame(0, $this->countValueRows($report->uuid));
    }
}
