<?php
/**
 * Tests CreateReportCommand exhaustively — kills Infection mutants on:
 *   - Identical / LogicalAnd / LogicalAndNegation on $pourCompte check (line 55)
 *   - UnwrapTrim / Coalesce on every trim()/?? '' (lines 58-94)
 *   - CastInt on (int) casts
 *   - Ternary on ?? null patterns
 *
 * Strategy : test every branch with explicit assertions on the exact value
 * AND its type — mutants like CastInt escape only if the test asserts === int.
 */

use PHPUnit\Framework\TestCase;
use App\DTO\CreateReportCommand;
use App\Enum\ReportType;

class CreateReportCommandMutationTest extends TestCase
{
    /** @return array<string, string> */
    private function basePost(): array
    {
        return [
            'type' => 'rsst', 'objet' => 'Obj', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_id' => '1', 'site_text' => 'UR21',
            'pole' => 'P1', 'service_affectation' => 'S1', 'telephone_mobile' => '0601',
            'pour_compte' => '0', 'pour_compte_nom' => '', 'pour_compte_prenom' => '',
            'is_confidential' => '0', 'consent_syndicat' => '0',
            'nature_auteur' => '', 'type_acte' => '',
        ];
    }

    /**
     * Kill mutants on line 55 : isset() && === '1' logical combos.
     * Tests: '1' → true, '0' → false, missing → false, '' → false.
     */
    public function testPourCompteFlagTruthTable(): void
    {
        $user = ['id' => 1, 'nom' => 'A', 'prenom' => 'B'];

        $post = $this->basePost();
        $post['pour_compte'] = '1';
        $this->assertTrue(CreateReportCommand::fromPost($post, $user)->pourCompteNom !== null);

        $post = $this->basePost();
        $post['pour_compte'] = '0';
        $this->assertNull(CreateReportCommand::fromPost($post, $user)->pourCompteNom);

        $post = $this->basePost();
        unset($post['pour_compte']);
        $this->assertNull(CreateReportCommand::fromPost($post, $user)->pourCompteNom);

        $post = $this->basePost();
        $post['pour_compte'] = '';
        $this->assertNull(CreateReportCommand::fromPost($post, $user)->pourCompteNom);

        $post = $this->basePost();
        $post['pour_compte'] = 'yes';
        $this->assertNull(CreateReportCommand::fromPost($post, $user)->pourCompteNom);
    }

    /**
     * Kill UnwrapTrim mutants — without trim(), whitespace would leak through.
     */
    public function testTrimAppliedToAllStringFields(): void
    {
        $user = ['id' => 7, 'nom' => 'Martin', 'prenom' => 'Jean'];
        $post = $this->basePost();
        $post['type'] = '  rsst  ';
        $post['objet'] = "  Obj with spaces  ";
        $post['description'] = "  Desc  ";
        $post['date_evenement'] = "  2026-01-15  ";
        $post['lieu'] = "  Bureau  ";
        $post['site_text'] = "  UR21  ";
        $post['pole'] = "  P1  ";
        $post['service_affectation'] = "  S1  ";
        $post['telephone_mobile'] = "  0601  ";
        $post['nature_auteur'] = "  usager  ";
        $post['type_acte'] = "  verbal  ";

        $cmd = CreateReportCommand::fromPost($post, $user);

        $this->assertSame('rsst', $cmd->type, 'type should be trimmed');
        $this->assertSame('Obj with spaces', $cmd->objet);
        $this->assertSame('Desc', $cmd->description);
        $this->assertSame('2026-01-15', $cmd->dateEvenement);
        $this->assertSame('Bureau', $cmd->lieu);
        $this->assertSame('UR21', $cmd->siteText);
        $this->assertSame('P1', $cmd->pole);
        $this->assertSame('S1', $cmd->serviceAffectation);
        $this->assertSame('0601', $cmd->telephoneMobile);
        $this->assertSame('usager', $cmd->natureAuteur);
        $this->assertSame('verbal', $cmd->typeActe);
    }

    /**
     * Kill Coalesce mutants — when key missing, must default to '' not null.
     */
    public function testMissingKeysDefaultToEmptyString(): void
    {
        $user = ['id' => 1, 'nom' => 'A', 'prenom' => 'B'];
        $post = ['type' => 'rsst']; // minimal — everything else missing

        $cmd = CreateReportCommand::fromPost($post, $user);

        $this->assertSame('', $cmd->objet);
        $this->assertSame('', $cmd->description);
        $this->assertSame('', $cmd->dateEvenement);
        $this->assertSame('', $cmd->lieu);
        $this->assertSame('', $cmd->siteText);
        $this->assertSame('', $cmd->pole);
        $this->assertSame('', $cmd->serviceAffectation);
        $this->assertSame('', $cmd->telephoneMobile);
    }

    /**
     * Kill CastInt mutants — (int) cast must be applied.
     */
    public function testCastsAreApplied(): void
    {
        $user = ['id' => '42', 'nom' => 'A', 'prenom' => 'B']; // string id
        $post = $this->basePost();
        $post['site_id'] = '99'; // string site_id

        $cmd = CreateReportCommand::fromPost($post, $user);

        $this->assertSame(42, $cmd->declarantId, 'declarantId must be (int)');
        $this->assertSame(99, $cmd->siteId, 'siteId must be (int)');
        $this->assertIsInt($cmd->declarantId);
        $this->assertIsInt($cmd->siteId);
    }

    /**
     * Kill Ternary mutants on ?? null patterns.
     */
    public function testNullableFieldsDefaultToNullWhenEmpty(): void
    {
        $user = ['id' => 1, 'nom' => 'A', 'prenom' => 'B'];
        $post = $this->basePost();
        // RAMI fields empty → null
        $post['type'] = 'rsst'; // not RAMI → natureAuteur/typeActe stay null
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertNull($cmd->natureAuteur);
        $this->assertNull($cmd->typeActe);
        $this->assertNull($cmd->pourCompteNom);
        $this->assertNull($cmd->pourComptePrenom);
        $this->assertNull($cmd->heureEvenement, 'heureEvenement missing → null');
    }

    /**
     * Kill Ternary mutants on pourCompte fields when pourCompte='1'.
     */
    public function testPourCompteFieldsPopulatedWhenFlagSet(): void
    {
        $user = ['id' => 1, 'nom' => 'A', 'prenom' => 'B'];
        $post = $this->basePost();
        $post['pour_compte'] = '1';
        $post['pour_compte_nom'] = 'Durant';
        $post['pour_compte_prenom'] = 'Pierre';
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame('Durant', $cmd->pourCompteNom);
        $this->assertSame('Pierre', $cmd->pourComptePrenom);
    }

    /**
     * Kill Ternary mutants on heureEvenement — non-empty value preserved.
     */
    public function testHeureEvenementPreservedWhenSet(): void
    {
        $user = ['id' => 1, 'nom' => 'A', 'prenom' => 'B'];
        $post = $this->basePost();
        $post['heure_evenement'] = '14:45';
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame('14:45', $cmd->heureEvenement);
    }

    /**
     * Kill isConfidential / consentSyndicat mutants.
     */
    public function testBooleanFlagsTruthTable(): void
    {
        $user = ['id' => 1, 'nom' => 'A', 'prenom' => 'B'];

        foreach (['is_confidential' => 'isConfidential', 'consent_syndicat' => 'consentSyndicat'] as $postKey => $prop) {
            $post = $this->basePost();
            $post[$postKey] = '1';
            $this->assertTrue(CreateReportCommand::fromPost($post, $user)->$prop, "$postKey='1' → true");

            $post = $this->basePost();
            $post[$postKey] = '0';
            $this->assertFalse(CreateReportCommand::fromPost($post, $user)->$prop, "$postKey='0' → false");

            $post = $this->basePost();
            $post[$postKey] = '';
            $this->assertFalse(CreateReportCommand::fromPost($post, $user)->$prop, "$postKey='' → false");

            $post = $this->basePost();
            unset($post[$postKey]);
            $this->assertFalse(CreateReportCommand::fromPost($post, $user)->$prop, "$postKey missing → false");
        }
    }

    /**
     * Kill mutants on user array access — declarantNom/Prenom/Id come from $user.
     */
    public function testUserDataPropagatedFromUserArray(): void
    {
        $post = $this->basePost();
        $user = ['id' => 123, 'nom' => 'Dupont', 'prenom' => 'Sophie', 'email' => 's@gouv.fr'];
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame(123, $cmd->declarantId);
        $this->assertSame('Dupont', $cmd->declarantNom);
        $this->assertSame('Sophie', $cmd->declarantPrenom);
    }

    /**
     * Kill mutants on user array missing keys — fallback to '' and 0.
     */
    public function testUserDataFallsBackWhenMissing(): void
    {
        $post = $this->basePost();
        $user = []; // empty user
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame(0, $cmd->declarantId, 'missing user.id → 0');
        $this->assertSame('', $cmd->declarantNom, 'missing user.nom → empty string');
        $this->assertSame('', $cmd->declarantPrenom, 'missing user.prenom → empty string');
    }

    /**
     * Kill mutants on RAMI-specific natureAuteur / typeActe validation.
     */
    public function testRamiFieldsPopulatedWhenTypeIsRami(): void
    {
        $user = ['id' => 1, 'nom' => 'A', 'prenom' => 'B'];
        $post = $this->basePost();
        $post['type'] = 'rami';
        $post['nature_auteur'] = 'usager';
        $post['type_acte'] = 'verbal';
        $cmd = CreateReportCommand::fromPost($post, $user);
        $this->assertSame('usager', $cmd->natureAuteur);
        $this->assertSame('verbal', $cmd->typeActe);
    }

    /**
     * Kill mutants on attachment defaults — always null on fromPost (set later by handler).
     */
    public function testAttachmentDefaultsToNull(): void
    {
        $user = ['id' => 1, 'nom' => 'A', 'prenom' => 'B'];
        $cmd = CreateReportCommand::fromPost($this->basePost(), $user);
        $this->assertNull($cmd->attachmentBlob);
        $this->assertNull($cmd->attachmentName);
        $this->assertNull($cmd->attachmentMime);
    }

    /**
     * Kill toArray() ArrayItem mutants — every field must be in the array.
     */
    public function testToArrayContainsAllFields(): void
    {
        $user = ['id' => 5, 'nom' => 'Test', 'prenom' => 'User'];
        $cmd = CreateReportCommand::fromPost($this->basePost(), $user);
        $arr = $cmd->toArray();
        $this->assertArrayHasKey('type', $arr);
        $this->assertArrayHasKey('objet', $arr);
        $this->assertArrayHasKey('description', $arr);
        $this->assertArrayHasKey('dateEvenement', $arr);
        $this->assertArrayHasKey('heureEvenement', $arr);
        $this->assertArrayHasKey('lieu', $arr);
        $this->assertArrayHasKey('declarantId', $arr);
        $this->assertArrayHasKey('declarantNom', $arr);
        $this->assertArrayHasKey('declarantPrenom', $arr);
        $this->assertArrayHasKey('siteId', $arr);
        $this->assertArrayHasKey('siteText', $arr);
        $this->assertArrayHasKey('pole', $arr);
        $this->assertArrayHasKey('serviceAffectation', $arr);
        $this->assertArrayHasKey('telephoneMobile', $arr);
        $this->assertArrayHasKey('isConfidential', $arr);
        $this->assertArrayHasKey('consentSyndicat', $arr);
        $this->assertArrayHasKey('natureAuteur', $arr);
        $this->assertArrayHasKey('typeActe', $arr);
        $this->assertArrayHasKey('pourCompteNom', $arr);
        $this->assertArrayHasKey('pourComptePrenom', $arr);
        $this->assertArrayHasKey('attachmentBlob', $arr);
        $this->assertArrayHasKey('attachmentName', $arr);
        $this->assertArrayHasKey('attachmentMime', $arr);
    }
}
