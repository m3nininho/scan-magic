<?php

use App\Enums\CardScanStatus;
use App\Models\Card;
use App\Models\CardScan;
use App\Models\Collection;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['name' => 'My Collection', 'user_id' => $user->id]);
    $card = Card::create(['provider' => 'scryfall', 'provider_id' => 'dashboard-card', 'name' => 'Dashboard Card', 'api_data' => []]);
    $collection->cards()->attach($card, ['quantity' => 3]);
    CardScan::create(['user_id' => $user->id, 'detected_name' => 'Dashboard Card', 'status' => CardScanStatus::Confirmed]);
    CardScan::create(['user_id' => $user->id, 'status' => CardScanStatus::Failed]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('summary.differentCards', 1)
        ->where('summary.totalCopies', 3)
        ->where('pendingScans', 1)
        ->has('recentCards', 1));
});
