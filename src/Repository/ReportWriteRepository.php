<?php

/** ReportWriteRepository — Écriture CRUD (création/mise à jour) des signalements. */

namespace App\Repository;

use App\DTO\CreateReportCommand;
use App\DTO\SiteId;
use App\DTO\UpdateReportCommand;
use App\Enum\ReportState;
use Exception;
use PDO;

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

    public function create(CreateReportCommand $cmd): string
    {
        $data = $this->toSnakeCase($cmd->toArray());
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

            $this->pdo->commit();
            return $uuid;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[SST-DB] createReport failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update(string $uuid, UpdateReportCommand $cmd, int $userId): bool
    {
        $data = $this->toSnakeCase($cmd->toArray());
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

            $this->pdo->commit();
            return $updated;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[SST-DB] updateReport failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
