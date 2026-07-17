<?php

/** EventDispatcher — Découple action métier / notifications. */

namespace App\Event;

class EventDispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function addListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /** @param array<string, mixed> $data */
    public function dispatch(string $event, array $data): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($data);
        }
    }
}
