<?php

namespace App\Http\Controllers;

use App\Contracts\CardProviderInterface;
use App\Contracts\CardRecognitionService;
use App\Data\CardMatchResult;
use App\Data\ProviderCard;
use App\Enums\CardScanStatus;
use App\Models\Card;
use App\Models\CardScan;
use App\Models\Collection;
use App\Services\CardImportService;
use App\Services\CardNameMatcher;
use App\Services\CollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ScannerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('scanner');
    }

    public function scan(Request $request, CardRecognitionService $recognition, CardNameMatcher $matcher): Response|JsonResponse
    {
        $path = $request->has('image_data')
            ? $this->storeEncodedImage($request)
            : $this->storeUploadedImage($request);
        $scan = CardScan::create(['user_id' => $request->user()->id, 'original_image_path' => $path, 'status' => CardScanStatus::Processing]);

        try {
            $rawText = $recognition->recognize(Storage::disk('local')->path($path));
            $result = $matcher->match($rawText);
            $payload = $this->result($result, $request->user()->id);
            $scan->update([
                'ocr_raw_text' => $result->rawText,
                'detected_name' => $result->detectedName,
                'confidence' => $result->confidence,
                'candidates' => $payload['candidates'],
                'status' => $result->detectedName ? CardScanStatus::NeedsConfirmation : CardScanStatus::Failed,
            ]);
        } catch (Throwable $exception) {
            $scan->update(['status' => CardScanStatus::Failed]);
            report($exception);

            return response()->json([
                'message' => 'Não foi possível ler a carta agora.',
                'errors' => ['image' => ['Não foi possível ler a carta agora. Tente novamente.']],
            ], 503);
        }

        $payload = ['scan' => $scan->fresh(), 'result' => $payload];

        return $request->expectsJson()
            ? response()->json($payload)
            : Inertia::render('scanner', $payload);
    }

    private function storeUploadedImage(Request $request): string
    {
        $validated = $request->validate(['image' => ['required', 'image', 'max:10240']]);
        $path = Storage::disk('local')->putFile('scans', $validated['image']);
        abort_unless($path !== false, 500, 'Could not store scan.');

        return $path;
    }

    private function storeEncodedImage(Request $request): string
    {
        $validated = $request->validate([
            'image_data' => ['required', 'string', 'max:15000000'],
            'image_name' => ['nullable', 'string', 'max:255'],
        ]);
        $contents = base64_decode($validated['image_data'], true);

        if ($contents === false || strlen($contents) > 10 * 1024 * 1024 || @getimagesizefromstring($contents) === false) {
            throw ValidationException::withMessages(['image' => 'The image is invalid or too large.']);
        }

        $extension = pathinfo($validated['image_name'] ?? 'card.jpg', PATHINFO_EXTENSION) ?: 'jpg';
        $path = 'scans/'.str()->uuid().'.'.strtolower($extension);
        abort_unless(Storage::disk('local')->put($path, $contents) === true, 500, 'Could not store scan.');

        return $path;
    }

    public function search(Request $request, CardProviderInterface $provider): Response|JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $scan = CardScan::create([
            'user_id' => $request->user()->id,
            'detected_name' => $validated['name'],
            'status' => CardScanStatus::Processing,
        ]);

        try {
            $candidates = $this->cards($provider->searchByName($validated['name']), $request->user()->id);
        } catch (Throwable $exception) {
            $scan->update(['status' => CardScanStatus::Failed]);
            report($exception);

            $payload = [
                'manualQuery' => $validated['name'],
                'searchError' => 'A busca está indisponível no momento. Tente novamente.',
            ];

            return $request->expectsJson()
                ? response()->json(['message' => $payload['searchError']], 503)
                : Inertia::render('scanner', $payload);
        }

        $firstCandidate = $candidates[0] ?? null;
        $scan->update([
            'detected_name' => $firstCandidate['name'] ?? $validated['name'],
            'candidates' => $candidates,
            'status' => $candidates === [] ? CardScanStatus::Failed : CardScanStatus::NeedsConfirmation,
        ]);

        $payload = [
            'scan' => $scan->fresh(),
            'manualQuery' => $validated['name'],
            'candidates' => $candidates,
        ];

        return $request->expectsJson()
            ? response()->json($payload)
            : Inertia::render('scanner', $payload);
    }

    public function confirm(Request $request, CardScan $scan, CardProviderInterface $provider, CardImportService $importer, CollectionService $collections): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $scan);

        if ($scan->status === CardScanStatus::Confirmed) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Esta carta já foi salva.', 'alreadySaved' => true]);
            }

            return $this->confirmationRedirect($request, 'Esta carta já foi salva.');
        }

        $validated = $request->validate([
            'provider_id' => ['required', 'string'],
            'return_to' => ['nullable', 'in:pending-scans'],
        ]);

        try {
            $providerCard = $provider->find($validated['provider_id']);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'provider_id' => 'A consulta da carta está indisponível. Tente novamente.',
            ]);
        }

        if ($providerCard === null) {
            throw ValidationException::withMessages(['provider_id' => 'A carta selecionada não está mais disponível.']);
        }

        $card = $importer->import($providerCard);

        // The catalog import commits independently; collection and scan must change together.
        $confirmation = DB::transaction(function () use ($request, $scan, $card, $collections): array {
            $lockedScan = CardScan::query()->lockForUpdate()->findOrFail($scan->id);

            if ($lockedScan->status === CardScanStatus::Confirmed) {
                return ['added' => false, 'totalQuantity' => $this->ownedQuantity($request->user()->id, $lockedScan->card_id)];
            }

            $collection = $collections->defaultForUser($request->user()->id);
            $totalQuantity = $collections->addCard($collection, $card);
            $lockedScan->update(['card_id' => $card->id, 'status' => CardScanStatus::Confirmed]);

            return ['added' => true, 'totalQuantity' => $totalQuantity];
        });

        if ($scan->original_image_path !== null && Storage::disk('local')->delete($scan->original_image_path)) {
            $scan->update(['original_image_path' => null]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $confirmation['added'] ? 'Carta adicionada à sua coleção!' : 'Esta carta já foi salva.',
                'alreadySaved' => ! $confirmation['added'],
                'quantityAdded' => $confirmation['added'] ? 1 : 0,
                'totalQuantity' => $confirmation['totalQuantity'],
                'card' => [
                    'id' => $card->id,
                    'name' => $card->name,
                    'imageUrl' => $card->image_url,
                    'type' => $card->type,
                    'setName' => $card->set_name,
                ],
            ]);
        }

        return $this->confirmationRedirect(
            $request,
            $confirmation['added'] ? 'Carta adicionada à sua coleção!' : 'Esta carta já foi salva.',
        );
    }

    /** @return array<string, mixed> */
    private function result(CardMatchResult $result, int $userId): array
    {
        return [
            'rawText' => $result->rawText,
            'detectedName' => $result->detectedName,
            'confidence' => $result->confidence,
            'candidates' => $this->cards($result->candidates, $userId),
        ];
    }

    /**
     * @param  array<int, ProviderCard>  $providerCards
     * @return array<int, array<string, mixed>>
     */
    private function cards(array $providerCards, int $userId): array
    {
        $collection = Collection::query()->where('user_id', $userId)->where('name', 'My Collection')->first();
        $quantities = $collection
            ? Card::query()
                ->join('collection_cards', 'collection_cards.card_id', '=', 'cards.id')
                ->where('collection_cards.collection_id', $collection->id)
                ->whereIn('cards.provider_id', collect($providerCards)->pluck('providerId'))
                ->pluck('collection_cards.quantity', 'cards.provider_id')
            : collect();

        return collect($providerCards)->map(fn (ProviderCard $card): array => [
            'providerId' => $card->providerId,
            'name' => $card->name,
            'type' => $card->type,
            'manaCost' => $card->manaCost,
            'text' => $card->text,
            'imageUrl' => $card->imageUrl,
            'setName' => $card->setName,
            'ownedQuantity' => (int) ($quantities[$card->providerId] ?? 0),
        ])->values()->all();
    }

    private function ownedQuantity(int $userId, ?int $cardId): int
    {
        if ($cardId === null) {
            return 0;
        }

        $collection = Collection::query()->where('user_id', $userId)->where('name', 'My Collection')->first();

        return (int) ($collection?->cards()->whereKey($cardId)->value('collection_cards.quantity') ?? 0);
    }

    private function confirmationRedirect(Request $request, string $message): RedirectResponse
    {
        return ($request->string('return_to')->toString() === 'pending-scans'
            ? to_route('pending-scans.index')
            : to_route('scanner'))
            ->with('success', $message);
    }
}
