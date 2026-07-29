<?php
/**
 * RGPD Anonymization Tests — Application SST DREETS BFC
 *
 * Verifies that UserRepository::anonymize() does NOT touch
 * pour_compte_nom/prenom (third-party identity).
 */

use PHPUnit\Framework\TestCase;

class RgpdAnonymizeTest extends TestCase
{
    private PDO $pdo;
    private int $siteId;
    private int $agentId;

    protected function setUp(): void
    {
        $this->pdo = getDB();

        // Clean up
        $this->pdo->exec('DELETE FROM report_access_log');
        $this->pdo->exec('DELETE FROM report_state_history');
        $this->pdo->exec('DELETE FROM report_responses');
        $this->pdo->exec('DELETE FROM report_agent_invites');
        $this->pdo->exec('DELETE FROM reports');

        // Seed site
        $this->pdo->exec("INSERT OR IGNORE INTO sites (code, nom, is_active) VALUES ('UD_RGPD', 'RGPD Test', 1)");
        $this->siteId = (int) $this->pdo->query("SELECT id FROM sites WHERE code = 'UD_RGPD'")->fetchColumn();

        // Seed user
        $this->pdo->exec("INSERT OR IGNORE INTO users (username, nom, prenom, role, site_id, is_active) VALUES ('rgpd_agent', 'Agent', 'RGPD', 'agent', {$this->siteId}, 1)");
        $this->agentId = (int) $this->pdo->query("SELECT id FROM users WHERE username = 'rgpd_agent'")->fetchColumn();
    }

    public function testAnonymizeDoesNotTouchPourCompteFields(): void
    {
        $uuid = 'test-rgpd-' . uniqid();
        $this->pdo->prepare('
            INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, lieu, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, consent_syndicat, etat, pour_compte_nom, pour_compte_prenom)
            VALUES (:uuid, :reference, :type, :objet, :description, :date_evenement, :lieu, :declarant_id, :declarant_nom, :declarant_prenom, :site_id, 0, 0, :etat, :pc_nom, :pc_prenom)
        ')->execute([
            ':uuid' => $uuid, ':reference' => 'RAMI-25-900', ':type' => 'rami',
            ':objet' => 'Test RGPD', ':description' => 'Test',
            ':date_evenement' => '2025-01-15', ':lieu' => 'Bureau',
            ':declarant_id' => $this->agentId, ':declarant_nom' => 'Agent',
            ':declarant_prenom' => 'RGPD', ':site_id' => $this->siteId, ':etat' => 'nouveau',
            ':pc_nom' => 'TiersConcerné', ':pc_prenom' => 'Jean',
        ]);

        // Anonymize the declarant
        \App\Repository\UserRepository::instance()->anonymize($this->agentId);

        // Verify pour_compte fields are untouched
        $report = $this->pdo->prepare('SELECT pour_compte_nom, pour_compte_prenom FROM reports WHERE uuid = :uuid');
        $report->execute([':uuid' => $uuid]);
        $row = $report->fetch();

        $this->assertEquals('TiersConcerné', $row['pour_compte_nom'], 'pour_compte_nom should NOT be anonymized');
        $this->assertEquals('Jean', $row['pour_compte_prenom'], 'pour_compte_prenom should NOT be anonymized');

        // Verify declarant fields ARE anonymized
        $report2 = $this->pdo->prepare('SELECT declarant_nom, declarant_prenom FROM reports WHERE uuid = :uuid');
        $report2->execute([':uuid' => $uuid]);
        $row2 = $report2->fetch();

        $this->assertEquals('Anonymisé', $row2['declarant_nom'], 'declarant_nom should be anonymized');
        $this->assertEquals('Anonymé', $row2['declarant_prenom'], 'declarant_prenom should be anonymized');
    }
}
