<?php

namespace App\Http\Controllers;

use App\Contracts\CardProviderInterface;
use App\Data\ProviderCard;
use App\Enums\CardScanStatus;
use App\Models\CardScan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PendingScanController extends Controller
{
    public function index(Request $request): Response
    {
        $scans = $request->user()->cardScans()
            ->where('status', '!=', CardScanStatus::Confirmed)
            ->select(['id', 'original_image_path', 'detected_name', 'confidence', 'candidates', 'status', 'created_at'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('pending-scans', ['scans' => $scans]);
    }

    public function search(Request $request, CardScan $scan, CardProviderInterface $provider): RedirectResponse
    {
        Gate::authorize('update', $scan);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        try {
            $candidates = collect($provider->searchByName($validated['name']))
                ->map(fn (ProviderCard $card): array => $this->card($card))
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['name' => 'A busca está indisponível no momento. Tente novamente.']);
        }

        $scan->update([
            'detected_name' => $candidates[0]['name'] ?? $validated['name'],
            'candidates' => $candidates,
            'status' => $candidates === [] ? CardScanStatus::Failed : CardScanStatus::NeedsConfirmation,
        ]);

        return back();
    }

    public function image(Request $request, CardScan $scan): StreamedResponse
    {
        Gate::authorize('view', $scan);
        abort_if($scan->original_image_path === null, 404);
        abort_unless(Storage::disk('local')->exists($scan->original_image_path), 404);

        return Storage::disk('local')->response($scan->original_image_path, headers: [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(CardScan $scan): RedirectResponse
    {
        Gate::authorize('delete', $scan);
        abort_if($scan->status === CardScanStatus::Confirmed, 404);

        if ($scan->original_image_path !== null) {
            Storage::disk('local')->delete($scan->original_image_path);
        }

        $scan->delete();

        return back()->with('success', 'Scan cancelado.');
    }

    /** @return array<string, string|null> */
    private function card(ProviderCard $card): array
    {
        return [
            'providerId' => $card->providerId,
            'name' => $card->name,
            'type' => $card->type,
            'imageUrl' => $card->imageUrl,
            'setName' => $card->setName,
        ];
    }
}
