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
 * 4. Le listener 'report.reopened' appelle notifyReportReopen (avec le motif)
 * 5. 'user.role_changed' n'a PAS de listener de notification — le changement
 *    de rôle est notifié par user_edit_handler.php seul (checkbox respectée)
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
        // ReflectionProperty::setAccessible() is a no-op since PHP 8.1 and deprecated in 8.5;
        // ReflectionClass::getProperty() already yields an accessible ReflectionProperty here.
        $listeners = $listenersProp->getValue($events);

        $this->assertArrayHasKey('report.created', $listeners, 'report.created listener must be registered');
        $this->assertArrayHasKey('report.responded', $listeners, 'report.responded listener must be registered');
        $this->assertArrayHasKey('report.reopened', $listeners, 'report.reopened listener must be registered');
        $this->assertArrayHasKey('report.abandoned', $listeners, 'report.abandoned listener must be registered');
        $this->assertArrayNotHasKey(
            'user.role_changed',
            $listeners,
            'user.role_changed ne doit PLUS avoir de listener de notification : le changement de rôle '
            . 'est notifié par user_edit_handler.php seul (checkbox notify_role_change respectée, '
            . 'audit email_sent/email_error fidèle — Bug #30).'
        );
    }

    public function testReportCreatedListenerCallsNotifyNewReport(): void
    {
        $events = new \App\Event\EventDispatcher();

        $calls = ['notifyNewReport' => [], 'notifyReportResponse' => [], 'notifyReportReopen' => [], 'notifyRoleChange' => []];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('report.created', new \App\DTO\ReportEventData(
            reportUuid: 'test-uuid-1',
            type: 'rsst',
            siteId: 1,
        ));

        $this->assertCount(1, $calls['notifyNewReport'] ?? [], 'notifyNewReport should be called once');
        $this->assertSame('test-uuid-1', $calls['notifyNewReport'][0]['reportUuid']);
        $this->assertSame('rsst', $calls['notifyNewReport'][0]['type']);
        $this->assertSame(1, $calls['notifyNewReport'][0]['siteId']);
    }

    public function testReportCreatedListenerHandlesDgiReport(): void
    {
        // L4131-2 — DGI reports must notify CHSCT (obligation légale)
        $events = new \App\Event\EventDispatcher();
        $calls = ['notifyNewReport' => [], 'notifyReportResponse' => [], 'notifyReportReopen' => [], 'notifyRoleChange' => []];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('report.created', new \App\DTO\ReportEventData(
            reportUuid: 'dgi-uuid',
            type: 'dgi',
            siteId: 5,
        ));

        $this->assertCount(1, $calls['notifyNewReport'], 'notifyNewReport must be called for DGI');
        $this->assertSame('dgi', $calls['notifyNewReport'][0]['type']);
    }

    public function testReportCreatedListenerDoesNotCrashOnMissingData(): void
    {
        $events = new \App\Event\EventDispatcher();
        $calls = ['notifyNewReport' => [], 'notifyReportResponse' => [], 'notifyReportReopen' => [], 'notifyRoleChange' => []];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        // Empty DTO — listener should silently no-op
        $events->dispatch('report.created', new \App\DTO\ReportEventData());
        $this->assertCount(0, $calls['notifyNewReport']);

        // Missing uuid — listener should silently no-op
        $events->dispatch('report.created', new \App\DTO\ReportEventData(
            type: 'rsst',
            siteId: 1,
        ));
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
        $events->dispatch('report.created', new \App\DTO\ReportEventData(
            reportUuid: 'test-uuid',
            type: 'rsst',
            siteId: 1,
        ));

        $this->assertTrue(true, 'Listener should catch exceptions without propagating');
    }

    public function testReportRespondedListenerCallsNotifyReportResponse(): void
    {
        $events = new \App\Event\EventDispatcher();
        $calls = ['notifyNewReport' => [], 'notifyReportResponse' => [], 'notifyReportReopen' => [], 'notifyRoleChange' => []];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('report.responded', new \App\DTO\ReportEventData(
            reportUuid: 'resp-uuid',
            userId: 42,
        ));

        $this->assertCount(1, $calls['notifyReportResponse'] ?? []);
        $this->assertSame('resp-uuid', $calls['notifyReportResponse'][0]['reportUuid']);
        $this->assertSame(42, $calls['notifyReportResponse'][0]['userId']);
    }

    public function testReportReopenedListenerPassesMotif(): void
    {
        // Fiabilisation — l'ancien envoi direct du handler report_reopen
        // incluait le motif de réouverture dans l'e-mail. Le chemin event
        // unique doit préserver ce contenu via ReportEventData::motif.
        $events = new \App\Event\EventDispatcher();
        $calls = ['notifyNewReport' => [], 'notifyReportResponse' => [], 'notifyReportReopen' => [], 'notifyRoleChange' => []];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('report.reopened', new \App\DTO\ReportEventData(
            reportUuid: 'reopen-uuid',
            userId: 9,
            motif: 'Elements nouveaux apres expertise',
        ));

        $this->assertCount(1, $calls['notifyReportReopen'] ?? []);
        $this->assertSame(
            'Elements nouveaux apres expertise',
            $calls['notifyReportReopen'][0]['motif'] ?? null,
            'Le motif de réouverture doit transiter jusqu\'à la notification'
        );
    }

    public function testUserRoleChangedEventHasNoNotificationListener(): void
    {
        // Contrat (council) — UNE SEULE notification par événement. Le
        // changement de rôle est notifié par le handler seul (checkbox
        // respectée) ; l'event ne déclenche plus d'e-mail.
        $events = new \App\Event\EventDispatcher();
        $calls = ['notifyNewReport' => [], 'notifyReportResponse' => [], 'notifyReportReopen' => [], 'notifyRoleChange' => []];
        $container = $this->createContainerWithTrackedNotifications($calls);

        registerEventListeners($events, $container);

        $events->dispatch('user.role_changed', new \App\DTO\UserEventData(
            userId: 7,
            oldRole: 'agent',
            newRole: 'superviseur',
        ));

        $this->assertCount(0, $calls['notifyRoleChange'] ?? [], 'Aucun e-mail de rôle via l\'event : chemin unique = handler');
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

            public function notifyReportReopen(string $reportUuid, int $userId, ?string $motif = null): void
            {
                $this->calls['notifyReportReopen'][] = [
                    'reportUuid' => $reportUuid,
                    'userId' => $userId,
                    'motif' => $motif,
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
            public function notifyReportReopen(string $reportUuid, int $userId, ?string $motif = null): void {}
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
