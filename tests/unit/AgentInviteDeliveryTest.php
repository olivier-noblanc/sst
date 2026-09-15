<?php
/**
 * Agent invite delivery — persistance conditionnée au verdict sendMail().
 *
 * Décision Oracle SMTP (bug #10) : une invitation NE DOIT être persistée que
 * si l'e-mail est réellement parti. Avant, la ligne était insérée AVANT
 * l'envoi (ou sans consommer le verdict) : un échec SMTP laissait une invite
 * orpheline au token jamais reçu. Le seam mailer injectable rend le verdict
 * déterministe ; la fonction retourne désormais la liste des échecs.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/mail.php';

class AgentInviteDeliveryTest extends TestCase
{
    private PDO $pdo;
    private string $reportUuid;

    protected function setUp(): void
    {
        setMailerSeam(null);
        $this->pdo = getDB();
        cleanupAllForTest($this->pdo);
        $this->pdo->exec('DELETE FROM report_agent_invites');

        $this->reportUuid = sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
        $this->pdo->exec("INSERT INTO users (id, username, nom, prenom, role, site_id, is_active, email)
            VALUES (9201, 'test.invite.decl', 'Decl', 'Arant', 'agent', NULL, 1, 'decl.invite@dreets-bfc.gouv.fr')");
        $this->pdo->exec("INSERT INTO reports (uuid, reference, type, objet, description, date_evenement, declarant_id, declarant_nom, declarant_prenom, site_id, is_confidential, etat)
            VALUES ('{$this->reportUuid}', 'RSST-26-INV', 'rsst', 'Objet invite', 'Desc', '2026-01-10', 9201, 'Decl', 'Arant', NULL, 0, 'nouveau')");
    }

    protected function tearDown(): void
    {
        setMailerSeam(null);
        $this->pdo->exec('DELETE FROM report_agent_invites');
    }

    private function inviteCount(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM report_agent_invites WHERE report_uuid = '{$this->reportUuid}'"
        )->fetchColumn();
    }

    public function testInviteNotPersistedWhenSendFails(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => false);

        $failed = sendAgentInviteEmails($this->pdo, $this->reportUuid, ['agent.fail@dreets-bfc.gouv.fr']);

        $this->assertSame(0, $this->inviteCount(), 'Un envoi échoué ne doit JAMAIS laisser d\'invite orpheline');
        $this->assertSame(['agent.fail@dreets-bfc.gouv.fr'], $failed, 'L\'échec doit être retourné à l\'appelant');
    }

    public function testInvitePersistedWhenSendSucceeds(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        $failed = sendAgentInviteEmails($this->pdo, $this->reportUuid, ['agent.ok@dreets-bfc.gouv.fr']);

        $this->assertSame(1, $this->inviteCount(), 'Invite persistée seulement après un envoi réussi');
        $this->assertSame([], $failed, 'Aucun échec quand l\'envoi réussit');
    }

    public function testOnlySuccessfulRecipientsArePersisted(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => $to === 'ok@dreets-bfc.gouv.fr');

        $failed = sendAgentInviteEmails($this->pdo, $this->reportUuid, ['ok@dreets-bfc.gouv.fr', 'fail@dreets-bfc.gouv.fr']);

        $this->assertSame(1, $this->inviteCount(), 'Seul le destinataire joignable est persisté');
        $persisted = (string) $this->pdo->query(
            "SELECT email FROM report_agent_invites WHERE report_uuid = '{$this->reportUuid}'"
        )->fetchColumn();
        $this->assertSame('ok@dreets-bfc.gouv.fr', $persisted);
        $this->assertSame(['fail@dreets-bfc.gouv.fr'], $failed);
    }

    public function testUnknownReportReturnsEmptyFailures(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        $this->assertSame([], sendAgentInviteEmails($this->pdo, 'unknown-report-uuid', ['agent@dreets-bfc.gouv.fr']));
    }
}