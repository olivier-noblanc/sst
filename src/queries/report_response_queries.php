<?php

/**
 * Report Response Queries — Application SST DREETS BFC
 *
 * Functions for responding to and updating reports.
 * Split from report_queries.php for readability (max 250 lines per file).
 *
 * All functions delegate to App\Repository\ReportRepository.
 */

use App\Repository\ReportRepository;

/**
 * Update a report (edit by declarant).
 *
 * @param PDO    $pdo     Database connection
 * @param string $uuid    Report UUID
 * @param array<string, mixed> $data    Updated data
 * @param int    $userId  The declarant's user ID (for ownership check)
 * @return bool
 */
function updateReport(PDO $pdo, string $uuid, array $data, int $userId): bool
{
    $repo = ReportRepository::instance();
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

    $stmt = $repo->getPdo()->prepare($sql);
    $stmt->execute($params);
    $updated = $stmt->rowCount() > 0;

    if ($updated) {
        try {
            $repo->getPdo()->prepare('DELETE FROM reports_fts WHERE uuid = :uuid')->execute([':uuid' => $uuid]);
            $repo->getPdo()->prepare('INSERT INTO reports_fts(uuid, objet, description) VALUES (:uuid, :objet, :description)')
                ->execute([':uuid' => $uuid, ':objet' => $data['objet'], ':description' => $data['description']]);
        } catch (Exception $ftsE) {
            error_log('[SST-DB] FTS5 update warning: ' . $ftsE->getMessage());
        }
    }
    return $updated;
}

/**
 * Abandon a report (soft delete).
 *
 * @param PDO    $pdo     Database connection
 * @param string $uuid    Report UUID
 * @param int    $userId  The declarant's user ID
 * @return bool
 */
function abandonReport(PDO $pdo, string $uuid, int $userId): bool
{
    return ReportRepository::instance()->abandon($uuid, $userId);
}

/**
 * Respond to a report (by superviseur only).
 * Also inserts into the response history table.
 *
 * @param PDO    $pdo          Database connection
 * @param string $uuid         Report UUID
 * @param int    $userId       The responding user's ID
 * @param string $reponse      Response text
 * @param string $nouvelEtat   New state ('en_cours' or 'traite')
 * @param array  $attachment   Optional attachment data ['blob', 'name', 'mime']
 * @return array{status: string, message?: string} Status result
 */
function respondToReport(PDO $pdo, string $uuid, int $userId, string $reponse, string $nouvelEtat, array $attachment = []): array
{
    return ReportRepository::instance()->respondToReport($uuid, $userId, $reponse, $nouvelEtat, $attachment);
}
