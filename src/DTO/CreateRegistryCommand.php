<?php

/** CreateRegistryCommand — DTO pour la création d'un registre. */

namespace App\DTO;

use App\Enum\ReportType;
use App\Enum\VisibilityMode;

final readonly class CreateRegistryCommand
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $shortLabel,
        public readonly ?string $description = null,
        public readonly string $icon = '📋',
        public readonly string $colorTheme = ReportType::Rsst->value,
        public readonly int $isEnabled = 1,
        public readonly int $isSystem = 0,
        public readonly int $sortOrder = 0,
        public readonly string $defaultVisibility = VisibilityMode::AgentChoice->value,
        public readonly int $notifyChsct = 0,
        public readonly ?string $legalNote = null,
    ) {}
}
