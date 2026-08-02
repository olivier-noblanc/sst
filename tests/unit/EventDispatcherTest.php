<?php
use PHPUnit\Framework\TestCase;
use App\Event\EventDispatcher;
use App\DTO\ReportEventData;
use App\DTO\ReportData;

class EventDispatcherTest extends TestCase
{
    public function testDispatchCallsListener(): void
    {
        $dispatcher = new EventDispatcher();
        $called = false;
        $dispatcher->addListener('test.event', function(ReportEventData $data) use (&$called) {
            $called = true;
            $this->assertSame('hello', $data->motif);
        });
        $dispatcher->dispatch('test.event', new ReportEventData(motif: 'hello'));
        $this->assertTrue($called);
    }

    public function testDispatchCallsMultipleListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $count = 0;
        $dispatcher->addListener('test.event', function(ReportEventData $data) use (&$count) { $count++; });
        $dispatcher->addListener('test.event', function(ReportEventData $data) use (&$count) { $count++; });
        $dispatcher->dispatch('test.event', new ReportEventData());
        $this->assertEquals(2, $count);
    }

    public function testDispatchUnknownEventDoesNothing(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->dispatch('nonexistent', new ReportEventData());
        $this->assertTrue(true);
    }

    public function testListenersAreCalledInOrder(): void
    {
        $dispatcher = new EventDispatcher();
        $order = [];
        $dispatcher->addListener('test.event', function(ReportEventData $data) use (&$order) { $order[] = 'first'; });
        $dispatcher->addListener('test.event', function(ReportEventData $data) use (&$order) { $order[] = 'second'; });
        $dispatcher->dispatch('test.event', new ReportEventData());
        $this->assertEquals(['first', 'second'], $order);
    }
}
