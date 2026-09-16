<?php

/**
 * Agent invite delivery — invariant « enqueue durable ».
 *
 * Décision produit « aucun mail ne doit être perdu » (remplace le
 * send-then-persist du bug #10) : l'invitation EST persistée et le message EST
 * mis en file AVANT tout transport. Un SMTP indisponible ne perd plus
 * l'invitation : le worker outbox rejouera l'envoi.
 *
 * Le seam mailer sert d'espion : s'il est appelé, c'est un envoi direct
 * résiduel (interdit ici — le transport appartient au worker).
 */

use App\Enum\OutboxEvent;
use App\Repository\AnonymizationPolicy;
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
        $this->pdo->exec('DELETE FROM email_outbox');

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
        $this->pdo->exec('DELETE FROM email_outbox');
    }

    private function inviteCount(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM report_agent_invites WHERE report_uuid = '{$this->reportUuid}'"
        )->fetchColumn();
    }

    private function outboxCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    }

    public function testInvitePersistedAndEnqueuedEvenWhenTransportWouldFail(): void
    {
        $sent = [];
        setMailerSeam(static function (string $to, string $subject, string $body, string $from = '') use (&$sent): bool {
            $sent[] = $to;
            return false;
        });

        $failed = sendAgentInviteEmails($this->pdo, $this->reportUuid, ['agent.fail@dreets-bfc.gouv.fr']);

        $this->assertSame([], $failed, 'Aucun échec : l\'enqueue durable a réussi');
        $this->assertSame(1, $this->inviteCount(), 'L\'invite est persistée (le worker garantit l\'envoi)');
        $this->assertSame(1, $this->outboxCount(), 'Le message est mis en file même si SMTP est indisponible');
        $this->assertSame([], $sent, 'Aucun envoi direct : le transport appartient au worker');
    }

    public function testInviteEnqueueIsDeduplicatedPerReportAndEmail(): void
    {
        setMailerSeam(static fn(string $to, string $subject, string $body, string $from = ''): bool => true);

        sendAgentInviteEmails($this->pdo, $this->reportUuid, ['agent.dup@dreets-bfc.gouv.fr']);
        sendAgentInviteEmails($this->pdo, $this->reportUuid, ['agent.dup@dreets-bfc.gouv.fr']);

        $this->assertSame(
            1,
            $this->outboxCount(),
            'Un même (signalement, destinataire) ne produit qu\'un message (dedup_key agent_invite)'
        );
        $this->assertSame(1, $this->inviteCount(), 'Le doublon n\'insère pas d\'invite supplémentaire');
    }

    public function testInviteMessageCarriesFrozenTokenLink(): void
    {
        setMailerSeam(null);

        sendAgentInviteEmails($this->pdo, $this->reportUuid, ['agent.link@dreets-bfc.gouv.fr']);

        $this->assertSame(1, $this->outboxCount());

        $token = (string) $this->pdo->query(
            "SELECT token FROM report_agent_invites WHERE report_uuid = '{$this->reportUuid}'"
        )->fetchColumn();
        $this->assertNotSame('', $token, 'Un token est persisté');

        // Identité d'occurrence = signalement + token de l'invite.
        $stmt = $this->pdo->prepare('SELECT * FROM email_outbox WHERE dedup_key = :k');
        $stmt->execute([':k' => OutboxEvent::AgentInvite->value . ':' . $this->reportUuid . ':' . $token . ':agent.link@dreets-bfc.gouv.fr']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);

        $this->assertStringContainsString(
            $token,
            (string) $row['body'],
            'Le corps figé contient le lien de confirmation portant le token persisté'
        );
    }

    public function testAnonymizedSentinelIsNeverInvited(): void
    {
        setMailerSeam(null);

        $failed = sendAgentInviteEmails($this->pdo, $this->reportUuid, [AnonymizationPolicy::ANONYMIZED_EMAIL]);

        $this->assertSame([], $failed);
        $this->assertSame(0, $this->inviteCount(), 'La sentinelle n\'est jamais invitée');
        $this->assertSame(0, $this->outboxCount(), 'La sentinelle n\'est jamais enqueue');
    }

    public function testUnknownReportReturnsEmptyFailures(): void
    {
        setMailerSeam(null);

        $this->assertSame([], sendAgentInviteEmails($this->pdo, 'unknown-report-uuid', ['agent@dreets-bfc.gouv.fr']));
        $this->assertSame(0, $this->outboxCount(), 'Rien n\'est mis en file pour un signalement inconnu');
    }
}
