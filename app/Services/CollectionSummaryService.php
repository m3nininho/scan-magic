<?php

namespace App\Services;

use App\Models\Collection;

class CollectionSummaryService
{
    /** @return array<string, int> */
    public function summarize(?Collection $collection): array
    {
        $summary = [
            'totalCopies' => 0,
            'differentCards' => 0,
            'lands' => 0,
            'creatures' => 0,
            'artifacts' => 0,
            'enchantments' => 0,
            'instants' => 0,
            'sorceries' => 0,
            'planeswalkers' => 0,
        ];

        if ($collection === null) {
            return $summary;
        }

        $summary['totalCopies'] = (int) $collection->cards()->sum('collection_cards.quantity');
        $summary['differentCards'] = $collection->cards()->count();

        foreach ([
            'lands' => 'Land',
            'creatures' => 'Creature',
            'artifacts' => 'Artifact',
            'enchantments' => 'Enchantment',
            'instants' => 'Instant',
            'sorceries' => 'Sorcery',
            'planeswalkers' => 'Planeswalker',
        ] as $key => $type) {
            $summary[$key] = $collection->cards()
                ->whereHas('cardTypes', fn ($query) => $query
                    ->where('category', 'type')
                    ->where('name', $type))
                ->count();
        }

        return $summary;
    }
}
