<?php
/**
 * Tests ReportData::toArray() exhaustively — kills Infection ArrayItem mutants.
 *
 * Each line in toArray() is a separate mutant for Infection (ArrayItem removal).
 * The test asserts every key + every value, so removing any line breaks an assertion.
 */

use PHPUnit\Framework\TestCase;
use App\DTO\ReportData;

class ReportDataToArrayMutationTest extends TestCase
{
    private function sample(): ReportData
    {
        return new ReportData(
            uuid: 'uuid-1',
            reference: 'rsst-25-001',
            type: 'rsst',
            objet: 'Objet test',
            description: 'Description test',
            dateEvenement: '2026-01-15',
            heureEvenement: '10:30',
            lieu: 'Bureau',
            declarantId: 42,
            declarantNom: 'Dupont',
            declarantPrenom: 'Jean',
            pourCompteDe: '5',
            pourCompteNom: 'Durant',
            pourComptePrenom: 'Pierre',
            natureAuteur: 'usager',
            typeActe: 'verbal',
            siteId: 3,
            siteText: 'UR21',
            pole: 'P1',
            serviceAffectation: 'S1',
            telephoneMobile: '0601',
            isConfidential: 1,
            consentSyndicat: 0,
            etat: 'nouveau',
            repondantId: 99,
            dateReponse: '2026-01-16',
            reponse: 'Réponse test',
            attachmentName: 'file.png',
            attachmentMime: 'image/png',
            createdAt: '2026-01-10 12:00:00',
            updatedAt: '2026-01-12 14:30:00',
            siteCode: 'UR21',
            siteNom: 'UR Test',
            repondantNom: 'Martin',
            repondantPrenom: 'Sophie',
        );
    }

    public function testToArrayContainsAllKeysWithCorrectValues(): void
    {
        $arr = $this->sample()->toArray();

        // String fields — kill ArrayItem mutants by asserting exact value AND presence
        $this->assertSame('uuid-1', $arr['uuid']);
        $this->assertSame('rsst-25-001', $arr['reference']);
        $this->assertSame('rsst', $arr['type']);
        $this->assertSame('Objet test', $arr['objet']);
        $this->assertSame('Description test', $arr['description']);
        $this->assertSame('2026-01-15', $arr['date_evenement']);
        $this->assertSame('10:30', $arr['heure_evenement']);
        $this->assertSame('Bureau', $arr['lieu']);
        $this->assertSame('Dupont', $arr['declarant_nom']);
        $this->assertSame('Jean', $arr['declarant_prenom']);
        $this->assertSame('5', $arr['pour_compte_de']);
        $this->assertSame('Durant', $arr['pour_compte_nom']);
        $this->assertSame('Pierre', $arr['pour_compte_prenom']);
        $this->assertSame('usager', $arr['nature_auteur']);
        $this->assertSame('verbal', $arr['type_acte']);
        $this->assertSame('UR21', $arr['site_text']);
        $this->assertSame('P1', $arr['pole']);
        $this->assertSame('S1', $arr['service_affectation']);
        $this->assertSame('0601', $arr['telephone_mobile']);
        $this->assertSame('nouveau', $arr['etat']);
        $this->assertSame('file.png', $arr['attachment_name']);
        $this->assertSame('image/png', $arr['attachment_mime']);
        $this->assertSame('2026-01-10 12:00:00', $arr['created_at']);
        $this->assertSame('2026-01-12 14:30:00', $arr['updated_at']);
        $this->assertSame('UR21', $arr['site_code']);
        $this->assertSame('UR Test', $arr['site_nom']);
        $this->assertSame('Martin', $arr['repondant_nom']);
        $this->assertSame('Sophie', $arr['repondant_prenom']);

        // Int fields — kill ArrayItem + CastInt mutants
        $this->assertSame(42, $arr['declarant_id']);
        $this->assertSame(3, $arr['site_id']);
        $this->assertSame(1, $arr['is_confidential']);
        $this->assertSame(0, $arr['consent_syndicat']);
        $this->assertSame(99, $arr['repondant_id']);

        // Nullable fields — kill ArrayItem mutants
        $this->assertSame('2026-01-16', $arr['date_reponse']);
        $this->assertSame('Réponse test', $arr['reponse']);
    }

    public function testToArrayKeyCountExact(): void
    {
        // Total keys = 34 (one per property). Removing any key breaks this count.
        $arr = $this->sample()->toArray();
        $this->assertCount(34, $arr, 'toArray() must return exactly 34 keys (one per ReportData property)');
    }

    public function testToArrayWithAllNullablesNull(): void
    {
        $d = new ReportData(
            uuid: 'uuid-nulls', reference: 'ref', type: 'rsst', objet: 'o', description: 'd',
            dateEvenement: '2026-01-01', heureEvenement: '', lieu: '',
            declarantId: 1, declarantNom: 'A', declarantPrenom: 'B',
            pourCompteDe: '', pourCompteNom: '', pourComptePrenom: '',
            natureAuteur: '', typeActe: '',
            siteId: null, siteText: '', pole: '', serviceAffectation: '', telephoneMobile: '',
            isConfidential: 0, consentSyndicat: 0, etat: 'nouveau',
            repondantId: null, dateReponse: null, reponse: null,
            attachmentName: null, attachmentMime: null,
            createdAt: '2026-01-01', updatedAt: '2026-01-01',
            siteCode: '', siteNom: '',
            repondantNom: null, repondantPrenom: null,
        );
        $arr = $d->toArray();
        // Kill mutants on nullable fields — they must be present as null, not absent.
        $this->assertNull($arr['site_id']);
        $this->assertNull($arr['repondant_id']);
        $this->assertNull($arr['date_reponse']);
        $this->assertNull($arr['reponse']);
        $this->assertNull($arr['attachment_name']);
        $this->assertNull($arr['attachment_mime']);
        $this->assertNull($arr['repondant_nom']);
        $this->assertNull($arr['repondant_prenom']);
        $this->assertCount(34, $arr, 'Even with all nulls, key count must be 34');
    }
}
