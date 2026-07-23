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
        ],
    ]);
