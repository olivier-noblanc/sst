<?php

/** ReportResponseRepository — Couche d'accès aux données pour les réponses des signalements. */

namespace App\Repository;

use PDO;

class ReportResponseRepository
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
     * @param list<string> $uuids
     * @return array<string, list<array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null}>>
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
                /** @var array{id: int, report_uuid: string, user_id: int|null, reponse: string|null, nouvel_etat: string|null, attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, created_at: string, nom: string|null, prenom: string|null} $resp */
                $result[$uuidValue][] = $resp;
            }
        }
        return $result;
    }
}
