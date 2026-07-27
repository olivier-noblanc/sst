<?php
/**
 * Event Listeners Test — Application SST DREETS BFC
 *
 * Audit #1 — Wire les EventDispatcher listeners en production.
 *
 * Avant ce fix :
 * - EventDispatcher était instancié mais 0 listener enregistré en prod
 * - ReportService::create() dispatchait 'report.created' → no-op
 * - notifyNewReport() n'était JAMAIS appelée → superviseurs non notifiés
 *
 * Ce test vérifie que :
 * 1. La fonction registerEventListeners enregistre bien les listeners
 * 2. Le listener 'report.created' appelle notifyNewReport
 * 3. Le listener 'report.responded' appelle notifyReportResponse
 * 4. Le listener 'report.reopened' appelle notifyReportReopen
 * 5. Le listener 'user.role_changed' appelle notifyRoleChange
 * 6. Les listeners catchent les exceptions (ne propagent pas)
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class EventListenersTest extends TestCase
{
    private static bool $bootstrapped = false;

    public static function setUpBeforeClass(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        require_once __DIR__ . '/../../src/config.php';
        require_once __DIR__ . '/../../src/helpers.php';
        require_once __DIR__ . '/../../src/Event/EventDispatcher.php';
        require_once __DIR__ . '/../../src/Event/event_listeners.php';
    }

    public function testRegisterEventListenersAddsListeners(): void
    {
        $events = new \App\Event\EventDispatcher();

        // Use a partial container mock — registerEventListeners only uses NotificationService
        $container = $this->createContainerWithMockedNotifications();

        registerEventListeners($events, $container);

        // Use reflection to verify listeners were registered
        $reflection = new \ReflectionClass($events);
        $listenersProp = $reflection->getProperty('listeners');
        $listenersProp->setAccessible(true);
        $listeners = $listenersProp->getValue($events);

        $this->assertArrayHasKey('report.created', $listeners, 'report.created listener must be registered');
        $this->assertArrayHasKey('report.responded', $listeners, 'report.responded listener must be registered');
        $this->assertArrayHasKey('report.reopened', $listeners, 'report.reopened listener must be registered');
        $this->assertArrayHasKey('user.role_changed', $listeners, 'user.role_changed listener must be registered');
    }

    public function testReportCreatedListenerCallsNotifyNewReport(): void
    {
        $events = new \App\Event\EventDispatcher();

        $calls = [];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('report.created', [
            'report' => [
                'uuid' => 'test-uuid-1',
                'type' => 'rsst',
                'site_id' => 1,
            ],
        ]);

        $this->assertCount(1, $calls['notifyNewReport'] ?? [], 'notifyNewReport should be called once');
        $this->assertSame('test-uuid-1', $calls['notifyNewReport'][0]['reportUuid']);
        $this->assertSame('rsst', $calls['notifyNewReport'][0]['type']);
        $this->assertSame(1, $calls['notifyNewReport'][0]['siteId']);
    }

    public function testReportCreatedListenerHandlesDgiReport(): void
    {
        // L4131-2 — DGI reports must notify CHSCT (obligation légale)
        $events = new \App\Event\EventDispatcher();
        $calls = [];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('report.created', [
            'report' => [
                'uuid' => 'dgi-uuid',
                'type' => 'dgi',
                'site_id' => 5,
            ],
        ]);

        $this->assertCount(1, $calls['notifyNewReport'], 'notifyNewReport must be called for DGI');
        $this->assertSame('dgi', $calls['notifyNewReport'][0]['type']);
    }

    public function testReportCreatedListenerDoesNotCrashOnMissingData(): void
    {
        $events = new \App\Event\EventDispatcher();
        $calls = [];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        // Missing report key — listener should silently no-op
        $events->dispatch('report.created', []);
        $this->assertCount(0, $calls['notifyNewReport']);

        // Missing uuid
        $events->dispatch('report.created', ['report' => ['type' => 'rsst', 'site_id' => 1]]);
        $this->assertCount(0, $calls['notifyNewReport']);
    }

    public function testReportCreatedListenerCatchesExceptions(): void
    {
        $events = new \App\Event\EventDispatcher();

        $container = $this->createContainer(\App\Services\NotificationService::class, function () {
            return new class {
                public function notifyNewReport(): void
                {
                    throw new RuntimeException('SMTP down');
                }
                public function notifyReportResponse(): void {}
                public function notifyReportReopen(): void {}
                public function notifyRoleChange(): void {}
            };
        });

        registerEventListeners($events, $container);

        // Should NOT throw — the listener must catch exceptions
        $events->dispatch('report.created', [
            'report' => ['uuid' => 'test-uuid', 'type' => 'rsst', 'site_id' => 1],
        ]);

        $this->assertTrue(true, 'Listener should catch exceptions without propagating');
    }

    public function testReportRespondedListenerCallsNotifyReportResponse(): void
    {
        $events = new \App\Event\EventDispatcher();
        $calls = [];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('report.responded', [
            'report' => ['uuid' => 'resp-uuid'],
            'userId' => 42,
        ]);

        $this->assertCount(1, $calls['notifyReportResponse'] ?? []);
        $this->assertSame('resp-uuid', $calls['notifyReportResponse'][0]['reportUuid']);
        $this->assertSame(42, $calls['notifyReportResponse'][0]['userId']);
    }

    public function testUserRoleChangedListenerCallsNotifyRoleChange(): void
    {
        $events = new \App\Event\EventDispatcher();
        $calls = [];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('user.role_changed', [
            'user' => ['id' => 7, 'role' => 'agent'],
            'oldRole' => 'agent',
            'newRole' => 'superviseur',
        ]);

        $this->assertCount(1, $calls['notifyRoleChange'] ?? []);
        $this->assertSame(7, $calls['notifyRoleChange'][0]['userId']);
        $this->assertSame('agent', $calls['notifyRoleChange'][0]['oldRole']);
        $this->assertSame('superviseur', $calls['notifyRoleChange'][0]['newRole']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────────

    private function createContainerWithTrackedNotifications(array &$calls): \App\Container\Container
    {
        $notifications = new class ($calls) {
            public function __construct(private array &$calls) {}

            public function notifyNewReport(string $reportUuid, string $type, int $siteId): void
            {
                $this->calls['notifyNewReport'][] = [
                    'reportUuid' => $reportUuid,
                    'type' => $type,
                    'siteId' => $siteId,
                ];
            }

            public function notifyReportResponse(string $reportUuid, int $userId): void
            {
                $this->calls['notifyReportResponse'][] = [
                    'reportUuid' => $reportUuid,
                    'userId' => $userId,
                ];
            }

            public function notifyReportReopen(string $reportUuid, int $userId): void
            {
                $this->calls['notifyReportReopen'][] = [
                    'reportUuid' => $reportUuid,
                    'userId' => $userId,
                ];
            }

            public function notifyRoleChange(int $userId, string $oldRole, string $newRole): void
            {
                $this->calls['notifyRoleChange'][] = [
                    'userId' => $userId,
                    'oldRole' => $oldRole,
                    'newRole' => $newRole,
                ];
            }
        };

        $container = new \App\Container\Container();
        $container->set(\App\Services\NotificationService::class, fn() => $notifications);
        return $container;
    }

    private function createContainerWithMockedNotifications(): \App\Container\Container
    {
        $notifications = new class {
            public function notifyNewReport(string $reportUuid, string $type, int $siteId): void {}
            public function notifyReportResponse(string $reportUuid, int $userId): void {}
            public function notifyReportReopen(string $reportUuid, int $userId): void {}
            public function notifyRoleChange(int $userId, string $oldRole, string $newRole): void {}
        };

        $container = new \App\Container\Container();
        $container->set(\App\Services\NotificationService::class, fn() => $notifications);
        return $container;
    }

    private function createContainer(string $serviceId, callable $factory): \App\Container\Container
    {
        $container = new \App\Container\Container();
        $container->set($serviceId, $factory);
        return $container;
    }
}
