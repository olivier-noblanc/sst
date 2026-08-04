<?php

/**
 * SitesStatsView — View model pour le tableau des statistiques par site.
 *
 * Agrège les données de SiteStatsRow avec la liste des sites et registres
 * pour produire des lignes de tableau prêtes à l'affichage, avec totaux calculés.
 */

namespace App\DTO;

class SitesStatsView
{
    /**
     * @param list<SiteStatsRow> $statsBySite
     * @param list<array{id: int, code: string, nom: string}> $sites
     * @param list<string> $registryCodes Codes des registres actifs
     */
    public function __construct(
        private readonly array $statsBySite,
        private readonly array $sites,
        private readonly array $registryCodes,
    ) {}

    /**
     * Retourne les lignes du tableau, chaque ligne ayant les clés :
     *   code, nom, total, + une clé par code de registre.
     *
     * @return list<array{code: string, nom: string, total: int, registryCounts: array<string, int>}>
     */
    public function getRows(): array
    {
        // Indexer les stats par code de site pour lookup O(1)
        $statsIndex = [];
        foreach ($this->statsBySite as $s) {
            $statsIndex[$s->code] = $s;
        }

        $rows = [];
        foreach ($this->sites as $site) {
            $siteCode = $site['code'];
            $matched = $statsIndex[$siteCode] ?? null;

            $row = [
                'code' => $siteCode,
                'nom' => $site['nom'],
                'total' => $matched !== null ? $matched->total : 0,
                'registryCounts' => [],
            ];

            foreach ($this->registryCodes as $code) {
                $row['registryCounts'][$code] = $matched !== null ? $matched->getCount($code) : 0;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Total pour un registre donné (tous sites confondus).
     */
    public function getTotalByRegistry(string $registryCode): int
    {
        $total = 0;
        foreach ($this->statsBySite as $s) {
            $total += $s->getCount($registryCode);
        }
        return $total;
    }

    /**
     * Total général (tous registres, tous sites).
     */
    public function getGrandTotal(): int
    {
        $total = 0;
        foreach ($this->statsBySite as $s) {
            $total += $s->total;
        }
        return $total;
    }

    /**
     * Vrai si la cellule (registre × site) a une valeur positive.
     */
    public function isCellPositive(string $registryCode, string $siteCode): bool
    {
        foreach ($this->statsBySite as $s) {
            if ($s->code === $siteCode) {
                return $s->getCount($registryCode) > 0;
            }
        }
        return false;
    }
}
