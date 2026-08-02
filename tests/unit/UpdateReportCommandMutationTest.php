<?php
/**
 * Tests UpdateReportCommand exhaustively — kills Infection mutants on:
 *   - UnwrapTrim on every trim() (lines 41-53)
 *   - Coalesce on every ?? '' / ?? null
 *   - Ternary on ?? null patterns
 *   - LogicalAnd / Identical on isset/=== combos
 *   - toArray() ArrayItem mutants
 *   - toArray() attachment handling (removeAttachment flag)
 */

use PHPUnit\Framework\TestCase;
use App\DTO\UpdateReportCommand;

class UpdateReportCommandMutationTest extends TestCase
{
    /** @return array<string, string> */
    private function basePost(): array
    {
        return [
            'objet' => 'Obj', 'description' => 'Desc',
            'date_evenement' => '2026-01-15', 'heure_evenement' => '10:30',
            'lieu' => 'Bureau', 'site_text' => 'UR21', 'pole' => 'P1',
            'service_affectation' => 'S1', 'telephone_mobile' => '0601',
            'is_confidential' => '0', 'consent_syndicat' => '0',
            'nature_auteur' => 'usager', 'type_acte' => 'verbal',
            'pour_compte' => '0', 'pour_compte_nom' => '', 'pour_compte_prenom' => '',
        ];
    }

    public function testTrimAppliedToAllStringFields(): void
    {
        $post = $this->basePost();
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
        $post['pour_compte_nom'] = "  Durant  ";
        $post['pour_compte_prenom'] = "  Pierre  ";
        $post['pour_compte'] = '1';

        $cmd = UpdateReportCommand::fromPost($post);

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
        $this->assertSame('Durant', $cmd->pourCompteNom);
        $this->assertSame('Pierre', $cmd->pourComptePrenom);
    }

    public function testMissingKeysDefaultToEmptyString(): void
    {
        $cmd = UpdateReportCommand::fromPost([]);
        $this->assertSame('', $cmd->objet);
        $this->assertSame('', $cmd->description);
        $this->assertSame('', $cmd->dateEvenement);
        $this->assertSame('', $cmd->lieu);
        $this->assertSame('', $cmd->siteText);
        $this->assertSame('', $cmd->pole);
        $this->assertSame('', $cmd->serviceAffectation);
        $this->assertSame('', $cmd->telephoneMobile);
    }

    public function testNullableFieldsDefaultToNullWhenEmpty(): void
    {
        $post = $this->basePost();
        $post['nature_auteur'] = '';
        $post['type_acte'] = '';
        $post['pour_compte'] = '0';
        $post['heure_evenement'] = null;

        $cmd = UpdateReportCommand::fromPost($post);
        $this->assertNull($cmd->natureAuteur);
        $this->assertNull($cmd->typeActe);
        $this->assertNull($cmd->pourCompteNom);
        $this->assertNull($cmd->pourComptePrenom);
        $this->assertNull($cmd->heureEvenement);
    }

    public function testHeureEvenementPreservedWhenSet(): void
    {
        $post = $this->basePost();
        $post['heure_evenement'] = '14:45';
        $this->assertSame('14:45', UpdateReportCommand::fromPost($post)->heureEvenement);
    }

    public function testPourCompteFlagControlsNomPrenomPopulation(): void
    {
        // When pour_compte='1', nom/prenom are populated
        $post = $this->basePost();
        $post['pour_compte'] = '1';
        $post['pour_compte_nom'] = 'Durant';
        $post['pour_compte_prenom'] = 'Pierre';
        $cmd = UpdateReportCommand::fromPost($post);
        $this->assertSame('Durant', $cmd->pourCompteNom);
        $this->assertSame('Pierre', $cmd->pourComptePrenom);

        // When pour_compte='0', nom/prenom are null even if provided
        $post = $this->basePost();
        $post['pour_compte'] = '0';
        $post['pour_compte_nom'] = 'Durant';
        $post['pour_compte_prenom'] = 'Pierre';
        $cmd = UpdateReportCommand::fromPost($post);
        $this->assertNull($cmd->pourCompteNom);
        $this->assertNull($cmd->pourComptePrenom);

        // When pour_compte missing, nom/prenom are null
        $post = $this->basePost();
        unset($post['pour_compte']);
        $post['pour_compte_nom'] = 'Durant';
        $cmd = UpdateReportCommand::fromPost($post);
        $this->assertNull($cmd->pourCompteNom);
    }

    public function testBooleanFlagsTruthTable(): void
    {
        foreach (['is_confidential' => 'isConfidential', 'consent_syndicat' => 'consentSyndicat', 'remove_attachment' => 'removeAttachment'] as $postKey => $prop) {
            $post = $this->basePost();
            $post[$postKey] = '1';
            $this->assertTrue(UpdateReportCommand::fromPost($post)->$prop, "$postKey='1' → true");

            $post = $this->basePost();
            $post[$postKey] = '0';
            $this->assertFalse(UpdateReportCommand::fromPost($post)->$prop, "$postKey='0' → false");

            $post = $this->basePost();
            $post[$postKey] = '';
            $this->assertFalse(UpdateReportCommand::fromPost($post)->$prop, "$postKey='' → false");

            $post = $this->basePost();
            unset($post[$postKey]);
            $this->assertFalse(UpdateReportCommand::fromPost($post)->$prop, "$postKey missing → false");
        }
    }

    /**
     * Kill toArray() ArrayItem mutants — every field must be in the array.
     * Also kills Ternary mutants on attachment handling.
     */
    public function testToArrayContainsAllFields(): void
    {
        $cmd = UpdateReportCommand::fromPost($this->basePost());
        $arr = $cmd->toArray();
        foreach (['objet', 'description', 'dateEvenement', 'heureEvenement', 'lieu', 'siteText', 'pole', 'serviceAffectation', 'telephoneMobile', 'isConfidential', 'consentSyndicat', 'natureAuteur', 'typeActe', 'pourCompteNom', 'pourComptePrenom', 'attachmentBlob', 'attachmentName', 'attachmentMime', 'removeAttachment'] as $key) {
            $this->assertArrayHasKey($key, $arr, "toArray() must include $key");
        }
    }

    /**
     * Kill toArray() Ternary mutants on $removeAttachment — when true,
     * attachment fields must be in array as null (NOT unset).
     * This is the exact bug #4-High — the test prevents regression.
     */
    public function testToArrayWithRemoveAttachmentExplicitlyNullsAttachmentFields(): void
    {
        $cmd = new UpdateReportCommand(
            objet: 'o', description: 'd', dateEvenement: '2026-01-01',
            heureEvenement: null, lieu: null, siteText: null, pole: null,
            serviceAffectation: null, telephoneMobile: null,
            isConfidential: false, consentSyndicat: false,
            natureAuteur: null, typeActe: null,
            pourCompteNom: null, pourComptePrenom: null,
            attachmentBlob: 'existing-blob',
            attachmentName: 'existing-name',
            attachmentMime: 'image/png',
            removeAttachment: true,
        );
        $arr = $cmd->toArray();

        $this->assertNull($arr['attachmentBlob'], 'removeAttachment=true must null the blob');
        $this->assertNull($arr['attachmentName'], 'removeAttachment=true must null the name');
        $this->assertNull($arr['attachmentMime'], 'removeAttachment=true must null the mime');
        $this->assertTrue($arr['removeAttachment']);
    }

    /**
     * When removeAttachment=false AND attachment fields are null (no new upload),
     * toArray() still includes them as null — they won't be unset.
     * (Existing behavior, but the test kills any UnwrapTernary/Half-Removal mutant.)
     */
    public function testToArrayWithoutAttachmentKeepsNullFields(): void
    {
        $cmd = new UpdateReportCommand(
            objet: 'o', description: 'd', dateEvenement: '2026-01-01',
            heureEvenement: null, lieu: null, siteText: null, pole: null,
            serviceAffectation: null, telephoneMobile: null,
            isConfidential: false, consentSyndicat: false,
        );
        $arr = $cmd->toArray();
        $this->assertArrayHasKey('attachmentBlob', $arr);
        $this->assertNull($arr['attachmentBlob']);
    }

    /**
     * When a new attachment is uploaded (blob/name/mime set), toArray() must preserve them.
     */
    public function testToArrayPreservesAttachmentWhenSetAndNotRemoving(): void
    {
        $cmd = new UpdateReportCommand(
            objet: 'o', description: 'd', dateEvenement: '2026-01-01',
            heureEvenement: null, lieu: null, siteText: null, pole: null,
            serviceAffectation: null, telephoneMobile: null,
            isConfidential: false, consentSyndicat: false,
            attachmentBlob: 'new-blob', attachmentName: 'new.png', attachmentMime: 'image/png',
        );
        $arr = $cmd->toArray();
        $this->assertSame('new-blob', $arr['attachmentBlob']);
        $this->assertSame('new.png', $arr['attachmentName']);
        $this->assertSame('image/png', $arr['attachmentMime']);
        $this->assertFalse($arr['removeAttachment']);
    }
}
