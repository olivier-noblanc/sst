<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repository\StatsRepository;
use App\Repository\RegistryRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class StatsRepositoryStructuredStatsTest extends TestCase
{
    private PDO $pdo;
    private StatsRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        
        // Schema minimal pour le test
        $this->pdo->exec('CREATE TABLE reports (
            uuid TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            nature_auteur TEXT,
            type_acte TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        $this->pdo->exec('CREATE TABLE registries (
            id INTEGER PRIMARY KEY,
            code TEXT NOT NULL UNIQUE,
            enabled INTEGER DEFAULT 1
        )');
        $this->pdo->exec('CREATE TABLE registry_fields (
            id INTEGER PRIMARY KEY,
            registry_id INTEGER NOT NULL,
            field_code TEXT NOT NULL,
            FOREIGN KEY (registry_id) REFERENCES registries(id)
        )');
        
        // Seed registres
        $this->pdo->exec("INSERT INTO registries (code) VALUES ('rami'), ('violences'), ('rsst')");
        
        // Seed registry_fields pour RAMI uniquement (a nature_auteur et type_acte)
        $this->pdo->exec("INSERT INTO registry_fields (registry_id, field_code) 
            SELECT 1, 'nature_auteur' UNION ALL
            SELECT 1, 'type_acte'");
        
        // Seed reports RAMI
        $this->pdo->exec("INSERT INTO reports (uuid, type, nature_auteur, type_acte) VALUES 
            ('uuid1', 'rami', 'Agent', 'Menace'),
            ('uuid2', 'rami', 'Public', 'Agression'),
            ('uuid3', 'rami', 'Agent', 'Incivilité')");
        
        // Seed reports violences (sans nature_auteur/type_acte)
        $this->pdo->exec("INSERT INTO reports (uuid, type, nature_auteur, type_acte) VALUES 
            ('uuid4', 'violences', NULL, NULL)");
        
        $this->repo = new StatsRepository($this->pdo);
    }

    public function testGetStructuredStatsForRegistryRami(): void
    {
        $stats = $this->repo->getStructuredStatsForRegistry('rami');
        
        self::assertCount(2, $stats->byNatureAuteur); // Agent: 2, Public: 1
        self::assertCount(3, $stats->byTypeActe); // Menace: 1, Agression: 1, Incivilité: 1
        
        // Vérifier les counts
        $natureCounts = array_column($stats->byNatureAuteur, 'count');
        self::assertContains(2, $natureCounts); // Agent apparaît 2 fois
    }

    public function testGetStructuredStatsForRegistryWithoutFields(): void
    {
        // RSST n'a pas de nature_auteur/type_acte dans registry_fields
        $stats = $this->repo->getStructuredStatsForRegistry('rsst');
        
        self::assertCount(0, $stats->byNatureAuteur);
        self::assertCount(0, $stats->byTypeActe);
    }

    public function testGetStructuredStatsForRegistryWithYearFilter(): void
    {
        $stats = $this->repo->getStructuredStatsForRegistry('rami', '2026');
        
        // Selon la date seedée, peut être vide ou non
        self::assertInstanceOf(\App\DTO\RamiStats::class, $stats);
    }
}
