<?php

/** ReportLifecycleRepository — Couche d'accès aux données pour le cycle de vie des signalements (abandon, réouverture, réponse). */

namespace App\Repository;

use App\DTO\AttachmentData;
use App\DTO\RespondToReportCommand;
use App\Enum\ReportState;
use App\Enum\RespondStatus;
use Exception;
use PDO;
use Throwable;

class ReportLifecycleRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            if (function_exists('getContainer') && getContainer()->has(self::class)) {
                $instance = getContainer()->get(self::class);
            } else {
                $instance = new self(getDB());
            }
        }
        return $instance;
    }

    /**
     * Audit #19 — count how many times a report has been reopened
     * (for rate limiting). Uses report_state_history to count transitions
     * to Reouvert state.
     */
    public function countReopens(string $uuid): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM report_state_history
                 WHERE report_uuid = :uuid AND etat_suivant = :etat_reouvert'
            );
            $stmt->execute([
                ':uuid' => $uuid,
                ':etat_reouvert' => ReportState::Reouvert->value,
            ]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            // @silent-ok: pre-migration (table missing) — fails open (allow reopen) rather
            // than blocking a legitimate action on a DB that hasn't migrated yet.
            error_log('[SST-REPORT] countReopens failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Abandonne un signalement et retourne l'id de la transition
     * (report_state_history.id) matérialisant CETTE occurrence d'abandon.
     *
     * L'id est l'identité durable utilisée comme composant de dedup_key outbox :
     * sans lui, deux abandons successifs (ex. cycles reopen→abandon) partagent
     * la même clé et la seconde notification est perdue par
     * `ON CONFLICT DO NOTHING`.
     *
     * @return int id de la ligne report_state_history insérée ; 0 si l'abandon
     *             n'a pas eu lieu (signalement introuvable ou état non abandonnable)
     */
    public function abandon(string $uuid, int $userId): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $stateStmt = $this->pdo->prepare("
                SELECT etat FROM reports
                WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN (
                    '" . ReportState::Nouveau->value . "',
                    '" . ReportState::EnCours->value . "',
                    '" . ReportState::Traite->value . "',
                    '" . ReportState::Reouvert->value . "'
                )
            ");
            $stateStmt->execute([':uuid' => $uuid, ':user_id' => $userId]);
            $previousState = $stateStmt->fetchColumn();
            if ($previousState === false) {
                $this->rollBackIfOwned($ownsTransaction);
                return 0;
            }

            // Audit lifecycle — clause d'états ALIGNÉE sur la matrice
            // ReportStateMachine::TRANSITIONS (autorité) : Abandonne est atteignable
            // depuis Nouveau/EnCours/Traite/Reouvert pour le rôle Agent. L'ancienne
            // clause ('nouveau', 'en_cours') était plus restrictive que la matrice
            // et rejetait silencieusement (rowCount=0) un abandon d'un signalement
            // Réouvert déjà validé par les guards.
            $stmt = $this->pdo->prepare("
                UPDATE reports
                SET etat = '" . ReportState::Abandonne->value . "', updated_at = datetime('now')
                WHERE uuid = :uuid AND declarant_id = :user_id AND etat IN (
                    '" . ReportState::Nouveau->value . "',
                    '" . ReportState::EnCours->value . "',
                    '" . ReportState::Traite->value . "',
                    '" . ReportState::Reouvert->value . "'
                )
            ");
            $stmt->execute([':uuid' => $uuid, ':user_id' => $userId]);
            if ($stmt->rowCount() === 0) {
                $this->rollBackIfOwned($ownsTransaction);
                return 0;
            }

            // Traçabilité de la transition (comme reopen) — l'id sert aussi
            // d'identité d'occurrence au dedup_key outbox.
            $histStmt = $this->pdo->prepare('
                INSERT INTO report_state_history (report_uuid, etat_precedent, etat_suivant, user_id, motif)
                VALUES (:report_uuid, :etat_precedent, :etat_suivant, :user_id, NULL)
            ');
            $histStmt->execute([
                ':report_uuid'    => $uuid,
                ':etat_precedent' => (string) $previousState,
                ':etat_suivant'   => ReportState::Abandonne->value,
                ':user_id'        => $userId,
            ]);
            $historyId = (int) $this->pdo->lastInsertId();

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $historyId;
        } catch (Exception $e) {
            $this->rollBackIfOwned($ownsTransaction);
            error_log('[SST-DB] abandon failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Réouvre un signalement et retourne l'id de la transition
     * (report_state_history.id) matérialisant CETTE occurrence — identité
     * durable du dedup_key outbox (deux réouvertures successives ne doivent
     * pas partager la même clé, même motif).
     *
     * @return int id de la ligne report_state_history insérée ; 0 si la
     *             réouverture n'a pas eu lieu
     */
    public function reopen(string $uuid, int $userId, string $motif): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $checkStmt = $this->pdo->prepare("SELECT etat FROM reports WHERE uuid = :uuid AND etat IN ('traite', 'abandonne')");
            $checkStmt->execute([':uuid' => $uuid]);
            $current = $checkStmt->fetch();
            if (!is_array($current)) {
                $this->rollBackIfOwned($ownsTransaction);
                return 0;
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
            $historyId = (int) $this->pdo->lastInsertId();

            $updateStmt = $this->pdo->prepare("
                UPDATE reports
                SET etat = :nouvel_etat, updated_at = datetime('now')
                WHERE uuid = :uuid AND etat IN (" . $this->pdo->quote(ReportState::Traite->value) . ', ' . $this->pdo->quote(ReportState::Abandonne->value) . ')
            ');
            $updateStmt->execute([':nouvel_etat' => ReportState::Reouvert->value, ':uuid' => $uuid]);
            if ($updateStmt->rowCount() === 0) {
                $this->rollBackIfOwned($ownsTransaction);
                return 0;
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

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $historyId;
        } catch (Exception $e) {
            $this->rollBackIfOwned($ownsTransaction);
            error_log('[SST-DB] reopen failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /** @return array{status: RespondStatus, message?: string, responseId?: int} */
    public function respond(string $uuid, RespondToReportCommand $cmd, int $userId): array
    {
        return $this->respondToReport($uuid, $userId, $cmd->reponse, $cmd->nouvelEtat->value, $cmd->attachment);
    }

    /**
     * @return array{status: RespondStatus, message?: string, responseId?: int}
     */
    public function respondToReport(string $uuid, int $userId, string $reponse, string $nouvelEtat, ?AttachmentData $attachment = null): array
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
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
                // RGPD (B1) — un répondant anonymisé a repondant_id = NULL.
                // L'archive doit alors porter user_id = NULL (colonne nullable,
                // FK vers users(id)) et jamais 0 : 0 ne référence aucun
                // utilisateur, la FK échoue et toute la nouvelle réponse est
                // perdue. L'historique (texte de la réponse) est préservé.
                $archiveUserId = $repondantIdRaw !== null ? (int) $repondantIdRaw : null;
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
                $this->rollBackIfOwned($ownsTransaction);
                return ['status' => RespondStatus::Concurrent];
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
                ':attachment_blob' => $attachment?->blob,
                ':attachment_name' => $attachment?->name,
                ':attachment_mime' => $attachment?->mime,
            ]);

            // Identité d'occurrence : l'id de report_responses matérialise CETTE
            // réponse (une réponse ultérieure du même répondant aura un autre id).
            $responseId = (int) $this->pdo->lastInsertId();

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return ['status' => RespondStatus::Ok, 'responseId' => $responseId];
        } catch (Exception $e) {
            // Join-if-active : si on participe à la transaction d'un appelant
            // (action métier englobante), on NE rollback PAS nous-mêmes et on
            // rethrow — sinon l'appelant committerait une action partielle.
            if (!$ownsTransaction) {
                throw $e;
            }
            $this->rollBackIfOwned($ownsTransaction);
            // @silent-ok: converted to a typed RespondStatus::Error the caller must handle
            // (RespondStatus is a backed enum — a match on it without the Error case is a
            // PHPStan error), not a swallow.
            error_log('[SST-DB] respondToReport transaction failed: ' . $e->getMessage());
            return ['status' => RespondStatus::Error, 'message' => $e->getMessage()];
        }
    }

    /**
     * Annule la transaction seulement si CET appel l'a ouverte et qu'elle est
     * encore active (check isolé : PHPStan replierait un test inline).
     */
    private function rollBackIfOwned(bool $ownsTransaction): void
    {
        if ($ownsTransaction && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
