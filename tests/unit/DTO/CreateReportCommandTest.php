<?php

use PHPUnit\Framework\TestCase;
use App\DTO\CreateReportCommand;
use App\DTO\SiteId;

class CreateReportCommandTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function basePost(array $overrides = []): array
    {
        $post = [
            'site_id' => '0',
            'type' => 'rsst', 'objet' => 'Test', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_text' => 'UR21',
            'pole' => '', 'service_affectation' => '', 'telephone_mobile' => '',
            'pour_compte' => '1', 'pour_compte_nom' => '', 'pour_compte_prenom' => '',
            'is_confidential' => '1', 'consent_syndicat' => '0',
            'nature_auteur' => '', 'type_acte' => '',
        ];
        return array_replace($post, $overrides);
    }

    /**
     * @return array{id: int|string, nom: string, prenom: string}
     */
    private function baseUser(array $overrides = []): array
    {
        $user = ['id' => 42, 'nom' => 'Martin', 'prenom' => 'Jean'];
        return array_replace($user, $overrides);
    }

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

    public function testFromPostWithStringUserIdCastsToInt(): void
    {
        $post = $this->basePost();
        $user = $this->baseUser(['id' => '42']);

        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame(42, $cmd->declarantId);
    }

    public function testFromPostWithStringSiteIdCastsToInt(): void
    {
        $post = $this->basePost(['site_id' => '5']);
        $user = $this->baseUser();

        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame(5, $cmd->siteId->toSql());
        $this->assertFalse($cmd->siteId->isNone());
    }

    public function testFromPostWithSiteIdAbsentFallsBackToZero(): void
    {
        $post = $this->basePost();
        unset($post['site_id']);
        $user = $this->baseUser();

        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertTrue($cmd->siteId->isNone());
        $this->assertNull($cmd->siteId->toSql());
    }

    public function testFromPostTrimsPourComptePrenom(): void
    {
        $post = $this->basePost(['pour_compte' => '1', 'pour_compte_prenom' => '  Jean  ']);
        $user = $this->baseUser();

        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame('Jean', $cmd->pourComptePrenom);
    }

    public function testFromPostWithAbsentPourComptePrenomReturnsEmptyString(): void
    {
        $post = $this->basePost(['pour_compte' => '1']);
        unset($post['pour_compte_prenom']);
        $user = $this->baseUser();

        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame('', $cmd->pourComptePrenom);
    }
}