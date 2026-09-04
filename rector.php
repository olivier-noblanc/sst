<?php

declare(strict_types=1);

use App\Rector\ReplaceMagicStringWithEnumRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/pages',
        __DIR__ . '/templates',
        __DIR__ . '/handlers',
    ])
    ->withSkip([
        __DIR__ . '/src/lib',
        __DIR__ . '/vendor',
        __DIR__ . '/tests',
        __DIR__ . '/src/Enum',
        __DIR__ . '/src/Rector',
        __DIR__ . '/src/PHPStan',
    ])
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
    ])
    ->withPhpSets(php85: true)
    ->withImportNames()
    ->withRules([
        ReplaceMagicStringWithEnumRector::class,
    ])
    ->withConfiguredRule(ReplaceMagicStringWithEnumRector::class, [
        'stringToEnum' => [
            // VisibilityMode
            'confidential' => 'App\Enum\VisibilityMode::Confidential',
            'agent_choice' => 'App\Enum\VisibilityMode::AgentChoice',
            'public'       => 'App\Enum\VisibilityMode::Public',
            // ReportType
            'rsst' => 'App\Enum\ReportType::Rsst',
            'rami' => 'App\Enum\ReportType::Rami',
            'dgi'  => 'App\Enum\ReportType::Dgi',
            // ReportState
            'nouveau'   => 'App\Enum\ReportState::Nouveau',
            'en_cours'  => 'App\Enum\ReportState::EnCours',
            'traite'    => 'App\Enum\ReportState::Traite',
            'reouvert'  => 'App\Enum\ReportState::Reouvert',
            'abandonne' => 'App\Enum\ReportState::Abandonne',
            // UserRole (oracle P1) — 'error'/'ok'/'concurrent' (RespondStatus)
            // volontairement NON mappés : collision avec le type de flash UI
            // setFlash('error', …) → la réécriture automatique corromprait les
            // appels. RespondStatus est comparé exclusivement via l'enum.
            'agent'       => 'App\Enum\UserRole::Agent',
            'superviseur' => 'App\Enum\UserRole::Superviseur',
            'chsct'       => 'App\Enum\UserRole::Chsct',
        ],
        'constToEnum' => [
            // UserRole constants
            'ROLE_AGENT'       => 'App\Enum\UserRole::Agent',
            'ROLE_SUPERVISEUR' => 'App\Enum\UserRole::Superviseur',
            'ROLE_CHSCT'       => 'App\Enum\UserRole::Chsct',
            // ReportState constants
            'ETAT_NOUVEAU'   => 'App\Enum\ReportState::Nouveau',
            'ETAT_EN_COURS'  => 'App\Enum\ReportState::EnCours',
            'ETAT_TRAITE'    => 'App\Enum\ReportState::Traite',
            'ETAT_REOUVERT'  => 'App\Enum\ReportState::Reouvert',
            'ETAT_ABANDONNE' => 'App\Enum\ReportState::Abandonne',
        ],
    ]);
