<?php

/** ReportAnonymizationRepository — Couche d'accès aux données pour la rétention RGPD et les délais (anonymisation). */

namespace App\Repository;

use App\Enum\ReportState;
use PDO;

class ReportAnonymizationRepository
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
     * Find overdue reports (nouveau state, older than cutoff) for delay alerts.
     *
     * @return list<array{uuid: string, reference: string, type: string, objet: string, created_at: string, site_id: int|null, site_code: string|null, site_nom: string|null, declarant_nom: string|null, declarant_prenom: string|null}>
     */
    public function findOverdue(string $cutoffDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.uuid, r.reference, r.type, r.objet, r.created_at,
                   r.site_id, s.code as site_code, s.nom as site_nom,
                   d.nom as declarant_nom, d.prenom as declarant_prenom
            FROM reports r
            LEFT JOIN sites s ON r.site_id = s.id
            LEFT JOIN users d ON r.declarant_id = d.id
            WHERE r.etat = '" . ReportState::Nouveau->value . "'
              AND r.created_at < :cutoff_date
            ORDER BY r.created_at ASC
        ");
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        /** @var list<array{uuid: string, reference: string, type: string, objet: string, created_at: string, site_id: int|null, site_code: string|null, site_nom: string|null, declarant_nom: string|null, declarant_prenom: string|null}> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /**
     * Find reports eligible for RGPD anonymization (final state, older than cutoff, not yet anonymized).
     *
     * @return list<array{uuid: string, reference: string, type: string, declarant_nom: string, declarant_prenom: string, date_evenement: string, etat: string}>
     */
    public function findAnonymizable(string $cutoffDate): array
    {
        // Audit #46 — Before this fix, the retention period was based on
        // date_evenement (event date) instead of date_reponse (close date).
        // A report closed yesterday could be anonymized today if the event
        // was old — defeating the purpose of the RGPD retention period.
        // Now we use COALESCE(date_reponse, date_evenement, created_at):
        //   - Prefer date_reponse (when the report was closed)
        //   - Fall back to date_evenement (if not yet closed — should not happen
        //     since we filter on etat IN (traite, abandonne))
        //   - Fall back to created_at (last resort)
        $stmt = $this->pdo->prepare("
            SELECT uuid, reference, type, declarant_nom, declarant_prenom, date_evenement, etat
            FROM reports
            WHERE etat IN ('" . ReportState::Traite->value . "', '" . ReportState::Abandonne->value . "')
              AND COALESCE(date_reponse, date_evenement, created_at) < :cutoff_date
              AND declarant_nom != '" . AnonymizationPolicy::ANONYMIZED_NAME . "'
        ");
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        /** @var list<array{uuid: string, reference: string, type: string, declarant_nom: string, declarant_prenom: string, date_evenement: string, etat: string}> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /**
     * Anonymize a single report (RGPD).
     */
    public function anonymize(string $uuid): bool
    {
        // Consolidé dans AnonymizationPolicy — mêmes valeurs que UserRepository::anonymize().
        return new AnonymizationPolicy()->anonymizeReport($this->pdo, $uuid);
    }
}
