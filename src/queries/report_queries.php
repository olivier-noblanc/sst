<?php

use App\DTO\ReportFilter;

/**
 * Report Queries — Application SST DREETS BFC
 *
 * Core SQL queries for reports (CRUD, listing, search).
 * Related: report_response_queries.php, report_count_queries.php,
 * report_agent_queries.php, report_invite_queries.php.
 *
 * All functions delegate to App\Repository\ReportRepository.
 */

require_once __DIR__ . '/report_count_queries.php';
require_once __DIR__ . '/report_response_queries.php';

use App\Repository\ReportRepository;

/** Generate a UUID v4. */
function generateUuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3)
        . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2)
        . '-' . substr($hex, 20, 12);
}

/** Validate UUID format (8-4-4-4-12 hex). Accepts all variants for legacy compatibility. */
function isValidUuid(string $uuid): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
}

/**
 * Create a new report. Returns the new report UUID.
 * @param array<string, mixed> $data
 */
function createReport(PDO $pdo, array $data): string
{
    $repo = ReportRepository::instance();
    $uuid = generateUuid();

    $repo->getPdo()->beginTransaction();
    try {
        $year = (int) date('Y');
        $seq = getNextSequence($repo->getPdo(), $data['type'], $year);
        $reference = generateReference($data['type'], date('y'), $seq);

        $stmt = $repo->getPdo()->prepare("
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
        /** @var int|string|null $isConfidentialRaw */
        $isConfidentialRaw = $data['is_confidential'] ?? null;
        $isConfidential = $isConfidentialRaw !== null ? (int) $isConfidentialRaw : 1;
        /** @var int|string|null $consentSyndicatRaw */
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
            ':site_id' => $data['site_id'],
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
        $repo->getPdo()->commit();

        // Update FTS5 index (non-critical)
        try {
            $repo->getPdo()->prepare('INSERT INTO reports_fts(uuid, objet, description) VALUES (:uuid, :objet, :description)')
                ->execute([':uuid' => $uuid, ':objet' => $data['objet'], ':description' => $data['description']]);
        } catch (Exception $ftsE) {
            error_log('[SST-DB] FTS5 insert warning: ' . $ftsE->getMessage());
        }
        return $uuid;
    } catch (Exception $e) {
        $repo->getPdo()->rollBack();
        error_log('[SST-DB] createReport failed: ' . $e->getMessage());
        throw $e;
    }
}

/** Get a single report by UUID with site and respondent info.
 * @return array<string, mixed>|null
 */
function getReportByUuid(PDO $pdo, string $uuid): ?array
{
    return ReportRepository::instance()->findById($uuid);
}

/**
 * Get reports by registry type with filtering and pagination.
 * @param array<string, mixed> $filters
 * @return array{reports: list<array<string, mixed>>, total: int}
 */
function getReportsByRegistry(PDO $pdo, string $type, array $filters, int $userSiteId, bool $seeAllSites, int $page = 1, int $perPage = 20): array
{
    /** @var int|string|null $siteIdRaw */
    $siteIdRaw = $filters['site_id'] ?? null;
    $siteId = $siteIdRaw !== null ? (int) $siteIdRaw : 0;
    /** @var int|string|null $declarantIdRaw */
    $declarantIdRaw = !empty($filters['declarant_id']) ? $filters['declarant_id'] : null;
    $declarantId = $declarantIdRaw !== null ? (int) $declarantIdRaw : null;
    /** @var int|string|null $confidentialFilterRaw */
    $confidentialFilterRaw = !empty($filters['confidential_filter']) ? $filters['confidential_filter'] : null;
    $confidentialFilter = $confidentialFilterRaw !== null ? (int) $confidentialFilterRaw : null;
    /** @var int|string|null $forceSiteIdRaw */
    $forceSiteIdRaw = !empty($filters['force_site_id']) ? $filters['force_site_id'] : null;
    $forceSiteId = $forceSiteIdRaw !== null ? (int) $forceSiteIdRaw : null;
    /** @var string $etat */
    $etat = $filters['etat'] ?? '';
    /** @var string|null $search */
    $search = $filters['q'] ?? null;
    $filter = new ReportFilter(
        type: $type,
        etat: $etat,
        siteId: $siteId,
        declarantId: $declarantId,
        confidentialFilter: $confidentialFilter,
        forceSiteId: $forceSiteId,
        search: $search,
        seeAllSites: $seeAllSites,
    );
    return ReportRepository::instance()->findPaginated($filter, $page, $perPage);
}
