<?php
/** ReportRepository — Couche d'accès aux données pour les signalements. */

namespace App\Repository;

use Exception;
use App\DTO\CreateReportCommand;
use App\DTO\ReportFilter;
use App\DTO\UpdateReportCommand;
use App\DTO\RespondToReportCommand;
use App\Query\QueryFilterBuilder;
use PDO;

class ReportRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self(getDB());
        }
        return $instance;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════════════

    private function baseSelect(): string
    {
        return 'SELECT r.uuid, r.reference, r.type, r.objet, r.description,
                r.date_evenement, r.heure_evenement, r.lieu,
                r.declarant_id, r.declarant_nom, r.declarant_prenom,
                r.pour_compte_de, r.pour_compte_nom, r.pour_compte_prenom,
                r.nature_auteur, r.type_acte,
                r.site_id, r.site_text, r.pole, r.service_affectation, r.telephone_mobile,
                r.is_confidential, r.consent_syndicat, r.etat,
                r.repondant_id, r.date_reponse, r.reponse,
                r.attachment_name, r.attachment_mime,
                r.created_at, r.updated_at,
                s.code as site_code, s.nom as site_nom
            FROM reports r LEFT JOIN sites s ON r.site_id = s.id';
    }

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

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Reports
    // ═══════════════════════════════════════════════════════════════════════════════

    public function findById(string $uuid): ?array
    {
        if (!isValidUuid($uuid)) {
            return null;
        }
        $stmt = $this->pdo->prepare('
            SELECT r.*, s.code as site_code, s.nom as site_nom,
                   rep.nom as repondant_nom, rep.prenom as repondant_prenom
            FROM reports r
            LEFT JOIN sites s ON r.site_id = s.id
            LEFT JOIN users rep ON r.repondant_id = rep.id
            WHERE r.uuid = :uuid
        ');
        $stmt->execute([':uuid' => $uuid]);
        return $stmt->fetch() ?: null;
    }

    public function findPaginated(ReportFilter $filter, int $page = 1, int $perPage = 20): array
    {
        $builder = new QueryFilterBuilder();
        $builder->addEqual('r.type', $filter->type);
        if (!$filter->seeAllSites) { $builder->addEqual('r.site_id', $filter->forceSiteId ?? 0); }
        $filters = $filter->toArray();
        if (!empty($filters['confidential_filter'])) {
            $cfId = (int) $filters['confidential_filter'];
            $builder->addRaw('(r.is_confidential = 0 OR r.declarant_id = :cf_declarant_id)', [':cf_declarant_id' => $cfId]);
        }
        if (!empty($filters['own_only'])) { $builder->addEqual('r.declarant_id', $filters['own_only']); }
        if (!empty($filters['etat'])) { $builder->addEqual('r.etat', $filters['etat']); }
        if (!empty($filters['site_id']) && $filter->seeAllSites) { $builder->addEqual('r.site_id', $filters['site_id']); }
        if (!empty($filters['force_site_id'])) { $builder->addEqual('r.site_id', (int) $filters['force_site_id']); }
        if (!empty($filters['declarant_id']) && empty($filters['confidential_filter'])) { $builder->addEqual('r.declarant_id', $filters['declarant_id']); }

        ['where' => $where, 'params' => $params] = $builder->build();

        if (!empty($filters['q'])) {
            static $hasFts = null;
            if ($hasFts === null) {
                try {
                    $c = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='reports_fts'");
                    $hasFts = ($c !== false && $c->fetch() !== false);
                } catch (Exception) {
                    $hasFts = false;
                }
            }
            if ($hasFts) {
                $where .= ' AND r.uuid IN (SELECT uuid FROM reports_fts WHERE reports_fts MATCH :q_fts)';
                $ftsQuery = trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', (string) $filters['q']));
                if ($ftsQuery === '') {
                    $where = str_replace('AND r.uuid IN (SELECT uuid FROM reports_fts WHERE reports_fts MATCH :q_fts)', 'AND (r.objet LIKE :q OR r.description LIKE :q2)', $where);
                    $params[':q'] = $params[':q2'] = '%' . $filters['q'] . '%';
                } else {
                    $params[':q_fts'] = $ftsQuery;
                }
            } else {
                $where .= ' AND (r.objet LIKE :q OR r.description LIKE :q2)';
                $params[':q'] = $params[':q2'] = '%' . $filters['q'] . '%';
            }
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports r WHERE $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $params[':limit'] = $perPage;
        $params[':offset'] = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare($this->baseSelect() . " WHERE $where ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->execute($params);
        return ['reports' => $stmt->fetchAll(), 'total' => $total];
    }

    public function findBySite(int $siteId): array
    {
        $stmt = $this->pdo->prepare($this->baseSelect() . ' WHERE r.site_id = :site_id ORDER BY r.created_at DESC');
        $stmt->execute([':site_id' => $siteId]);
        return $stmt->fetchAll();
    }

    public function getAdjacentUuids(array $report): array
    {
        $type = $report['type'] ?? 'rsst';
        $createdAt = $report['created_at'] ?? '';
        $uuid = $report['uuid'] ?? '';
        $result = ['prev' => null, 'next' => null];

        $stmt = $this->pdo->prepare('
            SELECT uuid, created_at FROM reports
            WHERE type = :type AND (created_at < :created_at OR (created_at = :created_at AND uuid < :uuid))
            ORDER BY created_at DESC, uuid DESC LIMIT 1
        ');
        $stmt->execute([':type' => $type, ':created_at' => $createdAt, ':uuid' => $uuid]);
        $prev = $stmt->fetch();
        if ($prev) { $result['prev'] = $prev['uuid']; }

        $stmt2 = $this->pdo->prepare('
            SELECT uuid, created_at FROM reports
            WHERE type = :type AND (created_at > :created_at OR (created_at = :created_at AND uuid > :uuid))
            ORDER BY created_at ASC, uuid ASC LIMIT 1
        ');
        $stmt2->execute([':type' => $type, ':created_at' => $createdAt, ':uuid' => $uuid]);
        $next = $stmt2->fetch();
        if ($next) { $result['next'] = $next['uuid']; }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Counts
    // ═══════════════════════════════════════════════════════════════════════════════

    public function countByState(string $type, int $siteId = 0, bool $seeAllSites = true): array
    {
        $sql = "SELECT etat, COUNT(*) as count FROM reports WHERE type = :type AND etat != '" . ETAT_ABANDONNE . "'";
        $params = [':type' => $type];

        if (!$seeAllSites && $siteId > 0) {
            $sql .= ' AND site_id = :site_id';
            $params[':site_id'] = $siteId;
        }
        $sql .= ' GROUP BY etat';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $counts = [
            ETAT_NOUVEAU   => 0,
            ETAT_EN_COURS  => 0,
            ETAT_TRAITE    => 0,
            ETAT_REOUVERT  => 0,
            'total'        => 0,
        ];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['etat']] = (int) $row['count'];
            $counts['total'] += (int) $row['count'];
        }
        return $counts;
    }

    public function countActive(string $type, int $siteId = 0, int $userId = 0, bool $confidentialMode = false): int
    {
        $sql = "SELECT COUNT(*) FROM reports WHERE type = :type AND etat != '" . ETAT_ABANDONNE . "'";
        $params = [':type' => $type];

        if ($siteId > 0) {
            $sql .= ' AND site_id = :site_id';
            $params[':site_id'] = $siteId;
        }
        if ($confidentialMode && $userId > 0) {
            $sql .= ' AND (is_confidential = 0 OR declarant_id = :user_id)';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countActiveForUser(string $type, int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE type = :type AND etat != '" . ETAT_ABANDONNE . "' AND declarant_id = :user_id");
        $stmt->execute([':type' => $type, ':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Responses
    // ═══════════════════════════════════════════════════════════════════════════════

    public function getResponses(string $reportUuid): array
    {
        $stmt = $this->pdo->prepare('
            SELECT rr.*, u.nom, u.prenom
            FROM report_responses rr
            LEFT JOIN users u ON rr.user_id = u.id
            WHERE rr.report_uuid = :report_uuid
            ORDER BY rr.created_at ASC
        ');
        $stmt->execute([':report_uuid' => $reportUuid]);
        return $stmt->fetchAll();
    }

    /**
     * @param list<string> $uuids
     * @return array<string, list<array<string, mixed>>>
     */
    public function getResponsesForUuids(array $uuids): array
    {
        if (empty($uuids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($uuids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT rr.*, rr.report_uuid, u.nom, u.prenom
            FROM report_responses rr
            LEFT JOIN users u ON rr.user_id = u.id
            WHERE rr.report_uuid IN ($placeholders)
            ORDER BY rr.created_at ASC
        ");
        $stmt->execute($uuids);
        $result = [];
        while ($resp = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $uuid = (string) $resp['report_uuid'];
            $result[$uuid][] = $resp;
        }
        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Linked agents
    // ═══════════════════════════════════════════════════════════════════════════════

    public function getLinkedAgents(string $reportUuid): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.nom, u.prenom, u.email
            FROM report_agents ra
            JOIN users u ON u.id = ra.user_id
            WHERE ra.report_uuid = ?
            ORDER BY u.nom, u.prenom
        ');
        $stmt->execute([$reportUuid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingInvites(string $reportUuid): array
    {
        $stmt = $this->pdo->prepare('
            SELECT email, created_at FROM report_agent_invites
            WHERE report_uuid = ? AND confirmed = 0
            ORDER BY created_at
        ');
        $stmt->execute([$reportUuid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAgentInviteByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM report_agent_invites WHERE token = ? AND confirmed = 0
        ');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Write — Reports
    // ═══════════════════════════════════════════════════════════════════════════════

    public function create(CreateReportCommand $cmd): string
    {
        $data = $this->toSnakeCase($cmd->toArray());
        $this->pdo->beginTransaction();
        try {
            $year = (int) date('Y');
            $seq = getNextSequence($this->pdo, $data['type'], $year);
            $reference = generateReference($data['type'], date('y'), $seq);
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
                    :is_confidential, :consent_syndicat, '" . ETAT_NOUVEAU . "',
                    :attachment_blob, :attachment_name, :attachment_mime
                )
            ");
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
                ':site_id' => $data['site_id'],
                ':site_text' => $data['site_text'] ?? null,
                ':pole' => $data['pole'] ?? null,
                ':service_affectation' => $data['service_affectation'] ?? null,
                ':telephone_mobile' => $data['telephone_mobile'] ?? null,
                ':is_confidential' => isset($data['is_confidential']) ? (int) $data['is_confidential'] : 1,
                ':consent_syndicat' => isset($data['consent_syndicat']) ? (int) $data['consent_syndicat'] : 0,
                ':attachment_blob' => $data['attachment_blob'] ?? null,
                ':attachment_name' => $data['attachment_name'] ?? null,
                ':attachment_mime' => $data['attachment_mime'] ?? null,
            ]);
            $this->pdo->commit();

            try {
                $this->pdo->prepare('INSERT INTO reports_fts(uuid, objet, description) VALUES (:uuid, :objet, :description)')
                    ->execute([':uuid' => $uuid, ':objet' => $data['objet'], ':description' => $data['description']]);
            } catch (Exception $ftsE) {
                error_log('[SST-DB] FTS5 insert warning: ' . $ftsE->getMessage());
            }
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
            ':is_confidential'   => isset($data['is_confidential']) ? (int) $data['is_confidential'] : 1,
            ':consent_syndicat'  => isset($data['consent_syndicat']) ? (int) $data['consent_syndicat'] : 0,
            ':pole'              => $data['pole'] ?? null,
            ':service_affectation' => $data['service_affectation'] ?? null,
            ':telephone_mobile'  => $data['telephone_mobile'] ?? null,
            ':site_text'         => $data['site_text'] ?? null,
        ];
        if (array_key_exists('attachment_blob', $data)) {
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
            . " WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN ('" . ETAT_NOUVEAU . "', '" . ETAT_EN_COURS . "')";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $updated = $stmt->rowCount() > 0;

        if ($updated) {
            try {
                $this->pdo->prepare('DELETE FROM reports_fts WHERE uuid = :uuid')->execute([':uuid' => $uuid]);
                $this->pdo->prepare('INSERT INTO reports_fts(uuid, objet, description) VALUES (:uuid, :objet, :description)')
                    ->execute([':uuid' => $uuid, ':objet' => $data['objet'], ':description' => $data['description']]);
            } catch (Exception $ftsE) {
                error_log('[SST-DB] FTS5 update warning: ' . $ftsE->getMessage());
            }
        }
        return $updated;
    }

    public function abandon(string $uuid, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE reports
            SET etat = '" . ETAT_ABANDONNE . "', updated_at = datetime('now')
            WHERE uuid = :uuid AND etat IN ('" . ETAT_NOUVEAU . "', '" . ETAT_EN_COURS . "')
        ");
        $stmt->execute([':uuid' => $uuid]);
        return $stmt->rowCount() > 0;
    }

    public function reopen(string $uuid, int $userId, string $motif): bool
    {
        $this->pdo->beginTransaction();
        try {
            $checkStmt = $this->pdo->prepare("SELECT etat FROM reports WHERE uuid = :uuid AND etat IN ('traite', 'abandonne')");
            $checkStmt->execute([':uuid' => $uuid]);
            $current = $checkStmt->fetch();
            if (!$current) {
                $this->pdo->rollBack();
                return false;
            }

            $histStmt = $this->pdo->prepare('
                INSERT INTO report_state_history (report_uuid, etat_precedent, etat_suivant, user_id, motif)
                VALUES (:report_uuid, :etat_precedent, :etat_suivant, :user_id, :motif)
            ');
            $histStmt->execute([
                ':report_uuid'    => $uuid,
                ':etat_precedent' => $current['etat'],
                ':etat_suivant'   => ETAT_REOUVERT,
                ':user_id'        => $userId,
                ':motif'          => $motif,
            ]);

            $updateStmt = $this->pdo->prepare("
                UPDATE reports
                SET etat = :nouvel_etat, updated_at = datetime('now')
                WHERE uuid = :uuid AND etat IN ('traite', 'abandonne')
            ");
            $updateStmt->execute([':nouvel_etat' => ETAT_REOUVERT, ':uuid' => $uuid]);
            if ($updateStmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            $respStmt = $this->pdo->prepare('
                INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat)
                VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat)
            ');
            $respStmt->execute([
                ':report_uuid' => $uuid,
                ':user_id'     => $userId,
                ':reponse'     => 'Réouverture du signalement. Motif : ' . $motif,
                ':nouvel_etat' => ETAT_REOUVERT,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[SST-DB] reopen failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function respond(string $uuid, RespondToReportCommand $cmd, int $userId): array
    {
        return $this->respondToReport($uuid, $userId, $cmd->reponse, $cmd->nouvelEtat, $cmd->attachment);
    }

    public function respondToReport(string $uuid, int $userId, string $reponse, string $nouvelEtat, array $attachment = []): array
    {
        $this->pdo->beginTransaction();
        try {
            $checkStmt = $this->pdo->prepare('SELECT etat, reponse, repondant_id, date_reponse FROM reports WHERE uuid = :uuid');
            $checkStmt->execute([':uuid' => $uuid]);
            $current = $checkStmt->fetch();

            if ($current && $current['etat'] === ETAT_REOUVERT && !empty($current['reponse'])) {
                $archiveStmt = $this->pdo->prepare('
                    INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat)
                    VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat)
                ');
                $archiveStmt->execute([
                    ':report_uuid' => $uuid,
                    ':user_id'     => (int) $current['repondant_id'],
                    ':reponse'     => '[Réponse initiale archivée] ' . $current['reponse'],
                    ':nouvel_etat' => ETAT_TRAITE,
                ]);
            }

            $stmt = $this->pdo->prepare("
                UPDATE reports
                SET etat = :nouvel_etat,
                    reponse = :reponse,
                    repondant_id = :user_id,
                    date_reponse = datetime('now'),
                    updated_at = datetime('now')
                WHERE uuid = :uuid AND etat IN ('" . ETAT_NOUVEAU . "', '" . ETAT_EN_COURS . "', '" . ETAT_REOUVERT . "')
            ");
            $stmt->execute([
                ':nouvel_etat' => $nouvelEtat,
                ':reponse'     => $reponse,
                ':user_id'     => $userId,
                ':uuid'        => $uuid,
            ]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return ['status' => 'concurrent'];
            }

            $stmt = $this->pdo->prepare('
                INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat, attachment_blob, attachment_name, attachment_mime)
                VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat, :attachment_blob, :attachment_name, :attachment_mime)
            ');
            $stmt->execute([
                ':report_uuid' => $uuid,
                ':user_id'     => $userId,
                ':reponse'     => $reponse,
                ':nouvel_etat' => $nouvelEtat,
                ':attachment_blob' => $attachment['blob'] ?? null,
                ':attachment_name' => $attachment['name'] ?? null,
                ':attachment_mime' => $attachment['mime'] ?? null,
            ]);

            $this->pdo->commit();
            return ['status' => 'true'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[SST-DB] respondToReport transaction failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Write — Linked agents
    // ═══════════════════════════════════════════════════════════════════════════════

    public function linkAgents(string $reportUuid, array $userIds): void
    {
        if (empty($userIds)) { return; }
        $stmt = $this->pdo->prepare('
            INSERT OR IGNORE INTO report_agents (report_uuid, user_id)
            VALUES (:uuid, :user_id)
        ');
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid > 0) {
                $stmt->execute([':uuid' => $reportUuid, ':user_id' => $uid]);
            }
        }
    }

    public function replaceLinkedAgents(string $reportUuid, array $userIds): void
    {
        $this->pdo->prepare('DELETE FROM report_agents WHERE report_uuid = ?')->execute([$reportUuid]);
        $this->linkAgents($reportUuid, $userIds);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Write — Agent invites
    // ═══════════════════════════════════════════════════════════════════════════════

    public function createAgentInvite(string $reportUuid, string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare('
            INSERT INTO report_agent_invites (report_uuid, email, token)
            VALUES (:uuid, :email, :token)
        ');
        $stmt->execute([':uuid' => $reportUuid, ':email' => $email, ':token' => $token]);
        return $token;
    }

    public function confirmAgentInvite(string $token, int $userId): bool
    {
        $invite = $this->getAgentInviteByToken($token);
        if (!$invite) { return false; }
        $stmt = $this->pdo->prepare("
            UPDATE report_agent_invites SET confirmed = 1, confirmed_at = datetime('now') WHERE token = ?
        ");
        $stmt->execute([$token]);
        $this->linkAgents($invite['report_uuid'], [$userId]);
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Statistics (delegated to StatsRepository for new code)
    // ═══════════════════════════════════════════════════════════════════════════════

    public function getStatistics(string $year = '', int $siteId = 0): array
    {
        return StatsRepository::instance()->getIndicateurs($year, $siteId);
    }

    public function getExportData(array $filters = []): array
    {
        return StatsRepository::instance()->getExportData($filters);
    }

    public function getPdo(): PDO { return $this->pdo; }
}
