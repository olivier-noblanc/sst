<?php

namespace App\DTO;

/** RegistryCardData — Data for a single registry card display. */
final readonly class RegistryCardData
{
    private function __construct(
        public string $type,
        public string $cardClass,
        public string $title,
        public string $subtitle,
        public string $desc,
        public int $count,
        public string $btnLabel,
        public string $btnUrl,
        public string $listUrl,
        public string $listLabel,
    ) {}

    public static function create(
        string $type,
        string $cardClass = '',
        string $title = '',
        string $subtitle = '',
        string $desc = '',
        int $count = 0,
        string $btnLabel = '',
        string $btnUrl = '',
        string $listUrl = '',
        string $listLabel = '',
    ): self {
        if ($cardClass === '') {
            $cardClass = 'registry-card--' . $type;
        }
        return new self(
            type: $type,
            cardClass: $cardClass,
            title: $title,
            subtitle: $subtitle,
            desc: $desc,
            count: $count,
            btnLabel: $btnLabel,
            btnUrl: $btnUrl,
            listUrl: $listUrl,
            listLabel: $listLabel,
        );
    }

}
