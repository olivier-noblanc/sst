<?php

/** Service registration — wires up the DI Container. */

use App\Container\Container;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Repository\SiteRepository;
use App\Repository\NotificationRepository;
use App\Repository\StatsRepository;
use App\Services\ReportService;
use App\Services\UserService;
use App\Services\AuthService;
use App\Services\SessionManager;
use App\Services\NotificationService;
use App\Services\BackupService;
use App\Services\AccessService;
use App\Services\ConfigService;
use App\Services\FormattingService;
use App\Services\CryptoService;
use App\Services\HttpService;
use App\Services\AssetService;
use App\Event\EventDispatcher;

function createContainer(): Container
{
    $container = new Container();

    // ═══════════════════════════════════════════════════════════════════════════════
    // Core infrastructure
    // ═══════════════════════════════════════════════════════════════════════════════

    $container->set(PDO::class, fn() => getDB());
    $container->set(EventDispatcher::class, fn() => new EventDispatcher());

    // ═══════════════════════════════════════════════════════════════════════════════
    // Repositories (require PDO)
    // ═══════════════════════════════════════════════════════════════════════════════

    $container->set(ReportRepository::class, fn($c) => new ReportRepository($c->get(PDO::class)));
    $container->set(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));
    $container->set(SiteRepository::class, fn($c) => new SiteRepository($c->get(PDO::class)));
    $container->set(NotificationRepository::class, fn($c) => new NotificationRepository($c->get(PDO::class)));
    $container->set(StatsRepository::class, fn($c) => new StatsRepository($c->get(PDO::class)));

    // ═══════════════════════════════════════════════════════════════════════════════
    // Services — standalone (no constructor dependencies)
    // ═══════════════════════════════════════════════════════════════════════════════

    $container->set(AccessService::class, fn() => new AccessService());
    $container->set(ConfigService::class, function () {
        $instance = new ConfigService();
        ConfigService::setInstance($instance);
        return $instance;
    });
    $container->set(FormattingService::class, fn() => new FormattingService());
    $container->set(CryptoService::class, fn() => new CryptoService());
    $container->set(HttpService::class, fn() => new HttpService());
    $container->set(AssetService::class, fn() => new AssetService());
    $container->set(SessionManager::class, fn() => new SessionManager());
    $container->set(NotificationService::class, fn() => new NotificationService());
    $container->set(BackupService::class, fn() => new BackupService());

    // ═══════════════════════════════════════════════════════════════════════════════
    // Services — with dependencies
    // ═══════════════════════════════════════════════════════════════════════════════

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
