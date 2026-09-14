<?php

/** RegistryFieldValueRepository — Lecture des valeurs soumises des champs dynamiques de registre. */

namespace App\Repository;

use PDO;

class RegistryFieldValueRepository
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
     * Valeurs des champs dynamiques d'un signalement, indexées par field_code.
     *
     * Les lignes à valeur NULL ("non renseigné") sont ignorées : la clé
     * absente EST la donnée "pas de valeur". L'écriture vit dans
     * ReportWriteRepository (même transaction que le signalement).
     *
     * @return array<string, string> field_code => value
     */
    public function findByReport(string $reportUuid): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT field_code, value FROM registry_field_values
             WHERE report_uuid = :uuid AND value IS NOT NULL
             ORDER BY field_code ASC'
        );
        $stmt->execute([':uuid' => $reportUuid]);
        /** @var array<string, string> $values */
        $values = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $values;
    }
}
