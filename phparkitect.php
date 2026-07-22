<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\DependsOnlyOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $srcSet = ClassSet::fromDir(__DIR__ . '/src');

    // DTOs are pure data carriers — no business logic, no I/O, nothing that
    // would make a command/filter object hard to construct in a test
    // without a database or HTTP context.
    $config->add(
        $srcSet,
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('App\DTO'))
            ->should(new DependsOnlyOnTheseNamespaces(['App\DTO', 'App\Enum']))
            ->because('DTOs are pure data carriers and must stay constructible without a database or HTTP context')
    );

    // Enums are pure value types — no dependencies on anything else in the
    // app (verified true of the current codebase; this rule exists to keep
    // it that way, not because a violation was found).
    $config->add(
        $srcSet,
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('App\Enum'))
            ->should(new DependsOnlyOnTheseNamespaces(['App\Enum']))
            ->because('Enums are pure value types and must not depend on anything else')
    );

    // The data layer (Repository) must stay independent of the HTTP layer
    // (Router, Middleware) — a repository should be usable from a CLI
    // script or a test with no request in flight.
    $config->add(
        $srcSet,
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('App\Repository'))
            ->should(new NotDependsOnTheseNamespaces(['App\Router', 'App\Middleware']))
            ->because('Repositories must stay usable outside an HTTP request (CLI scripts, tests) — they should not know about routing or middleware')
    );

    // Business logic (Services) must not reach into routing/middleware
    // plumbing directly — that's the handler/router's job to orchestrate,
    // not the service's.
    $config->add(
        $srcSet,
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('App\Services'))
            ->should(new NotDependsOnTheseNamespaces(['App\Router', 'App\Middleware']))
            ->because('Services implement business logic and must not depend on how a request got routed to them')
    );
};
