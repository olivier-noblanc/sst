<?php

/** ReportQueryRepository — Requêtes/filtrage de signalements (lecture seule). */

namespace App\Repository;

use App\DTO\AdjacentUuids;
use App\DTO\PaginatedReports;
use App\DTO\ReportData;
use App\DTO\ReportFilter;
use App\DTO\ReportListItem;
use App\DTO\SiteId;
use App\Enum\ReportState;
use App\Enum\VisibilityMode;
use App\Query\QueryFilterBuilder;
use Exception;
use PDO;

class ReportQueryRepository
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
            siteId: SiteId::fromDatabase($row['site_id'] !== null ? (int) $row['site_id'] : null)->toSql(),
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

    public function findPaginated(ReportFilter $filter, int $page = 1, int $perPage = 20): PaginatedReports
    {
        // Audit #18 + #73 — clamp page and perPage to safe values.
        // #18: a negative or zero page could produce a negative OFFSET.
        // #73: a page > totalPages would produce an empty result + confusing
        //      UX (blank list, then pagination showing the last page).
        //      Now we clamp the page to totalPages (computed from the count)
        //      BEFORE the SELECT data, so the user sees the last available
        //      page instead of a blank one.
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $builder = new QueryFilterBuilder();
        $builder->addEqual('r.type', $filter->type);
        $filters = $filter->toArray();

        if (!empty($filters['linked_agent_id'])) {
            // linked_agent_id replaces both own_only and confidential_filter:
            // the agent sees reports they declared + reports they are linked to
            $lid = (int) $filters['linked_agent_id'];
            $linkedParams = [':linked_agent_id' => $lid];
            $linkedCondition = 'r.declarant_id = :linked_agent_id OR r.uuid IN (SELECT report_uuid FROM report_agents WHERE user_id = :linked_agent_id)';
            $visibility = $filters['linked_agent_visibility'] ?? VisibilityMode::Confidential->value;
            if ($visibility === VisibilityMode::AgentChoice->value) {
                // Audit #80 — force_site_id must restrict the "everyone else's
                // public reports" fallback (r.is_confidential = 0) — that's the
                // actual cross-site leak #3-High (c965c0c) fixed — but must NOT
                // restrict $linkedCondition: an agent explicitly linked to a
                // report (as declarant, or listed in report_agents) must see it
                // regardless of which site it was filed under. That's exactly
                // what 67037c4 fixed a day earlier; #3-High applied
                // force_site_id unconditionally to the whole OR and silently
                // undid it. Both bugs were real — fix is to split the site
                // restriction between the two branches instead of ANDing it
                // onto both.
                if (!empty($filters['force_site_id'])) {
                    $linkedParams[':force_site_id'] = (int) $filters['force_site_id'];
                    $builder->addRaw(
                        "($linkedCondition OR (r.is_confidential = 0 AND r.site_id = :force_site_id))",
                        $linkedParams
                    );
                } else {
                    $builder->addRaw(
                        "($linkedCondition OR r.is_confidential = 0)",
                        $linkedParams
                    );
                }
            } else {
                // In confidential mode: only declarant's own + linked reports —
                // no site restriction here either, being linked IS the
                // authorization (same reasoning as above).
                $builder->addRaw("($linkedCondition)", $linkedParams);
            }
        } else {
            if (!empty($filters['confidential_filter'])) {
                $cfIdRaw = $filters['confidential_filter'];
                $cfId = (int) $cfIdRaw;
                $builder->addRaw('(r.is_confidential = 0 OR r.declarant_id = :cf_declarant_id)', [':cf_declarant_id' => $cfId]);
            }
        }

        if (!empty($filters['etat'])) {
            $builder->addEqual('r.etat', $filters['etat']);
        } else {
            // Audit #64 — exclude 'abandonne' by default from the list view.
            // Before this fix, findPaginated returned all states including
            // 'abandonne' (soft-deleted reports), but RegistryCardService count
            // excludes them → the card said "5 signalements" but the list showed 7.
            // Now findPaginated excludes abandonne unless the user explicitly
            // filters by etat=abandonne.
            $builder->addRaw('r.etat != ' . $this->pdo->quote(ReportState::Abandonne->value));
        }
        if (!empty($filters['site_id']) && $filter->seeAllSites) {
            $builder->addEqual('r.site_id', $filters['site_id']);
        }
        if (!empty($filters['force_site_id']) && empty($filters['linked_agent_id'])) {
            // Audit #80 — when linked_agent_id is set, the site restriction is
            // already applied above (scoped only to the non-linked fallback).
            // Applying it again here, unconditionally, would AND it onto
            // $linkedCondition too and reintroduce the invisible-rattaché-
            // reports bug (67037c4) that #3-High (c965c0c) accidentally undid.
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
                    // @silent-ok: feature-detection probe (does the FTS5 table exist?),
                    // not a real failure — sets a flag the code branches on below.
                    $hasFts = false;
                }
            }
            // Audit #17 — Build WHERE conditionally instead of str_replace on the
            // WHERE string (brittle — could break if the SQL fragment changed).
            // Now we decide upfront which search clause to use.
            $searchTerm = $filters['q'];
            $ftsQuery = trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $searchTerm));
            if ($hasFts && $ftsQuery !== '') {
                // FTS5 search (fast, indexed) — only when we have a valid FTS query
                $where .= ' AND r.uuid IN (SELECT uuid FROM reports_fts WHERE reports_fts MATCH :q_fts)';
                $params[':q_fts'] = $ftsQuery;
            } else {
                // Fallback: LIKE search (slower, but works without FTS5 or for special-char queries)
                $where .= ' AND (r.objet LIKE :q OR r.description LIKE :q2)';
                $params[':q'] = $params[':q2'] = '%' . $searchTerm . '%';
            }
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports r WHERE $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Audit #73 — clamp page to totalPages now that we know the total.
        // If ?p=100 is requested on a 5-page list, return the last page
        // instead of a blank list. Avoids the confusing "empty list + pagination
        // showing page 5" UX.
        $totalPages = (int) ceil($total / $perPage);
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        $params[':limit'] = $perPage;
        $params[':offset'] = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare($this->baseSelect() . " WHERE $where ORDER BY r.created_at DESC, r.uuid DESC LIMIT :limit OFFSET :offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $reports = [];
        foreach ($rows as $row) {
            $reports[] = new ReportListItem(
                uuid: (string) $row['uuid'],
                reference: (string) ($row['reference'] ?? ''),
                type: (string) $row['type'],
                objet: (string) ($row['objet'] ?? ''),
                dateEvenement: (string) ($row['date_evenement'] ?? ''),
                // Audit #79 — was missing entirely: report_list.php's
                // $canEdit = $access->canEditReport($reportArr, $userId)
                // needs declarant_id to know whether the current user IS
                // the declarant. Without it, canEditReport() read an
                // undefined array key (warning in prod), (int) null = 0,
                // and no declarant ever saw "Modifier" on the list page —
                // the same user-facing symptom as the report_card.php
                // (array) cast bug fixed earlier this session, different
                // root cause (DTO missing the field, not a bad cast).
                declarantId: (int) ($row['declarant_id'] ?? 0),
                declarantNom: (string) ($row['declarant_nom'] ?? ''),
                declarantPrenom: (string) ($row['declarant_prenom'] ?? ''),
                siteCode: (string) ($row['site_code'] ?? ''),
                etat: (string) $row['etat'],
                isConfidential: (int) ($row['is_confidential'] ?? 0),
            );
        }
        return new PaginatedReports(reports: $reports, total: $total);
    }

    public function getAdjacentUuids(string $type, ?string $createdAt, string $currentUuid): AdjacentUuids
    {
        $createdAt ??= '';
        $uuid = $currentUuid;
        $prev = null;
        $next = null;

        // Audit #63 — La liste des signalements est triée par created_at DESC.
        // "Précédent" = plus récent (au-dessus dans la liste) = created_at > current.
        // "Suivant" = plus ancien (en-dessous) = created_at < current.
        // Avant ce fix, les sens étaient inversés.

        // prev = newer report (appears above current in DESC list)
        $stmt = $this->pdo->prepare('
            SELECT uuid, created_at FROM reports
            WHERE type = :type AND (created_at > :created_at OR (created_at = :created_at AND uuid > :uuid))
            ORDER BY created_at ASC, uuid ASC LIMIT 1
        ');
        $stmt->execute([':type' => $type, ':created_at' => $createdAt, ':uuid' => $uuid]);
        $row = $stmt->fetch();
        if (is_array($row) && isset($row['uuid'])) {
            $prev = $row['uuid'];
        }

        // next = older report (appears below current in DESC list)
        $stmt2 = $this->pdo->prepare('
            SELECT uuid, created_at FROM reports
            WHERE type = :type AND (created_at < :created_at OR (created_at = :created_at AND uuid < :uuid))
            ORDER BY created_at DESC, uuid DESC LIMIT 1
        ');
        $stmt2->execute([':type' => $type, ':created_at' => $createdAt, ':uuid' => $uuid]);
        $row2 = $stmt2->fetch();
        if (is_array($row2) && isset($row2['uuid'])) {
            $next = $row2['uuid'];
        }

        return new AdjacentUuids(prev: $prev, next: $next);
    }
}
