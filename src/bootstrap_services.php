<?php
/** Service registration — wires up the DI Container. */

use App\Container\Container;
use App\Repository\ReportRepository;
use App\Services\ReportService;
use App\Event\EventDispatcher;

function createContainer(): Container
{
    $container = new Container();

    $container->set(PDO::class, fn() => getDB());
    $container->set(EventDispatcher::class, fn() => new EventDispatcher());
    $container->set(ReportRepository::class, fn($c) => new ReportRepository($c->get(PDO::class)));
    $container->set(ReportService::class, fn($c) => new ReportService(
        $c->get(ReportRepository::class),
        $c->get(EventDispatcher::class)
    ));

    return $container;
}

function getContainer(): Container
{
    static $container = null;
    if ($container === null) {
        $container = createContainer();
    }
    return $container;
}
