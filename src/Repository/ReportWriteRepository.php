<?php

/** ReportWriteRepository — Écriture CRUD (création/mise à jour) des signalements. */

namespace App\Repository;

use App\DTO\CreateReportCommand;
use App\DTO\SiteId;
use App\DTO\UpdateReportCommand;
use App\Enum\ReportState;
use Exception;
use PDO;
use RuntimeException;

class ReportWriteRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            // Prefer container instance if available (shared lifecycle)
            if (function_exists('getContainer') && getContainer()->has(self::class)) {
                $instance = getContainer()->get(self::class);
            } else {
                $instance = new self(getDB());
            }
        }
        return $instance;
    }

    /**
     * @param array<string, string|int|bool|null> $data  // DTO toArray() — typed primitives
     * @return array<string, string|int|bool|null>
     */
    private function toSnakeCase(array $data): array
    {
        $map = [
            'dateEvenement'       => 'date_evenement',
            'heureEvenement'      => 'heure_evenement',
            'declarantId'         => 'declarant_id',
            'declarantNom'        => 'declarant_nom',
            'declarantPrenom'     => 'declarant_prenom',
            'siteId'              => 'site_id',
            'siteText'            => 'site_text',
            'serviceAffectation'  => 'service_affectation',
            'telephoneMobile'     => 'telephone_mobile',
            'isConfidential'      => 'is_confidential',
            'consentSyndicat'     => 'consent_syndicat',
            'natureAuteur'        => 'nature_auteur',
            'typeActe'            => 'type_acte',
            'pourCompteNom'       => 'pour_compte_nom',
            'pourComptePrenom'    => 'pour_compte_prenom',
            'pourCompteDe'        => 'pour_compte_de',
            'attachmentBlob'      => 'attachment_blob',
            'attachmentName'      => 'attachment_name',
            'attachmentMime'      => 'attachment_mime',
        ];
        $result = [];
        foreach ($data as $key => $value) {
            $result[$map[$key] ?? $key] = $value;
        }
        return $result;
    }

    /** @param array<string, string|null> $customFieldValues */
    public function create(CreateReportCommand $cmd, array $customFieldValues = []): string
    {
        $data = $cmd->toArray();
        unset($data['customFields']);
        $data = $this->toSnakeCase($data);
        $this->pdo->beginTransaction();
        try {
            $year = (int) date('Y');
            $seq = getNextSequence($this->pdo, $cmd->type, $year);
            $reference = generateReference($cmd->type, date('y'), $seq);
            $uuid = generateUuid();

            $stmt = $this->pdo->prepare("
                INSERT INTO reports (
                    uuid, reference, type, objet, description, date_evenement, heure_evenement,
                    lieu, declarant_id, declarant_nom, declarant_prenom,
                    pour_compte_de, pour_compte_nom, pour_compte_prenom,
                    nature_auteur, type_acte, site_id, site_text, pole, service_affectation, telephone_mobile,
                    is_confidential, consent_syndicat, etat,
                    attachment_blob, attachment_name, attachment_mime
                ) VALUES (
                    :uuid, :reference, :type, :objet, :description, :date_evenement, :heure_evenement,
                    :lieu, :declarant_id, :declarant_nom, :declarant_prenom,
                    :pour_compte_de, :pour_compte_nom, :pour_compte_prenom,
                    :nature_auteur, :type_acte, :site_id, :site_text, :pole, :service_affectation, :telephone_mobile,
                    :is_confidential, :consent_syndicat, '" . ReportState::Nouveau->value . "',
                    :attachment_blob, :attachment_name, :attachment_mime
                )
            ");
            $isConfidentialRaw = $data['is_confidential'] ?? null;
            $isConfidential = $isConfidentialRaw !== null ? (int) $isConfidentialRaw : 1;
            $consentSyndicatRaw = $data['consent_syndicat'] ?? null;
            $consentSyndicat = $consentSyndicatRaw !== null ? (int) $consentSyndicatRaw : 0;
            $stmt->execute([
                ':uuid' => $uuid, ':reference' => $reference, ':type' => $data['type'],
                ':objet' => $data['objet'], ':description' => $data['description'],
                ':date_evenement' => $data['date_evenement'], ':heure_evenement' => $data['heure_evenement'] ?? null,
                ':lieu' => $data['lieu'] ?? null, ':declarant_id' => $data['declarant_id'],
                ':declarant_nom' => $data['declarant_nom'], ':declarant_prenom' => $data['declarant_prenom'],
                ':pour_compte_de' => $data['pour_compte_de'] ?? null,
                ':pour_compte_nom' => $data['pour_compte_nom'] ?? null,
                ':pour_compte_prenom' => $data['pour_compte_prenom'] ?? null,
                ':nature_auteur' => $data['nature_auteur'] ?? null, ':type_acte' => $data['type_acte'] ?? null,
                // site_id = 0 is the UI/form sentinel for "no site" (hidden field
                // forced empty in no-site-mode, or the explicit "— Aucun —" option
                // elsewhere) — 0 is never a real site id, and the FOREIGN KEY on
                // site_id rejects it. Must bind NULL (nullable column, see schema.sql).
                ':site_id' => SiteId::fromInput((int) $data['site_id'])->toSql(),
                ':site_text' => $data['site_text'] ?? null,
                ':pole' => $data['pole'] ?? null,
                ':service_affectation' => $data['service_affectation'] ?? null,
                ':telephone_mobile' => $data['telephone_mobile'] ?? null,
                ':is_confidential' => $isConfidential,
                ':consent_syndicat' => $consentSyndicat,
                ':attachment_blob' => $data['attachment_blob'] ?? null,
                ':attachment_name' => $data['attachment_name'] ?? null,
                ':attachment_mime' => $data['attachment_mime'] ?? null,
            ]);

            // reports_fts stays in sync automatically via the AFTER INSERT
            // trigger on reports (see schema.sql) — no manual sync needed
            // here anymore.

            // Champs dynamiques du registre — même transaction que le
            // signalement (atomique) ; [] = aucun champ custom, no-op.
            $this->replaceCustomFieldValues($uuid, $cmd->type, $customFieldValues);

            $this->pdo->commit();
            return $uuid;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[SST-DB] createReport failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /** @param array<string, string|null> $customFieldValues */
    public function update(string $uuid, UpdateReportCommand $cmd, int $userId, ?string $registryCode = null, array $customFieldValues = []): bool
    {
        $data = $cmd->toArray();
        unset($data['customFields']);
        $data = $this->toSnakeCase($data);
        $setClauses = [
            'objet = :objet',
            'description = :description',
            'date_evenement = :date_evenement',
            'heure_evenement = :heure_evenement',
            'lieu = :lieu',
            'pour_compte_nom = :pour_compte_nom',
            'pour_compte_prenom = :pour_compte_prenom',
            'nature_auteur = :nature_auteur',
            'type_acte = :type_acte',
            'is_confidential = :is_confidential',
            'consent_syndicat = :consent_syndicat',
            'pole = :pole',
            'service_affectation = :service_affectation',
            'telephone_mobile = :telephone_mobile',
            'site_text = :site_text',
        ];
        $isConfidentialRaw = $data['is_confidential'] ?? null;
        $isConfidential = $isConfidentialRaw !== null ? (int) $isConfidentialRaw : 1;
        $consentSyndicatRaw = $data['consent_syndicat'] ?? null;
        $consentSyndicat = $consentSyndicatRaw !== null ? (int) $consentSyndicatRaw : 0;
        $params = [
            ':objet'             => $data['objet'],
            ':description'       => $data['description'],
            ':date_evenement'    => $data['date_evenement'],
            ':heure_evenement'   => $data['heure_evenement'] ?? null,
            ':lieu'              => $data['lieu'] ?? null,
            ':pour_compte_nom'   => $data['pour_compte_nom'] ?? null,
            ':pour_compte_prenom' => $data['pour_compte_prenom'] ?? null,
            ':nature_auteur'     => $data['nature_auteur'] ?? null,
            ':type_acte'         => $data['type_acte'] ?? null,
            ':pole'              => $data['pole'] ?? null,
            ':service_affectation' => $data['service_affectation'] ?? null,
            ':telephone_mobile'  => $data['telephone_mobile'] ?? null,
            ':is_confidential'   => $isConfidential,
            ':consent_syndicat'  => $consentSyndicat,
            ':site_text'         => $data['site_text'] ?? null,
        ];
        if ($cmd->removeAttachment || $data['attachment_blob'] !== null) {
            $setClauses[] = 'attachment_blob = :attachment_blob';
            $setClauses[] = 'attachment_name = :attachment_name';
            $setClauses[] = 'attachment_mime = :attachment_mime';
            $params[':attachment_blob'] = $data['attachment_blob'];
            $params[':attachment_name'] = $data['attachment_name'] ?? null;
            $params[':attachment_mime'] = $data['attachment_mime'] ?? null;
        }
        $setClauses[] = "updated_at = datetime('now')";
        $params[':uuid'] = $uuid;
        $params[':user_id'] = $userId;

        $sql = 'UPDATE reports SET ' . implode(', ', $setClauses)
            . " WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN ('" . ReportState::Nouveau->value . "', '" . ReportState::EnCours->value . "')";

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $updated = $stmt->rowCount() > 0;

            // reports_fts stays in sync automatically via the AFTER UPDATE
            // trigger on reports (see schema.sql) — no manual sync needed
            // here anymore.

            // Champs dynamiques du registre — même transaction que l'UPDATE
            // (atomique). $registryCode null = appel historique sans support
            // des champs custom → valeurs existantes intactes (no-op).
            if ($updated && $registryCode !== null) {
                $this->replaceCustomFieldValues($uuid, $registryCode, $customFieldValues);
            }

            $this->pdo->commit();
            return $updated;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[SST-DB] updateReport failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remplace les valeurs des champs dynamiques d'un signalement
     * (registry_field_values) — DELETE puis INSERT, dans la transaction
     * de l'appelant (create/update). Sémantique explicite :
     * - valeur null / '' → ligne absente (non renseigné),
     * - $values [] → no-op (aucune écriture),
     * - $registryCode inconnu → RuntimeException (crash hard, règle projet).
     *
     * La FK composite (registry_id, field_code) → registry_fields rejette
     * au niveau base tout code sans définition : la couche service
     * (CustomFieldsService::filterPersistable) garantit en amont que seuls
     * les codes dynamiques définis atteignent cette méthode.
     *
     * @param array<string, string|null> $values
     */
    private function replaceCustomFieldValues(string $uuid, string $registryCode, array $values): void
    {
        if ($values === []) {
            return;
        }

        $ridStmt = $this->pdo->prepare('SELECT id FROM registries WHERE code = :code');
        $ridStmt->execute([':code' => $registryCode]);
        $registryId = $ridStmt->fetchColumn();
        if ($registryId === false) {
            throw new RuntimeException('Registre inconnu pour la persistance des champs dynamiques : ' . $registryCode);
        }

        $delete = $this->pdo->prepare('DELETE FROM registry_field_values WHERE report_uuid = :uuid AND registry_id = :rid');
        $delete->execute([':uuid' => $uuid, ':rid' => (int) $registryId]);

        $insert = $this->pdo->prepare(
            'INSERT INTO registry_field_values (report_uuid, registry_id, field_code, value)
             VALUES (:uuid, :rid, :code, :value)'
        );
        foreach ($values as $fieldCode => $value) {
            if ($value === null || $value === '') {
                continue; // non renseigné = explicitement absent
            }
            $insert->execute([
                ':uuid' => $uuid,
                ':rid'  => (int) $registryId,
                ':code' => (string) $fieldCode,
                ':value' => $value,
            ]);
        }
    }
}
