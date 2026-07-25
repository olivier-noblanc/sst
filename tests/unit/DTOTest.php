<?php
use PHPUnit\Framework\TestCase;
use App\DTO\CreateReportCommand;
use App\DTO\ReportFilter;
use App\DTO\UpdateReportCommand;
use App\DTO\RespondToReportCommand;
use App\Enum\ReportState;

class DTOTest extends TestCase
{
    public function testCreateReportCommandFromPost(): void
    {
        $post = [
            'type' => 'rsst', 'objet' => 'Test', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_id' => '1', 'site_text' => 'UR21',
            'pole' => '', 'service_affectation' => '', 'telephone_mobile' => '',
            'pour_compte' => '0', 'pour_compte_nom' => '', 'pour_compte_prenom' => '',
            'is_confidential' => '1', 'consent_syndicat' => '0',
            'nature_auteur' => '', 'type_acte' => '',
        ];
        $user = ['id' => 42, 'nom' => 'Martin', 'prenom' => 'Jean', 'email' => 'jean@gouv.fr'];
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertEquals(ReportType::Rsst, $cmd->type);
        $this->assertEquals('Test', $cmd->objet);
        $this->assertEquals(42, $cmd->declarantId);
        $this->assertEquals(true, $cmd->isConfidential);
    }

    public function testReportFilterFromGet(): void
    {
        $get = ['type' => 'rsst', 'etat' => 'nouveau', 'site' => '5', 'q' => 'test'];
        $user = ['site_id' => 1, 'role' => 'superviseur'];
        $filter = ReportFilter::fromGet($get, $user);
        $this->assertEquals('rsst', $filter->type);
        $this->assertEquals('nouveau', $filter->etat);
        $this->assertEquals(5, $filter->siteId);
    }

    public function testUpdateReportCommandFromPost(): void
    {
        $post = [
            'objet' => 'Updated', 'description' => 'New desc',
            'date_evenement' => '2026-02-01', 'heure_evenement' => null,
            'lieu' => 'Salle', 'site_text' => '', 'pole' => '',
            'service_affectation' => '', 'telephone_mobile' => '',
            'is_confidential' => '0', 'consent_syndicat' => '1',
        ];
        $cmd = UpdateReportCommand::fromPost($post);
        $this->assertEquals('Updated', $cmd->objet);
        $this->assertEquals(false, $cmd->isConfidential);
        $this->assertEquals(true, $cmd->consentSyndicat);
    }

    public function testRespondToReportCommandFromPost(): void
    {
        $post = ['reponse' => 'Pris en compte', 'nouvel_etat' => 'en_cours'];
        $cmd = RespondToReportCommand::fromPost($post);
        $this->assertEquals('Pris en compte', $cmd->reponse);
        $this->assertEquals(ReportState::EnCours, $cmd->nouvelEtat);
    }
}