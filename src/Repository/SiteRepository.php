<?php

/** SiteRepository — Couche d'accès aux données pour les sites (Unités Régionales). */

namespace App\Repository;

use Exception;
use PDO;

class SiteRepository
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

    /** @return array<mixed, mixed> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM sites ORDER BY code ASC');
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    /** @return array<mixed, mixed> */
    public function findActive(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM sites WHERE is_active = 1 ORDER BY code ASC');
        $rows = $stmt !== false ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    /** @return array<mixed, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sites WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<mixed, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sites WHERE code = :code');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(string $code, string $nom, string $departement = ''): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO sites (code, nom, departement) VALUES (:code, :nom, :departement)');
        $stmt->execute([
            ':code'        => $code,
            ':nom'         => $nom,
            ':departement' => $departement,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $code, string $nom, string $departement = ''): bool
    {
        $stmt = $this->pdo->prepare('UPDATE sites SET code = :code, nom = :nom, departement = :departement WHERE id = :id');
        $stmt->execute([
            ':code'        => $code,
            ':nom'         => $nom,
            ':departement' => $departement,
            ':id'          => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function toggleActive(int $id, bool $active): bool
    {
        $stmt = $this->pdo->prepare('UPDATE sites SET is_active = :active WHERE id = :id');
        $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countUsers(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE site_id = :id AND is_active = 1');
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function countReports(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM reports WHERE site_id = :id');
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            if ($this->countUsers($id) > 0) {
                $this->pdo->rollBack();
                return false;
            }
            if ($this->countReports($id) > 0) {
                $this->pdo->rollBack();
                return false;
            }
            $stmt = $this->pdo->prepare('DELETE FROM notification_settings WHERE site_id = :id');
            $stmt->execute([':id' => $id]);
            $stmt = $this->pdo->prepare('DELETE FROM sites WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $deleted = $stmt->rowCount() > 0;
            $this->pdo->commit();
            return $deleted;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function countActiveSites(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM sites WHERE is_active = 1');
        if ($stmt === false) {
            return 0;
        }
        return (int) $stmt->fetchColumn();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
