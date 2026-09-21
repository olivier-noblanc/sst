<?php

/**
 * PurgeService — Purge supervisée des signalements (onglet « Maintenance »).
 *
 * Garde-fou « sentinelle » : la purge n'est autorisée que si le fichier
 * erase.txt existe à la racine de l'application. Ce fichier est déposé
 * volontairement par un technicien pour armer la purge, et supprimé
 * automatiquement après un succès complet — jamais après un échec.
 *
 * Toute la logique SQL reste dans PurgeRepository
 * (NoSqlOutsideRepositoryRule) ; ce service orchestre et gère la sentinelle.
 */

namespace App\Services;

use App\Repository\AuditRepository;
use App\Repository\PurgeRepository;
use RuntimeException;

final readonly class PurgeService
{
    /** Nom du fichier sentinelle attendu à la racine. */
    public const string MARKER_FILENAME = 'erase.txt';

    public function __construct(
        private PurgeRepository $repository,
        private AuditRepository $audit,
        private ?string $markerPath = null,
    ) {}

    /**
     * Chemin de la sentinelle : racine du projet par défaut. Surchargeable
     * (tests) pour ne jamais toucher un éventuel fichier réel à la racine.
     */
    public function markerPath(): string
    {
        return $this->markerPath
            ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::MARKER_FILENAME;
    }

    /** La purge est-elle armée (sentinelle présente) ? */
    public function isArmed(): bool
    {
        return is_file($this->markerPath());
    }

    /**
     * Exécute la purge applicative FK-safe puis supprime la sentinelle.
     *
     * @return array<string, int> Nombre de lignes supprimées par table.
     *
     * @throws PurgeNotArmedException si la sentinelle est absente.
     * @throws RuntimeException       si la sentinelle n'a pas pu être supprimée.
     */
    public function purgeAll(): array
    {
        if (!$this->isArmed()) {
            throw new PurgeNotArmedException(
                'Purge refusée : le fichier ' . self::MARKER_FILENAME . ' est absent à la racine.'
            );
        }

        $counts = $this->repository->purgeReportData();

        $this->audit->log(
            category: 'maintenance',
            action: 'purge_reports',
            details: sprintf(
                'Purge supervisée : %d signalement(s) et données liées, %d message(s) outbox et %d session(s) supprimés',
                $counts['reports'] ?? 0,
                $counts['email_outbox'] ?? 0,
                $counts['sessions'] ?? 0,
            ),
            targetType: 'system',
            context: $counts,
        );

        $marker = $this->markerPath();
        if (is_file($marker) && !unlink($marker)) {
            throw new RuntimeException(
                'Purge effectuée mais le fichier ' . self::MARKER_FILENAME . ' n\'a pas pu être supprimé.'
            );
        }

        return $counts;
    }
}
