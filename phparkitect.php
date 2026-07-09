<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $srcSet = ClassSet::fromDir(__DIR__ . '/src');

    // Helpers should not depend on query files
    $config->add(
        $srcSet,
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('SST\Helpers'))
            ->should(new \Arkitect\Expression\ForClasses\NotHaveDependencyOutsideNamespace('SST\Helpers'))
            ->because('Helpers are utility functions and should not depend on queries')
    );
};
