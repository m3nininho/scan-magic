<?php

namespace App\Data;

final readonly class CardMatchResult
{
    /** @param array<int, ProviderCard> $candidates */
    public function __construct(
        public string $rawText,
        public ?string $detectedName,
        public float $confidence,
        /** @var array<int, ProviderCard> */
        public array $candidates,
    ) {}
}
