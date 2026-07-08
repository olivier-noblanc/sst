<?php
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    public function testDispatchCallsListener(): void
    {
        $dispatcher = new EventDispatcher();
        $called = false;
        $dispatcher->addListener('test.event', function($data) use (&$called) {
            $called = true;
            $this->assertEquals('hello', $data['message']);
        });
        $dispatcher->dispatch('test.event', ['message' => 'hello']);
        $this->assertTrue($called);
    }

    public function testDispatchCallsMultipleListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $count = 0;
        $dispatcher->addListener('test.event', function() use (&$count) { $count++; });
        $dispatcher->addListener('test.event', function() use (&$count) { $count++; });
        $dispatcher->dispatch('test.event', []);
        $this->assertEquals(2, $count);
    }

    public function testDispatchUnknownEventDoesNothing(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->dispatch('nonexistent', []);
        $this->assertTrue(true);
    }

    public function testListenersAreCalledInOrder(): void
    {
        $dispatcher = new EventDispatcher();
        $order = [];
        $dispatcher->addListener('test.event', function() use (&$order) { $order[] = 'first'; });
        $dispatcher->addListener('test.event', function() use (&$order) { $order[] = 'second'; });
        $dispatcher->dispatch('test.event', []);
        $this->assertEquals(['first', 'second'], $order);
    }
}