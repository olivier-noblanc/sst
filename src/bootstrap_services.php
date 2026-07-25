<?php

/** Service registration — wires up the DI Container. */

use App\Container\Container;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Repository\SiteRepository;
use App\Repository\NotificationRepository;
use App\Repository\StatsRepository;
use App\Repository\RegistryRepository;
use App\Repository\RegistryFieldRepository;
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
use App\Services\RegistryCardService;
use App\Services\StatisticsService;
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
    $container->set(RegistryRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new RegistryRepository($pdo);
    });
    $container->set(RegistryFieldRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new RegistryFieldRepository($pdo);
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
    $container->set(RegistryCardService::class, function (Container $c) {
        /** @var RegistryRepository $registryRepo */ $registryRepo = $c->get(RegistryRepository::class);
        /** @var ReportRepository $reportRepo */ $reportRepo = $c->get(ReportRepository::class);
        /** @var AccessService $accessService */ $accessService = $c->get(AccessService::class);
        return new RegistryCardService($registryRepo, $reportRepo, $accessService);
    });
    $container->set(StatisticsService::class, function (Container $c) {
        /** @var StatsRepository $statsRepo */ $statsRepo = $c->get(StatsRepository::class);
        return new StatisticsService($statsRepo);
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
