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
     * The old array<string, mixed> signature is kept for backward compat
     * with any callers not yet migrated, but new code should use DTOs.
     *
     * @param ReportEventData|UserEventData|array<string, mixed> $data
     */
    public function dispatch(string $event, ReportEventData|UserEventData|array $data): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($data);
        }
    }
}
