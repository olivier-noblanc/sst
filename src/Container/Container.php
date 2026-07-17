<?php

/** Container — Simple DI Container. */

namespace App\Container;

use RuntimeException;

class Container
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): mixed
    {
        if (!isset($this->instances[$id])) {
            if (!isset($this->factories[$id])) {
                throw new RuntimeException("Service '$id' not registered.");
            }
            $this->instances[$id] = ($this->factories[$id])($this);
        }
        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
