<?php

/** NotificationRepository — Couche d'accès aux données pour les notifications email. */

namespace App\Repository;

use PDO;
use Throwable;

class NotificationRepository
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

    /** @return list<array{id: int, site_id: int, type: string, registry: ?string, email: string, created_at: string, site_code: ?string, site_nom: ?string}> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('
            SELECT ns.*, s.code as site_code, s.nom as site_nom
            FROM notification_settings ns
            LEFT JOIN sites s ON ns.site_id = s.id
            ORDER BY ns.type, s.code, ns.registry
        ');
        /** @var list<array{id: int, site_id: int, type: string, registry: ?string, email: string, created_at: string, site_code: ?string, site_nom: ?string}> $rows */
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return $rows;
    }

    public function save(?int $siteId, string $type, string $registry, string $email): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO notification_settings (site_id, type, registry, email)
            VALUES (:site_id, :type, :registry, :email)
        ');
        $stmt->execute([
            ':site_id'  => $siteId,
            ':type'     => $type,
            ':registry' => $registry,
            ':email'    => $email,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteByType(string $type): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM notification_settings WHERE type = :type');
        $stmt->execute([':type' => $type]);
        return $stmt->rowCount();
    }

    /**
     * Remplace la totalité des notifications d'un type de portée par les
     * entrées fournies, dans UNE transaction (tout ou rien).
     *
     * Correctif moyen (audit) — les onglets settings « sites » et « global »
     * (handlers/settings_handler.php) enchaînaient deleteByType() + N save()
     * hors transaction : un échec au milieu (FK site_id inexistant, disque
     * plein, perte de connexion) laissait la table dans un état partiel —
     * anciennes notifications déjà supprimées, nouvelles partiellement
     * insérées. Toute entrée invalide ici invalide l'ensemble : rollback
     * complet + rethrow de l'exception d'origine (crash hard, jamais
     * d'échec silencieux — AGENTS.md).
     *
     * Entrées vides = vide la portée (comportement existant : deleteByType
     * + 0 save), dans la même transaction.
     *
     * @param string $type Type de portée ('site'|'global') — valeur métier existante
     * @param list<array{site_id: ?int, email: string}> $entries
     *        site_id : NULL pour la portée global (vérité DB, jamais 0).
     *        registry : 'all' (valeur utilisée par les deux onglets settings).
     */
    public function replaceByType(string $type, array $entries): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->deleteByType($type);
            foreach ($entries as $entry) {
                $this->save($entry['site_id'], $type, 'all', $entry['email']);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<string> */
    public function findSiteEmails(int $siteId): array
    {
        $stmt = $this->pdo->prepare("SELECT email FROM notification_settings WHERE site_id = :site_id AND type = 'site'");
        $stmt->execute([':site_id' => $siteId]);
        return array_column($stmt->fetchAll(), 'email');
    }

    /** @return list<string> */
    public function findGlobalEmails(): array
    {
        $stmt = $this->pdo->query("SELECT email FROM notification_settings WHERE type = 'global'");
        return $stmt !== false ? array_column($stmt->fetchAll(), 'email') : [];
    }
}
