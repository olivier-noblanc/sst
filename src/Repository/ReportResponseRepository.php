<?php

/** ReportResponseRepository — Couche d'accès aux données pour les réponses des signalements. */

namespace App\Repository;

use PDO;

class ReportResponseRepository
{
    /**
     * Taille max d'une liste IN (?,...) en variables SQL.
     * 999 reste sous la limite des vieux builds SQLite (999 variables) ET des
     * builds récents (32 766 depuis SQLite 3.32) — cf. bug exp-7 : un seul
     * IN sur l'export (jusqu'à 50 000 uuids) dépassait la limite en prepares
     * natifs (PDO::ATTR_EMULATE_PREPARES = false) → "too many SQL variables".
     * Valeur injectable au constructeur pour tester les frontières de chunks.
     */
    public const int CHUNK_SIZE = 999;

    public function __construct(
        private readonly PDO $pdo,
        int $chunkSize = self::CHUNK_SIZE,
    ) {
        $this->chunkSize = max(1, $chunkSize);
    }

    private readonly int $chunkSize;

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

    /** @return list<array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null}> */
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
        /** @var list<array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null}> $rows */
        return $rows;
    }

    /**
     * Réponses groupées par report_uuid pour une liste (potentiellement massive)
     * d'uuids — chemin de l'export CSV (jusqu'à EXPORT_MAX_ROWS = 50 000 uuids).
     *
     * Fix exp-7 : un seul IN (?,...) dépassait la limite de variables SQLite en
     * prepares natifs (SQLITE_MAX_VARIABLE_NUMBER, 32 766 par défaut) dès ~33k
     * uuids → PDOException "too many SQL variables" et export en échec. La liste
     * est donc découpée en chunks bornés (CHUNK_SIZE) et les résultats mergés :
     *  - chaque uuid tombe dans exactement un chunk → ni perte ni doublon ;
     *  - les doublons d'input sont dédupliqués au préalable (array_unique) :
     *    un uuid dupliqué tomberait sinon dans deux chunks et dupliquerait ses
     *    lignes — l'IN(...) mono-requête ne les dupliquait pas ;
     *  - l'ordre intra-uuid (created_at ASC) est préservé : chaque chunk trie
     *    identiquement et le groupage par uuid rend l'ordre inter-chunks sans
     *    effet sur le résultat indexé ;
     *  - aucune erreur DB n'est avalée : les PDOException remontent telles quelles.
     *
     * @param list<string> $uuids
     * @return array<string, list<array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null}>>
     */
    public function getResponsesForUuids(array $uuids): array
    {
        if (empty($uuids)) {
            return [];
        }
        $uniqueUuids = array_values(array_unique($uuids));
        $result = [];
        foreach (array_chunk($uniqueUuids, $this->chunkSize) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->pdo->prepare("
                SELECT rr.*, rr.report_uuid, u.nom, u.prenom
                FROM report_responses rr
                LEFT JOIN users u ON rr.user_id = u.id
                WHERE rr.report_uuid IN ($placeholders)
                ORDER BY rr.created_at ASC
            ");
            $stmt->execute($chunk);
            while ($resp = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($resp)) {
                    continue;
                }
                $uuidValue = $resp['report_uuid'] ?? null;
                if (is_string($uuidValue) && $uuidValue !== '') {
                    /** @var array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null} $resp */
                    $result[$uuidValue][] = $resp;
                }
            }
        }
        return $result;
    }
}
