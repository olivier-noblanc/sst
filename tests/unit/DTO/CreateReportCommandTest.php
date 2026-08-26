<?php

use PHPUnit\Framework\TestCase;
use App\DTO\CreateReportCommand;
use App\DTO\SiteId;

class CreateReportCommandTest extends TestCase
{
    public function testFromPostWithSiteIdZeroReturnsSiteIdNone(): void
    {
        $post = [
            'site_id' => '0',
            'type' => 'rsst', 'objet' => 'Test', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_text' => 'UR21',
            'pole' => '', 'service_affectation' => '', 'telephone_mobile' => '',
            'pour_compte' => '0', 'pour_compte_nom' => '', 'pour_compte_prenom' => '',
            'is_confidential' => '1', 'consent_syndicat' => '0',
            'nature_auteur' => '', 'type_acte' => '',
        ];
        $user = ['id' => 42, 'nom' => 'Martin', 'prenom' => 'Jean'];
        
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertInstanceOf(SiteId::class, $cmd->siteId);
        $this->assertTrue($cmd->siteId->isNone());
    }

    public function testFromPostWithSiteIdNullReturnsSiteIdNone(): void
    {
        $post = [
            'site_id' => null,
            'type' => 'rsst', 'objet' => 'Test', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_text' => 'UR21',
            'pole' => '', 'service_affectation' => '', 'telephone_mobile' => '',
            'pour_compte' => '0', 'pour_compte_nom' => '', 'pour_compte_prenom' => '',
            'is_confidential' => '1', 'consent_syndicat' => '0',
            'nature_auteur' => '', 'type_acte' => '',
        ];
        $user = ['id' => 42, 'nom' => 'Martin', 'prenom' => 'Jean'];
        
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertInstanceOf(SiteId::class, $cmd->siteId);
        $this->assertTrue($cmd->siteId->isNone());
    }

    public function testFromPostWithSiteIdOneReturnsSiteIdOne(): void
    {
        $post = [
            'site_id' => '1',
            'type' => 'rsst', 'objet' => 'Test', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_text' => 'UR21',
            'pole' => '', 'service_affectation' => '', 'telephone_mobile' => '',
            'pour_compte' => '0', 'pour_compte_nom' => '', 'pour_compte_prenom' => '',
            'is_confidential' => '1', 'consent_syndicat' => '0',
            'nature_auteur' => '', 'type_acte' => '',
        ];
        $user = ['id' => 42, 'nom' => 'Martin', 'prenom' => 'Jean'];
        
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertInstanceOf(SiteId::class, $cmd->siteId);
        $this->assertFalse($cmd->siteId->isNone());
        $this->assertSame(1, $cmd->siteId->toSql());
    }

    public function testFromPostWithPourCompteNomWithSpacesReturnsTrimmed(): void
    {
        $post = [
            'pour_compte' => '1',
            'pour_compte_nom' => '  John Doe  ',
            'pour_compte_prenom' => 'Jane',
            'site_id' => '0',
            'type' => 'rsst', 'objet' => 'Test', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_text' => 'UR21',
            'pole' => '', 'service_affectation' => '', 'telephone_mobile' => '',
            'is_confidential' => '1', 'consent_syndicat' => '0',
            'nature_auteur' => '', 'type_acte' => '',
        ];
        $user = ['id' => 42, 'nom' => 'Martin', 'prenom' => 'Jean'];
        
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertEquals('John Doe', $cmd->pourCompteNom);
    }

    public function testFromPostWithPourCompteFalseReturnsNullForPourCompteFields(): void
    {
        $post = [
            'pour_compte' => '0',
            'pour_compte_nom' => 'John Doe',
            'pour_compte_prenom' => 'Jane',
            'site_id' => '0',
            'type' => 'rsst', 'objet' => 'Test', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_text' => 'UR21',
            'pole' => '', 'service_affectation' => '', 'telephone_mobile' => '',
            'is_confidential' => '1', 'consent_syndicat' => '0',
            'nature_auteur' => '', 'type_acte' => '',
        ];
        $user = ['id' => 42, 'nom' => 'Martin', 'prenom' => 'Jean'];
        
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertNull($cmd->pourCompteNom);
        $this->assertNull($cmd->pourComptePrenom);
    }
}