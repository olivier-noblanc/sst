<?php

/** CreateRegistryFieldCommand — DTO pour la création d'un champ custom de registre. */

namespace App\DTO;

final readonly class CreateRegistryFieldCommand
{
    public function __construct(
        public readonly string $fieldCode,
        public readonly string $label,
        public readonly string $fieldType = 'text',
        public readonly ?string $options = null,
        public readonly int $isRequired = 0,
        public readonly int $sortOrder = 0,
    ) {}
}
