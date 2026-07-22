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

    $container->set(ReportRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new ReportRepository($pdo);
    });
    $container->set(UserRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new UserRepository($pdo);
    });
    $container->set(SiteRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new SiteRepository($pdo);
    });
    $container->set(NotificationRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new NotificationRepository($pdo);
    });
    $container->set(StatsRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new StatsRepository($pdo);
    });

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


    // ═══════════════════════════════════════════════════════════════════════════════
    // Services — with dependencies
    // ═══════════════════════════════════════════════════════════════════════════════

    $container->set(ReportService::class, function (Container $c) {
        /** @var ReportRepository $repo */ $repo = $c->get(ReportRepository::class);
        /** @var EventDispatcher $events */ $events = $c->get(EventDispatcher::class);
        return new ReportService($repo, $events);
    });
    $container->set(UserService::class, function (Container $c) {
        /** @var UserRepository $repo */ $repo = $c->get(UserRepository::class);
        /** @var EventDispatcher $events */ $events = $c->get(EventDispatcher::class);
        return new UserService($repo, $events);
    });
    $container->set(AuthService::class, function (Container $c) {
        /** @var UserRepository $repo */ $repo = $c->get(UserRepository::class);
        /** @var EventDispatcher $events */ $events = $c->get(EventDispatcher::class);
        return new AuthService($repo, $events);
    });

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
