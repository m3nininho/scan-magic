<?php

namespace App\Contracts;

use App\Data\ProviderCard;

interface CardProviderInterface
{
    /** @return array<int, ProviderCard> */
    public function searchByName(string $name): array;

    public function find(string $id): ?ProviderCard;
}
