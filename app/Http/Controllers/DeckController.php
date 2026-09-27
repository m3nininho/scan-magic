<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Services\DeckValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeckController extends Controller
{
    public function index(Request $request, DeckValidationService $validator): Response
    {
        $decks = $request->user()->decks()->with(['commander:id,name', 'cards:id,name'])->latest('id')->paginate(12)
            ->through(fn (Deck $deck): array => Arr::except($validator->present($deck), ['cards', 'warnings']));

        return Inertia::render('decks/index', ['decks' => $decks]);
    }

    public function show(Request $request, int $deck, DeckValidationService $validator): Response
    {
        $saved = $request->user()->decks()->with(['commander:id,name', 'cards:id,name'])->findOrFail($deck);

        return Inertia::render('decks/show', ['deck' => $validator->present($saved)]);
    }

    public function store(Request $request, DeckValidationService $validator): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $validator): array {
            $validated = $validator->validate($request->user(), $request->only(['name', 'format', 'strategy', 'commander_card_id', 'cards']));
            if ($validated['total'] === 0) {
                throw ValidationException::withMessages(['cards' => 'Nenhuma carta válida disponível para salvar.']);
            }
            $deck = $request->user()->decks()->create(Arr::only($validated, ['name', 'format', 'strategy', 'commander_card_id']));
            $cards = [];
            foreach ($validated['cards'] as $row) {
                $cards[$row['card_id']] = Arr::only($row, ['quantity', 'role']);
            }
            $deck->cards()->sync($cards);

            return ['deck' => ['id' => $deck->id] + $validated];
        });

        return response()->json($result, 201);
    }
}
