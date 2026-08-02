<?php

namespace App\DTO;

final readonly class AttachmentData
{
    public function __construct(
        public readonly ?string $blob = null,
        public readonly ?string $name = null,
        public readonly ?string $mime = null,
    ) {}

    /** @phpstan-ignore shipmonk.deadMethod */
    public function isEmpty(): bool
    {
        return $this->blob === null && $this->name === null && $this->mime === null;
    }
}
