<?php

namespace App\Http\Controllers;

use App\Enums\CardScanStatus;
use App\Models\Collection;
use App\Services\CollectionSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CollectionSummaryService $summary): Response
    {
        $collection = Collection::query()
            ->whereBelongsTo($request->user())
            ->where('name', 'My Collection')
            ->first();
        $pendingScans = $request->user()->cardScans()
            ->where('status', '!=', CardScanStatus::Confirmed)
            ->count();

        return Inertia::render('dashboard', [
            'summary' => $summary->summarize($collection),
            'pendingScans' => $pendingScans,
            'recentCards' => $collection
                ? $collection->cards()
                    ->select(['cards.id', 'cards.name', 'cards.image_url'])
                    ->orderByPivotDesc('updated_at')
                    ->limit(6)
                    ->get()
                : [],
        ]);
    }
}
