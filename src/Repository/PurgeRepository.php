<?php

/** PurgeRepository — Couche d'accès aux données pour la purge supervisée. */

namespace App\Repository;

use PDO;
use Throwable;

/**
 * Purge applicative des signalements et de leurs données liées.
 *
 * La liste ordonnée `REPORT_PURGE_TABLES` est la version factorisée et
 * corrigée de la séquence historique de `nuclear-reset.php` : elle est
 * FK-safe (enfants avant parents) et inclut désormais `registry_field_values`,
 * que le script CLI oubliait (valeurs de champs de registre orphelines après
 * une purge).
 */
class PurgeRepository
{
    /**
     * Ordre FK-safe (enfants avant parents) des tables liées aux signalements,
     * partagé avec nuclear-reset.php.
     *
     * @var list<string>
     */
    public const REPORT_PURGE_TABLES = [
        'report_agent_invites',
        'report_access_log',
        'report_state_history',
        'report_agents',
        'registry_field_values',
        'report_responses',
        'reports',
        'report_sequence',
        'audit_log',
    ];

    /**
     * Tables purgées par la purge web en plus des données de signalement.
     *
     * @var list<string>
     */
    public const ADDITIONAL_PURGE_TABLES = [
        'email_outbox',
        'sessions',
    ];

    public function __construct(
        private readonly PDO $pdo
    ) {}

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
     * Purge transactionnelle : signalements + données liées + audit_log,
     * puis outbox e-mail et sessions. Conservés : users, sites, config_app,
     * registries et registry_fields (définitions de registres).
     *
     * @return array<string, int> Nombre de lignes supprimées par table.
     */
    public function purgeReportData(): array
    {
        $counts = [];

        $this->pdo->beginTransaction();

        try {
            $tables = [...self::REPORT_PURGE_TABLES, ...self::ADDITIONAL_PURGE_TABLES];
            foreach ($tables as $table) {
                $counts[$table] = $this->deleteAllRows($table);
            }

            $this->resetSequences($tables);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $counts;
    }

    private function deleteAllRows(string $table): int
    {
        $deleted = $this->pdo->exec('DELETE FROM ' . $table);

        return $deleted === false ? 0 : $deleted;
    }

    /**
     * Réinitialise les compteurs AUTOINCREMENT des tables vidées (équivalent
     * nuclear-reset). No-op si la base n'a jamais créé de table AUTOINCREMENT
     * (sqlite_sequence absent).
     *
     * @param list<string> $tables
     */
    private function resetSequences(array $tables): void
    {
        $sequenceTable = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'"
        );
        if ($sequenceTable === false || $sequenceTable->fetchColumn() === false) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($tables), '?'));
        $stmt = $this->pdo->prepare('DELETE FROM sqlite_sequence WHERE name IN (' . $placeholders . ')');
        $stmt->execute($tables);
    }
}
