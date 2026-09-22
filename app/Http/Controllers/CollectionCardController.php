<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Collection;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionCardController extends Controller
{
    public function show(Request $request, Card $card): Response
    {
        $collection = $this->collection($request);
        $collectionCard = $collection->cards()
            ->whereKey($card->id)
            ->select([
                'cards.id', 'cards.name', 'cards.layout', 'cards.mana_cost', 'cards.cmc',
                'cards.type', 'cards.rarity', 'cards.set_code', 'cards.set_name', 'cards.text',
                'cards.flavor', 'cards.artist', 'cards.number', 'cards.power', 'cards.toughness',
                'cards.loyalty', 'cards.image_url', 'cards.image_uris', 'cards.card_faces',
            ])
            ->with([
                'colors:id,name,code',
                'colorIdentity:id,name,code',
                'cardTypes:id,name,category',
                'legalities:id,card_id,format_id,legality',
                'legalities.format:id,name',
            ])
            ->firstOrFail();

        return Inertia::render('collection/card', [
            'card' => $collectionCard,
            'quantity' => (int) $collectionCard->pivot->getAttribute('quantity'),
        ]);
    }

    public function update(Request $request, Card $card, CollectionService $collections): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $collection = $this->collection($request);
        abort_unless($collection->cards()->whereKey($card->id)->exists(), 404);

        $collections->setQuantity($collection, $card, $validated['quantity']);

        if ($validated['quantity'] === 0) {
            return to_route('collection')->with('success', 'Carta removida da sua coleção.');
        }

        return back()->with('success', 'Quantidade atualizada.');
    }

    private function collection(Request $request): Collection
    {
        return Collection::query()
            ->whereBelongsTo($request->user())
            ->where('name', 'My Collection')
            ->firstOrFail();
    }
}
