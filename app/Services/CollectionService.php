<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Collection;

class CollectionService
{
    public function addCard(Collection $collection, Card $card, int $quantity = 1): int
    {
        $currentQuantity = $collection->cards()->whereKey($card->id)->first()?->pivot->quantity ?? 0;
        $newQuantity = (int) $currentQuantity + $quantity;

        $collection->cards()->syncWithoutDetaching([
            $card->id => ['quantity' => $newQuantity],
        ]);

        return $newQuantity;
    }

    public function setQuantity(Collection $collection, Card $card, int $quantity): void
    {
        if ($quantity === 0) {
            $collection->cards()->detach($card->id);

            return;
        }

        $collection->cards()->updateExistingPivot($card->id, ['quantity' => $quantity]);
    }

    public function defaultForUser(?int $userId = null): Collection
    {
        return Collection::firstOrCreate(['user_id' => $userId, 'name' => 'My Collection']);
    }
}
