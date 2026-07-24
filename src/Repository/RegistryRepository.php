<?php

/** RegistryRepository — Couche d'accès aux données pour les registres. */

namespace App\Repository;

use App\Enum\ReportType;
use App\Enum\VisibilityMode;
use PDO;

class RegistryRepository
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

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM registries ORDER BY sort_order ASC, code ASC');
        if ($stmt === false) {
            return [];
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function findEnabled(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM registries WHERE is_enabled = 1 ORDER BY sort_order ASC, code ASC');
        if ($stmt === false) {
            return [];
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registries WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registries WHERE code = :code');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO registries (code, label, short_label, description, icon, color_theme,
                is_enabled, is_system, sort_order, default_visibility, notify_chsct, legal_note)
            VALUES (:code, :label, :short_label, :description, :icon, :color_theme,
                :is_enabled, :is_system, :sort_order, :default_visibility, :notify_chsct, :legal_note)
        ');
        $stmt->execute([
            ':code'               => $data['code'],
            ':label'              => $data['label'],
            ':short_label'        => $data['short_label'],
            ':description'        => $data['description'] ?? null,
            ':icon'               => $data['icon'] ?? '📋',
            ':color_theme'        => $data['color_theme'] ?? ReportType::Rsst->value,
            ':is_enabled'         => $data['is_enabled'] ?? 1,
            ':is_system'          => $data['is_system'] ?? 0,
            ':sort_order'         => $data['sort_order'] ?? 0,
            ':default_visibility' => $data['default_visibility'] ?? VisibilityMode::AgentChoice->value,
            ':notify_chsct'       => $data['notify_chsct'] ?? 0,
            ':legal_note'         => $data['legal_note'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [':id' => $id];
        foreach (['label', 'short_label', 'description', 'icon', 'color_theme',
            'is_enabled', 'is_system', 'sort_order', 'default_visibility',
            'notify_chsct', 'legal_note'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        if (empty($sets)) {
            return false;
        }
        $sets[] = "updated_at = datetime('now')";
        $sql = 'UPDATE registries SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function toggleEnabled(int $id, bool $enabled): bool
    {
        return $this->update($id, ['is_enabled' => $enabled ? 1 : 0]);
    }

    public function delete(int $id): bool
    {
        $reg = $this->findById($id);
        if ($reg === null || (int) $reg['is_system'] === 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM registries WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countByCode(string $code): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM registries WHERE code = :code');
        $stmt->execute([':code' => $code]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Seed the 3 default registres (RSST, RAMI, DGI) if not already present.
     */
    public function seedDefaults(): void
    {
        $defaults = [
            [
                'code' => ReportType::Rsst->value, 'label' => 'Registre de Santé et de Sécurité au Travail',
                'short_label' => 'RSST', 'description' => 'Risques liés aux locaux, équipements, ergonomie, conditions environnementales',
                'icon' => '📋', 'color_theme' => ReportType::Rsst->value, 'is_system' => 1, 'sort_order' => 1,
                'default_visibility' => VisibilityMode::Public->value, 'legal_note' => 'Décret n° 82-453 art. 3-2 : registre consultable par tout agent.',
            ],
            [
                'code' => ReportType::Rami->value, 'label' => 'Registre des Actes d\'Agressions, de Menaces et d\'Incivilités',
                'short_label' => 'RAMI', 'description' => 'Agressions physiques ou verbales, menaces, incivilités, harcèlement',
                'icon' => '⚠️', 'color_theme' => ReportType::Rami->value, 'is_system' => 0, 'sort_order' => 2,
                'default_visibility' => VisibilityMode::AgentChoice->value, 'legal_note' => 'Données sensibles (art. 9 RGPD) : le mode confidentiel ou choix de l\'agent est recommandé.',
            ],
            [
                'code' => ReportType::Dgi->value, 'label' => 'Registre de signalement d\'un Danger Grave et Imminent',
                'short_label' => 'DGI', 'description' => 'Danger nécessitant une action immédiate, droit de retrait',
                'icon' => '🔴', 'color_theme' => ReportType::Dgi->value, 'is_system' => 0, 'sort_order' => 3,
                'default_visibility' => VisibilityMode::AgentChoice->value, 'notify_chsct' => 1,
                'legal_note' => 'Articles L4131-1 et D4132-1 du Code du travail.',
            ],
        ];
        foreach ($defaults as $data) {
            if ($this->countByCode($data['code']) === 0) {
                $this->create($data);
            }
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get all available CSS theme keys.
     * @return list<string>
     */
    public static function availableThemes(): array
    {
        return array_merge(
            array_map(fn(ReportType $t) => $t->value, ReportType::cases()),
            ['vert', 'violet', 'orange', 'teal', 'indigo', 'rose', 'ambre'],
        );
    }

    /**
     * Get CSS class names for a given theme.
     * @return array{card: string, badge: string, btn: string, registry_card: string, indicateur: string, synthesis_th: string, text: string, border_left: string}
     */
    public static function themeClasses(string $theme): array
    {
        return [
            'card'          => "card--$theme",
            'badge'         => "badge--$theme",
            'btn'           => "btn--$theme",
            'registry_card' => "registry-card--$theme",
            'indicateur'    => "indicateur-card--$theme",
            'synthesis_th'  => "synthesis-th--$theme",
            'text'          => "text--$theme",
            'border_left'   => "border-left--$theme",
        ];
    }
}
