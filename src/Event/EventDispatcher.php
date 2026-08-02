<?php

/** EventDispatcher — Découple action métier / notifications. */

namespace App\Event;

use App\DTO\ReportEventData;
use App\DTO\UserEventData;

class EventDispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function addListener(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Dispatch an event with typed DTO data.
     *
     * Accepts either a ReportEventData or UserEventData DTO.
     */
    public function dispatch(string $event, ReportEventData|UserEventData $data): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($data);
        }
    }
}
