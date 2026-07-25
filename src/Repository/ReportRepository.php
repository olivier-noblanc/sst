<?php

/** ReportRepository — Couche d'accès aux données pour les signalements. */

namespace App\Repository;

use App\Enum\ReportState;
use App\Enum\ReportType;
use App\Enum\VisibilityMode;
use Exception;
use App\DTO\AdjacentUuids;
use App\DTO\CreateReportCommand;
use App\DTO\PaginatedReports;
use App\DTO\ReportData;
use App\DTO\ReportFilter;
use App\DTO\ReportListItem;
use App\DTO\ReportStateCounts;
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
            // Prefer container instance if available (shared lifecycle)
            if (function_exists('getContainer') && getContainer()->has(self::class)) {
                $instance = getContainer()->get(self::class);
            } else {
                $instance = new self(getDB());
            }
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
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

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Reports
    // ═══════════════════════════════════════════════════════════════════════════════

    public function findById(string $uuid): ?ReportData
    {
        if (!isValidUuid($uuid)) {
            return null;
        }
        $stmt = $this->pdo->prepare('
            SELECT r.uuid, r.reference, r.type, r.objet, r.description,
                   r.date_evenement, r.heure_evenement, r.lieu,
                   r.declarant_id, r.declarant_nom, r.declarant_prenom,
                   r.pour_compte_de, r.pour_compte_nom, r.pour_compte_prenom,
                   r.nature_auteur, r.type_acte,
                   r.site_id, r.site_text, r.pole, r.service_affectation, r.telephone_mobile,
                   r.is_confidential, r.consent_syndicat, r.etat,
                   r.repondant_id, r.date_reponse, r.reponse,
                   r.attachment_name, r.attachment_mime,
                   r.created_at, r.updated_at,
                   s.code as site_code, s.nom as site_nom,
                   rep.nom as repondant_nom, rep.prenom as repondant_prenom
            FROM reports r
            LEFT JOIN sites s ON r.site_id = s.id
            LEFT JOIN users rep ON r.repondant_id = rep.id
            WHERE r.uuid = :uuid
        ');
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        return new ReportData(
            uuid: (string) $row['uuid'],
            reference: (string) ($row['reference'] ?? ''),
            type: (string) $row['type'],
            objet: (string) ($row['objet'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            dateEvenement: (string) ($row['date_evenement'] ?? ''),
            heureEvenement: (string) ($row['heure_evenement'] ?? ''),
            lieu: (string) ($row['lieu'] ?? ''),
            declarantId: (int) $row['declarant_id'],
            declarantNom: (string) ($row['declarant_nom'] ?? ''),
            declarantPrenom: (string) ($row['declarant_prenom'] ?? ''),
            pourCompteDe: (string) ($row['pour_compte_de'] ?? ''),
            pourCompteNom: (string) ($row['pour_compte_nom'] ?? ''),
            pourComptePrenom: (string) ($row['pour_compte_prenom'] ?? ''),
            natureAuteur: (string) ($row['nature_auteur'] ?? ''),
            typeActe: (string) ($row['type_acte'] ?? ''),
            siteId: (int) ($row['site_id'] ?? 0),
            siteText: (string) ($row['site_text'] ?? ''),
            pole: (string) ($row['pole'] ?? ''),
            serviceAffectation: (string) ($row['service_affectation'] ?? ''),
            telephoneMobile: (string) ($row['telephone_mobile'] ?? ''),
            isConfidential: (int) ($row['is_confidential'] ?? 0),
            consentSyndicat: (int) ($row['consent_syndicat'] ?? 0),
            etat: (string) $row['etat'],
            repondantId: isset($row['repondant_id']) ? (int) $row['repondant_id'] : null,
            dateReponse: $row['date_reponse'] ?? null,
            reponse: $row['reponse'] ?? null,
            attachmentName: $row['attachment_name'] ?? null,
            attachmentMime: $row['attachment_mime'] ?? null,
            createdAt: (string) ($row['created_at'] ?? ''),
            updatedAt: (string) ($row['updated_at'] ?? ''),
            siteCode: (string) ($row['site_code'] ?? ''),
            siteNom: (string) ($row['site_nom'] ?? ''),
            repondantNom: $row['repondant_nom'] ?? null,
            repondantPrenom: $row['repondant_prenom'] ?? null,
        );
    }

    /**
     * Fetch only the attachment blob for a report (used by print page).
     *
     * @return array{attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null}|null
     */
    public function getAttachmentBlob(string $uuid): ?array
    {
        if (!isValidUuid($uuid)) {
            return null;
        }
        $stmt = $this->pdo->prepare('
            SELECT attachment_blob, attachment_name, attachment_mime
            FROM reports WHERE uuid = :uuid
        ');
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findPaginated(ReportFilter $filter, int $page = 1, int $perPage = 20): PaginatedReports
    {
        $builder = new QueryFilterBuilder();
        $builder->addEqual('r.type', $filter->type);
        $filters = $filter->toArray();

        if (!empty($filters['linked_agent_id'])) {
            // linked_agent_id replaces both own_only and confidential_filter:
            // the agent sees reports they declared + reports they are linked to
            $lid = (int) $filters['linked_agent_id'];
            $linkedCondition = 'r.declarant_id = :linked_agent_id OR r.uuid IN (SELECT report_uuid FROM report_agents WHERE user_id = :linked_agent_id)';
            $visibility = $filters['linked_agent_visibility'] ?? VisibilityMode::Confidential->value;
            if ($visibility === VisibilityMode::AgentChoice->value) {
                // In agent_choice: linked reports + all non-confidential reports from the site
                $builder->addRaw(
                    "($linkedCondition OR r.is_confidential = 0)",
                    [':linked_agent_id' => $lid]
                );
            } else {
                // In confidential: only declarant's own + linked reports
                $builder->addRaw(
                    "($linkedCondition)",
                    [':linked_agent_id' => $lid]
                );
            }
        } else {
            if (!empty($filters['confidential_filter'])) {
                $cfIdRaw = $filters['confidential_filter'];
                $cfId = (int) $cfIdRaw;
                $builder->addRaw('(r.is_confidential = 0 OR r.declarant_id = :cf_declarant_id)', [':cf_declarant_id' => $cfId]);
            }
            if (!empty($filters['own_only'])) {
                $builder->addEqual('r.declarant_id', $filters['own_only']);
            }
        }

        if (!empty($filters['etat'])) {
            $builder->addEqual('r.etat', $filters['etat']);
        }
        if (!empty($filters['site_id']) && $filter->seeAllSites) {
            $builder->addEqual('r.site_id', $filters['site_id']);
        }
        if (!empty($filters['force_site_id']) && empty($filters['linked_agent_id'])) {
            $forceSiteIdRaw = $filters['force_site_id'];
            $builder->addEqual('r.site_id', (int) $forceSiteIdRaw);
        }
        if (!empty($filters['declarant_id']) && empty($filters['confidential_filter']) && empty($filters['linked_agent_id'])) {
            $builder->addEqual('r.declarant_id', $filters['declarant_id']);
        }
        if (!empty($filters['chsct_consent_only'])) {
            $builder->addRaw('r.consent_syndicat = 1');
        }

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
                $ftsQuery = trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', is_string($filters['q']) ? $filters['q'] : ''));
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
        $rows = $stmt->fetchAll();
        $reports = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $reports[] = new ReportListItem(
                    uuid: (string) $row['uuid'],
                    reference: (string) ($row['reference'] ?? ''),
                    type: (string) $row['type'],
                    objet: (string) ($row['objet'] ?? ''),
                    dateEvenement: (string) ($row['date_evenement'] ?? ''),
                    declarantNom: (string) ($row['declarant_nom'] ?? ''),
                    declarantPrenom: (string) ($row['declarant_prenom'] ?? ''),
                    siteCode: (string) ($row['site_code'] ?? ''),
                    etat: (string) $row['etat'],
                    isConfidential: (int) ($row['is_confidential'] ?? 0),
                );
            }
        }
        return new PaginatedReports(reports: $reports, total: $total);
    }

    /**
     * @param array{type?: string, created_at?: string, uuid?: string} $report
     */
    public function getAdjacentUuids(array $report): AdjacentUuids
    {
        $type = $report['type'] ?? ReportType::Rsst->value;
        $createdAt = $report['created_at'] ?? '';
        $uuid = $report['uuid'] ?? '';
        $prev = null;
        $next = null;

        $stmt = $this->pdo->prepare('
            SELECT uuid, created_at FROM reports
            WHERE type = :type AND (created_at < :created_at OR (created_at = :created_at AND uuid < :uuid))
            ORDER BY created_at DESC, uuid DESC LIMIT 1
        ');
        $stmt->execute([':type' => $type, ':created_at' => $createdAt, ':uuid' => $uuid]);
        $row = $stmt->fetch();
        if (is_array($row) && isset($row['uuid'])) {
            $prev = $row['uuid'];
        }

        $stmt2 = $this->pdo->prepare('
            SELECT uuid, created_at FROM reports
            WHERE type = :type AND (created_at > :created_at OR (created_at = :created_at AND uuid > :uuid))
            ORDER BY created_at ASC, uuid ASC LIMIT 1
        ');
        $stmt2->execute([':type' => $type, ':created_at' => $createdAt, ':uuid' => $uuid]);
        $row2 = $stmt2->fetch();
        if (is_array($row2) && isset($row2['uuid'])) {
            $next = $row2['uuid'];
        }

        return new AdjacentUuids(prev: $prev, next: $next);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Counts
    // ═══════════════════════════════════════════════════════════════════════════════

    public function countByState(string $type, int $siteId = 0, bool $seeAllSites = true): ReportStateCounts
    {
        $sql = 'SELECT etat, COUNT(*) as count FROM reports WHERE type = :type AND etat != ' . $this->pdo->quote(ReportState::Abandonne->value);
        $params = [':type' => $type];

        if (!$seeAllSites && $siteId > 0) {
            $sql .= ' AND site_id = :site_id';
            $params[':site_id'] = $siteId;
        }
        $sql .= ' GROUP BY etat';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $nouveau = 0;
        $enCours = 0;
        $traite = 0;
        $reouvert = 0;
        $total = 0;
        $rows = $stmt->fetchAll();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $etat = is_string($row['etat'] ?? null) ? $row['etat'] : '';
                $count = (int) ($row['count'] ?? 0);
                match ($etat) {
                    ReportState::Nouveau->value => $nouveau = $count,
                    ReportState::EnCours->value => $enCours = $count,
                    ReportState::Traite->value => $traite = $count,
                    ReportState::Reouvert->value => $reouvert = $count,
                    default => null,
                };
                $total += $count;
            }
        }
        return new ReportStateCounts(
            nouveau: $nouveau,
            enCours: $enCours,
            traite: $traite,
            reouvert: $reouvert,
            total: $total,
        );
    }

    public function countActive(string $type, int $siteId = 0, int $userId = 0, bool $confidentialMode = false): int
    {
        $sql = "SELECT COUNT(*) FROM reports WHERE type = :type AND etat != '" . ReportState::Abandonne->value . "'";
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

    /**
     * Count reports visible to an agent, including reports where they are linked
     * via report_agents table.
     */
    public function countVisibleForAgent(string $type, int $userId, int $siteId = 0, string $visibility = VisibilityMode::Confidential->value): int
    {
        $sql = "SELECT COUNT(*) FROM reports r WHERE r.type = :type AND r.etat != '" . ReportState::Abandonne->value . "'";
        $params = [':type' => $type];

        $linkedClause = '(r.declarant_id = :user_id OR r.uuid IN (SELECT report_uuid FROM report_agents WHERE user_id = :user_id))';

        if ($visibility === VisibilityMode::Confidential->value) {
            $sql .= " AND $linkedClause";
            $params[':user_id'] = $userId;
        } elseif ($visibility === VisibilityMode::AgentChoice->value) {
            if ($siteId > 0) {
                $sql .= ' AND r.site_id = :site_id';
                $params[':site_id'] = $siteId;
            }
            $sql .= " AND (r.is_confidential = 0 OR $linkedClause)";
            $params[':user_id'] = $userId;
        } else {
            // public
            if ($siteId > 0) {
                $sql .= ' AND r.site_id = :site_id';
                $params[':site_id'] = $siteId;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Responses
    // ═══════════════════════════════════════════════════════════════════════════════

    /** @return array<mixed, mixed> */
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
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param list<string> $uuids
     * @return array<string, list<array<mixed>>>
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
        while ($resp = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($resp)) {
                continue;
            }
            $uuidValue = $resp['report_uuid'] ?? null;
            if (is_string($uuidValue) && $uuidValue !== '') {
                $result[$uuidValue][] = $resp;
            }
        }
        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Read — Linked agents
    // ═══════════════════════════════════════════════════════════════════════════════

    /** @return array<int, array{id: int, nom: string, prenom: string, email: string}> */
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array{email: string, created_at: string}> */
    public function getPendingInvites(string $reportUuid): array
    {
        $stmt = $this->pdo->prepare('
            SELECT email, created_at FROM report_agent_invites
            WHERE report_uuid = ? AND confirmed = 0
            ORDER BY created_at
        ');
        $stmt->execute([$reportUuid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        /** @var list<array{email: string, created_at: string}> $rows */
        return is_array($rows) ? $rows : [];
    }

    /** @return array<mixed, mixed>|null */
    public function getAgentInviteByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM report_agent_invites WHERE token = ? AND confirmed = 0
        ');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
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
                ':site_id' => !empty($data['site_id']) ? $data['site_id'] : null,
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

    public function abandon(string $uuid, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE reports
            SET etat = '" . ReportState::Abandonne->value . "', updated_at = datetime('now')
            WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN ('" . ReportState::Nouveau->value . "', '" . ReportState::EnCours->value . "')
        ");
        $stmt->execute([':uuid' => $uuid, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function reopen(string $uuid, int $userId, string $motif): bool
    {
        $this->pdo->beginTransaction();
        try {
            $checkStmt = $this->pdo->prepare("SELECT etat FROM reports WHERE uuid = :uuid AND etat IN ('traite', 'abandonne')");
            $checkStmt->execute([':uuid' => $uuid]);
            $current = $checkStmt->fetch();
            if (!is_array($current)) {
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
                ':etat_suivant'   => ReportState::Reouvert->value,
                ':user_id'        => $userId,
                ':motif'          => $motif,
            ]);

            $updateStmt = $this->pdo->prepare("
                UPDATE reports
                SET etat = :nouvel_etat, updated_at = datetime('now')
                WHERE uuid = :uuid AND etat IN ('traite', 'abandonne')
            ");
            $updateStmt->execute([':nouvel_etat' => ReportState::Reouvert->value, ':uuid' => $uuid]);
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
                ':nouvel_etat' => ReportState::Reouvert->value,
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

    /** @return array{status: string, message?: string} */
    public function respond(string $uuid, RespondToReportCommand $cmd, int $userId): array
    {
        return $this->respondToReport($uuid, $userId, $cmd->reponse, $cmd->nouvelEtat, $cmd->attachment);
    }

    /**
     * @param array<mixed, mixed> $attachment
     * @return array{status: string, message?: string}
     */
    public function respondToReport(string $uuid, int $userId, string $reponse, string $nouvelEtat, array $attachment = []): array
    {
        $this->pdo->beginTransaction();
        try {
            $checkStmt = $this->pdo->prepare('SELECT etat, reponse, repondant_id, date_reponse FROM reports WHERE uuid = :uuid');
            $checkStmt->execute([':uuid' => $uuid]);
            $current = $checkStmt->fetch();

            if (is_array($current) && $current['etat'] === ReportState::Reouvert->value && !empty($current['reponse'])) {
                $archiveStmt = $this->pdo->prepare('
                    INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat)
                    VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat)
                ');
                $repondantIdRaw = $current['repondant_id'] ?? null;
                $archiveUserId = $repondantIdRaw !== null ? (int) $repondantIdRaw : 0;
                $archiveStmt->execute([
                    ':report_uuid' => $uuid,
                    ':user_id'     => $archiveUserId,
                    ':reponse'     => '[Réponse initiale archivée] ' . $current['reponse'],
                    ':nouvel_etat' => ReportState::Traite->value,
                ]);
            }

            $stmt = $this->pdo->prepare("
                UPDATE reports
                SET etat = :nouvel_etat,
                    reponse = :reponse,
                    repondant_id = :user_id,
                    date_reponse = datetime('now'),
                    updated_at = datetime('now')
                WHERE uuid = :uuid AND etat IN ('" . ReportState::Nouveau->value . "', '" . ReportState::EnCours->value . "', '" . ReportState::Reouvert->value . "')
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
            return ['status' => 'ok'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[SST-DB] respondToReport transaction failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Write — Linked agents
    // ═══════════════════════════════════════════════════════════════════════════════

    /** @param list<int|string> $userIds */
    public function linkAgents(string $reportUuid, array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        // Filter valid user IDs
        $validIds = array_filter(array_map(fn($id) => (int) $id, $userIds), fn($id) => $id > 0);
        if (empty($validIds)) {
            return;
        }

        // Build multi-row INSERT with UNION ALL for SQLite
        $rows = [];
        $params = [];
        foreach ($validIds as $i => $uid) {
            $rows[] = "(:uuid, :uid_{$i})";
            $params[":uid_{$i}"] = $uid;
        }
        $params[':uuid'] = $reportUuid;

        $sql = 'INSERT OR IGNORE INTO report_agents (report_uuid, user_id) VALUES '
            . implode(', ', $rows);
        $this->pdo->prepare($sql)->execute($params);
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
        if ($invite === null) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE report_agent_invites SET confirmed = 1, confirmed_at = datetime('now') WHERE token = ?
            ");
            $stmt->execute([$token]);
            $this->linkAgents($invite['report_uuid'], [$userId]);
            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Export (delegated to StatsRepository)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * @param array<string, mixed> $filters
     * @return array<mixed, mixed>
     */
    public function getExportData(array $filters = []): array
    {
        return StatsRepository::instance()->getExportData($filters);
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    // Write — Linked agents
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Check if a user is linked to a report via report_agents table.
     */
    public function isLinkedAgent(string $reportUuid, int $userId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1 FROM report_agents WHERE report_uuid = :uuid AND user_id = :user_id LIMIT 1
        ');
        $stmt->execute([':uuid' => $reportUuid, ':user_id' => $userId]);
        return (bool) $stmt->fetch();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get response attachment by response ID.
     *
     * @return array<string, mixed>|null
     */
    public function getResponseAttachmentById(int $responseId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT rr.attachment_blob, rr.attachment_name, rr.attachment_mime, rr.report_uuid
            FROM report_responses rr
            WHERE rr.id = :id
        ');
        $stmt->execute([':id' => $responseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function countByDeclarantId(int $declarantId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM reports WHERE declarant_id = :uid');
        $stmt->execute([':uid' => $declarantId]);
        return (int) $stmt->fetchColumn();
    }

    public function getTypeByUuid(string $uuid): ?string
    {
        $stmt = $this->pdo->prepare('SELECT type FROM reports WHERE uuid = :uuid');
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? ($row['type'] ?? null) : null;
    }

    public function getNextSequence(string $type, int $year): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO report_sequence (type, year, last_sequence)
            VALUES (:type, :year, 1)
            ON CONFLICT(type, year) DO UPDATE SET last_sequence = last_sequence + 1
            RETURNING last_sequence
        ');
        $stmt->execute([':type' => $type, ':year' => $year]);
        return (int) $stmt->fetchColumn();
    }

    public function logAccess(string $reportUuid, int $userId, string $role): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO report_access_log (report_uuid, user_id, role)
            VALUES (:report_uuid, :user_id, :role)
        ');
        $stmt->execute([
            ':report_uuid' => $reportUuid,
            ':user_id'     => $userId,
            ':role'        => $role,
        ]);
    }
}
