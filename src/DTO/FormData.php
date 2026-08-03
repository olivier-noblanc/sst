<?php

namespace App\DTO;

use Error;
use ArrayAccess;

/**
 * FormData — immutable DTO representing the form data persisted across a
 * redirect-after-validation-error flow.
 *
 * Replaces the untyped array<string, mixed> previously stored in
 * $_SESSION['form_data']. The DTO kills Infection mutants on:
 *  - (string) / (int) casts on $_POST values
 *  - null coalescing chains ($formData['field'] ?? '')
 *  - isset()/empty() branching on untyped array access
 *
 * Implements ArrayAccess for backward compatibility with templates that
 * still access form data as $formData['nom'] instead of $formData->getString('nom').
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class FormData implements ArrayAccess
{
    /**
     * @param array<string, string|int|float|bool|null> $fields
     */
    public function __construct(
        public readonly array $fields = [],
    ) {}

    /**
     * Hydrate from $_POST (or any raw superglobal-style array).
     *
     * Nested arrays are preserved as-is (e.g. `etats[]` checkboxes produce
     * `array<int, string>` — templates do `in_array($key, $formData['etats'])`
     * which only works if the nested structure is preserved).
     *
     * @param array<string, mixed> $post
     */
    public static function fromPost(array $post): self
    {
        $fields = [];
        foreach ($post as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $fields[$key] = $value;
        }
        return new self($fields);
    }

    /**
     * Restore from $_SESSION['form_data'] raw storage.
     *
     * @param array<string, mixed> $data
     */
    public static function fromSession(array $data): self
    {
        return self::fromPost($data);
    }

    /** @return array<string, string|int|float|bool|null> */
    public function toArray(): array
    {
        return $this->fields;
    }

    /**
     * Get a string field (with default fallback).
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->fields[$key] ?? null;
        return is_string($value) ? $value : (string) ($value ?? $default);
    }

    /**
     * Get an integer field (with default fallback).
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->fields[$key] ?? null;
        return is_int($value) ? $value : (int) ($value ?? $default);
    }

    /**
     * Get a boolean field (with default fallback).
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->fields[$key] ?? null;
        return is_bool($value) ? $value : (bool) ($value ?? $default);
    }

    /**
     * Check if a field exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->fields);
    }

    /**
     * Check if a field is "empty" (PHP empty() semantics — covers '', 0, '0', null, false, []).
     */
    public function isEmpty(string $key): bool
    {
        $value = $this->fields[$key] ?? null;
        return empty($value);
    }

    /** @param string $offset */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    /** @param string $offset */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->fields[(string) $offset] ?? null;
    }

    /** @param string $offset */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new Error('Cannot modify readonly FormData — use FormData::fromPost() to create a new instance.');
    }

    /** @param string $offset */
    public function offsetUnset(mixed $offset): never
    {
        throw new Error('Cannot unset readonly FormData — use FormData::fromPost() to create a new instance.');
    }
}
