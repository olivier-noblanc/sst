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
use App\Repository\AuditRepository;
use App\Repository\SessionRepository;
use App\Repository\ConfigRepository;
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
use App\Services\RegistryPolicy;
use App\Services\ReportStateMachine;
use App\Services\CronService;
use App\Event\EventDispatcher;

require_once __DIR__ . '/Event/event_listeners.php';

function createContainer(): Container
{
    $container = new Container();

    // ═══════════════════════════════════════════════════════════════════════════════
    // Core infrastructure
    // ═══════════════════════════════════════════════════════════════════════════════

    $container->set(PDO::class, fn() => getDB());
    $container->set(EventDispatcher::class, function (Container $c) {
        $events = new EventDispatcher();
        // Audit #1 — wire les listeners en production (avant ce fix, 0 listener).
        registerEventListeners($events, $c);
        return $events;
    });

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
    $container->set(AuditRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new AuditRepository($pdo);
    });
    $container->set(SessionRepository::class, function (Container $c) { /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new SessionRepository($pdo);
    });

    // ═══════════════════════════════════════════════════════════════════════════════
    // Services — standalone (no constructor dependencies)
    // ═══════════════════════════════════════════════════════════════════════════════

    $container->set(AccessService::class, fn() => new AccessService());
    $container->set(ConfigService::class, fn() => new ConfigService());
    $container->set(FormattingService::class, fn() => new FormattingService());
    $container->set(CryptoService::class, fn() => new CryptoService());
    $container->set(HttpService::class, fn() => new HttpService());
    $container->set(AssetService::class, fn() => new AssetService());
    $container->set(SessionManager::class, fn() => new SessionManager());
    $container->set(RegistryPolicy::class, fn() => new RegistryPolicy());
    $container->set(ReportStateMachine::class, fn() => new ReportStateMachine());


    // ═══════════════════════════════════════════════════════════════════════════════
    // Services — with dependencies
    // ═══════════════════════════════════════════════════════════════════════════════

    $container->set(NotificationService::class, function (Container $c) {
        /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        return new NotificationService($pdo);
    });

    $container->set(ReportService::class, function (Container $c) {
        /** @var ReportRepository $repo */ $repo = $c->get(ReportRepository::class);
        /** @var EventDispatcher $events */ $events = $c->get(EventDispatcher::class);
        /** @var ReportStateMachine $stateMachine */ $stateMachine = $c->get(ReportStateMachine::class);
        return new ReportService($repo, $events, $stateMachine);
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
    $container->set(CronService::class, function (Container $c) {
        /** @var PDO $pdo */ $pdo = $c->get(PDO::class);
        /** @var ConfigRepository $configRepo */ $configRepo = $c->get(ConfigRepository::class);
        /** @var ReportRepository $reportRepo */ $reportRepo = $c->get(ReportRepository::class);
        /** @var AuditRepository $auditRepo */ $auditRepo = $c->get(AuditRepository::class);
        /** @var SessionRepository $sessionRepo */ $sessionRepo = $c->get(SessionRepository::class);
        return new CronService($pdo, $configRepo, $reportRepo, $auditRepo, $sessionRepo);
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
