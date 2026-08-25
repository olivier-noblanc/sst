<?php

/** ReportAttachmentRepository — Couche d'accès aux données pour les pièces jointes des signalements et des réponses. */

namespace App\Repository;

use PDO;

class ReportAttachmentRepository
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
     * Fetch only the attachment blob for a report (used by print page).
     *
     * @return array{attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null}|null
     */
    public function getAttachmentBlob(string $uuid): ?array
    {
        if (!isValidUuid($uuid)) {
            return null;
        }
        $stmt = $this->pdo->prepare('
            SELECT attachment_blob, attachment_name, attachment_mime
            FROM reports WHERE uuid = :uuid
        ');
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Get response attachment by response ID.
     *
     * @return array{attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, report_uuid: string}|null
     */
    public function getResponseAttachmentById(int $responseId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT rr.attachment_blob, rr.attachment_name, rr.attachment_mime, rr.report_uuid
            FROM report_responses rr
            WHERE rr.id = :id
        ');
        $stmt->execute([':id' => $responseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        /** @var array{attachment_blob: string|null, attachment_name: string|null, attachment_mime: string|null, report_uuid: string}|null $row */
        return is_array($row) ? $row : null;
    }
}
