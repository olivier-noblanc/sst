<?php

/** UpdateRegistryCommand — DTO pour la mise à jour d'un registre. */

namespace App\DTO;

final readonly class UpdateRegistryCommand
{
    public function __construct(
        public readonly ?string $label = null,
        public readonly ?string $shortLabel = null,
        public readonly ?string $description = null,
        public readonly ?string $icon = null,
        public readonly ?string $colorTheme = null,
        public readonly ?int $isEnabled = null,
        public readonly ?int $sortOrder = null,
        public readonly ?string $defaultVisibility = null,
        public readonly ?int $notifyChsct = null,
        public readonly ?string $legalNote = null,
    ) {}
}
