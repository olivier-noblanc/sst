<?php

namespace App\DTO;

use Error;
use ArrayAccess;

/**
 * FlashMessage — immutable DTO representing a one-shot flash message stored in session.
 *
 * Replaces the untyped array{type: string, message: string} previously stored
 * in $_SESSION['flash']. The DTO kills Infection mutants on:
 *  - $flash['type'] (string cast no longer needed)
 *  - $flash['message'] (string cast no longer needed)
 *  - null coalescing (?? '') on untyped array access
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class FlashMessage implements ArrayAccess
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
    ) {}

    /**
     * Restore from $_SESSION['flash'] raw storage.
     *
     * @param array{type?: mixed, message?: mixed} $data
     */
    public static function fromSession(array $data): self
    {
        return new self(
            type: $data['type'] ?? '',
            message: $data['message'] ?? '',
        );
    }

    /** @return array{type: string, message: string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'message' => $this->message];
    }

    /** @param string $offset */
    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['type', 'message'], true);
    }

    /** @param string $offset */
    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'type' => $this->type,
            'message' => $this->message,
            default => null,
        };
    }

    /** @param string $offset */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new Error('Cannot modify readonly property ' . $offset);
    }

    /** @param string $offset */
    public function offsetUnset(mixed $offset): never
    {
        throw new Error('Cannot unset readonly property ' . $offset);
    }
}
