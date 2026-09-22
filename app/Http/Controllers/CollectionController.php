<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardType;
use App\Models\Collection;
use App\Models\Color;
use App\Services\CollectionSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(Request $request, CollectionSummaryService $summary): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:1'],
            'rarity' => ['nullable', 'string', 'max:50'],
            'set' => ['nullable', 'string', 'max:20'],
        ]);
        $collection = Collection::query()
            ->where('user_id', $request->user()->id)
            ->where('name', 'My Collection')
            ->first();

        $cards = $collection
            ? $collection->cards()
                ->select(['cards.id', 'cards.name', 'cards.type', 'cards.set_code', 'cards.set_name', 'cards.rarity', 'cards.image_url'])
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('cards.name', 'like', "%{$search}%"))
                ->when($filters['type'] ?? null, fn ($query, $type) => $query->whereHas('cardTypes', fn ($types) => $types->where('category', 'type')->where('name', $type)))
                ->when($filters['color'] ?? null, fn ($query, $color) => $query->whereHas('colors', fn ($colors) => $colors->where('code', $color)))
                ->when($filters['rarity'] ?? null, fn ($query, $rarity) => $query->where('cards.rarity', $rarity))
                ->when($filters['set'] ?? null, fn ($query, $set) => $query->where('cards.set_code', $set))
                ->orderBy('cards.name')
                ->orderBy('cards.id')
                ->paginate(24)
                ->withQueryString()
            : Card::query()->whereRaw('1 = 0')->paginate(24);

        return Inertia::render('collection', [
            'collection' => $collection,
            'cards' => $cards,
            'summary' => $summary->summarize($collection),
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($collection),
        ]);
    }

    /** @return array<string, mixed> */
    private function filterOptions(?Collection $collection): array
    {
        if ($collection === null) {
            return ['types' => [], 'colors' => [], 'rarities' => [], 'sets' => []];
        }

        $collectionId = $collection->id;

        return [
            'types' => CardType::query()
                ->where('category', 'type')
                ->whereHas('cards.collections', fn ($query) => $query->where('collections.id', $collectionId))
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all(),
            'colors' => Color::query()
                ->whereHas('cards.collections', fn ($query) => $query->where('collections.id', $collectionId))
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn (Color $color): array => [
                    'value' => $color->code,
                    'label' => $color->name,
                ])
                ->values()
                ->all(),
            'rarities' => $collection->cards()
                ->whereNotNull('rarity')
                ->distinct()
                ->orderBy('rarity')
                ->pluck('rarity')
                ->values()
                ->all(),
            'sets' => $collection->cards()
                ->whereNotNull('set_code')
                ->select(['set_code', 'set_name'])
                ->distinct()
                ->orderBy('set_name')
                ->get()
                ->map(fn (Card $card): array => [
                    'value' => $card->set_code,
                    'label' => $card->set_name ?? $card->set_code,
                ])
                ->values()
                ->all(),
        ];
    }
}
