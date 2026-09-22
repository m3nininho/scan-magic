<?php

use App\Enums\CardScanStatus;
use App\Models\CardScan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('pending scans only include scans owned by the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownedScan = CardScan::create(['user_id' => $user->id, 'detected_name' => 'Owned', 'status' => CardScanStatus::NeedsConfirmation]);
    CardScan::create(['user_id' => $user->id, 'detected_name' => 'Saved', 'status' => CardScanStatus::Confirmed]);
    CardScan::create(['user_id' => $otherUser->id, 'detected_name' => 'Other', 'status' => CardScanStatus::Failed]);

    $response = $this->actingAs($user)->get('/pending-scans');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('pending-scans')
        ->has('scans.data', 1)
        ->where('scans.data.0.id', $ownedScan->id));
});

test('user can view only their own pending scan image', function () {
    Storage::fake('local');
    Storage::disk('local')->put('scans/card.jpg', 'image-content');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $scan = CardScan::create(['user_id' => $owner->id, 'original_image_path' => 'scans/card.jpg', 'status' => CardScanStatus::Failed]);

    $this->actingAs($owner)->get("/pending-scans/{$scan->id}/image")->assertOk();
    $this->actingAs($otherUser)->get("/pending-scans/{$scan->id}/image")->assertNotFound();
});

test('user can search manually for an unidentified scan', function () {
    Http::fake(['*' => Http::response([
        'id' => 'found-card', 'name' => 'Found Card', 'type_line' => 'Creature — Wizard', 'set_name' => 'Test Set',
    ], 200)]);
    $user = User::factory()->create();
    $scan = CardScan::create(['user_id' => $user->id, 'status' => CardScanStatus::Failed]);

    $response = $this->actingAs($user)->post("/pending-scans/{$scan->id}/search", ['name' => 'Found Card']);

    $response->assertRedirect();
    expect($scan->fresh()->detected_name)->toBe('Found Card')
        ->and($scan->fresh()->status)->toBe(CardScanStatus::NeedsConfirmation)
        ->and($scan->fresh()->candidates[0]['providerId'])->toBe('found-card');
});

test('owner can cancel a pending scan and its stored image', function () {
    Storage::fake('local');
    Storage::disk('local')->put('scans/cancel.jpg', 'image-content');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $scan = CardScan::create([
        'user_id' => $owner->id,
        'original_image_path' => 'scans/cancel.jpg',
        'status' => CardScanStatus::NeedsConfirmation,
    ]);

    $this->actingAs($otherUser)->delete("/pending-scans/{$scan->id}")->assertNotFound();
    $this->assertDatabaseHas('card_scans', ['id' => $scan->id]);

    $this->actingAs($owner)->delete("/pending-scans/{$scan->id}")->assertRedirect();

    $this->assertDatabaseMissing('card_scans', ['id' => $scan->id]);
    Storage::disk('local')->assertMissing('scans/cancel.jpg');
});
