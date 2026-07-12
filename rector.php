<?php

declare(strict_types=1);

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
    ])
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
    ])
    ->withPhpSets(php85: true)
    ->withImportNames();
