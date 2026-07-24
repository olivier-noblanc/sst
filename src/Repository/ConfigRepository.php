<?php

/** ConfigRepository — Couche d'accès aux données pour la configuration. */

namespace App\Repository;

use PDO;

class ConfigRepository
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

    public function get(string $cle): ?string
    {
        $stmt = $this->pdo->prepare('SELECT valeur FROM config_app WHERE cle = :cle');
        $stmt->execute([':cle' => $cle]);
        $result = $stmt->fetchColumn();
        return ($result !== false && $result !== null && $result !== '') ? (string) $result : null;
    }

    public function set(string $cle, string $valeur): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO config_app (cle, valeur, type, categorie, libelle, modifiable)
            VALUES (:cle, :valeur, "", "", "", 1)
            ON CONFLICT(cle) DO UPDATE SET valeur = :valeur2, updated_at = datetime("now")');
        $stmt->execute([':cle' => $cle, ':valeur' => $valeur, ':valeur2' => $valeur]);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
