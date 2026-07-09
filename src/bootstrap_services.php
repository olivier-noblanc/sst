<?php
/** Service registration — wires up the DI Container. */

use App\Container\Container;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Services\ReportService;
use App\Services\UserService;
use App\Services\AuthService;
use App\Services\SessionManager;
use App\Event\EventDispatcher;

function createContainer(): Container
{
    $container = new Container();

    $container->set(PDO::class, fn() => getDB());
    $container->set(EventDispatcher::class, fn() => new EventDispatcher());

    // Repositories
    $container->set(ReportRepository::class, fn($c) => new ReportRepository($c->get(PDO::class)));
    $container->set(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));

    // Services
    $container->set(ReportService::class, fn($c) => new ReportService(
        $c->get(ReportRepository::class),
        $c->get(EventDispatcher::class)
    ));
    $container->set(UserService::class, fn($c) => new UserService(
        $c->get(UserRepository::class),
        $c->get(EventDispatcher::class)
    ));
    $container->set(AuthService::class, fn($c) => new AuthService(
        $c->get(UserRepository::class),
        $c->get(EventDispatcher::class)
    ));
    $container->set(SessionManager::class, fn() => new SessionManager());

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
