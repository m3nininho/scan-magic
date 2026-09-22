<?php

use App\Models\Card;
use App\Models\Collection;
use App\Models\User;

test('user can view details for a card in their collection', function () {
    $user = User::factory()->create();
    $card = Card::create([
        'provider' => 'scryfall', 'provider_id' => 'detail-card', 'name' => 'Detail Card',
        'mana_cost' => '{1}{U}', 'cmc' => 2, 'type' => 'Creature — Wizard', 'api_data' => [],
    ]);
    $collection = Collection::create(['name' => 'My Collection', 'user_id' => $user->id]);
    $collection->cards()->attach($card, ['quantity' => 2]);

    $response = $this->actingAs($user)->get("/collection/cards/{$card->id}");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('collection/card')
        ->where('card.id', $card->id)
        ->where('card.name', 'Detail Card')
        ->where('quantity', 2)
        ->missing('card.api_data'));
});

test('user cannot view a card outside their collection', function () {
    $user = User::factory()->create();
    $card = Card::create(['provider' => 'scryfall', 'provider_id' => 'other-card', 'name' => 'Other Card', 'api_data' => []]);

    $response = $this->actingAs($user)->get("/collection/cards/{$card->id}");

    $response->assertNotFound();
});

test('user can change a card quantity and remove it at zero', function () {
    $user = User::factory()->create();
    $card = Card::create(['provider' => 'scryfall', 'provider_id' => 'quantity-card', 'name' => 'Quantity Card', 'api_data' => []]);
    $collection = Collection::create(['name' => 'My Collection', 'user_id' => $user->id]);
    $collection->cards()->attach($card, ['quantity' => 2]);

    $this->actingAs($user)->patch("/collection/cards/{$card->id}", ['quantity' => 4])->assertRedirect();

    expect($collection->cards()->first()->pivot->quantity)->toBe(4);

    $this->patch("/collection/cards/{$card->id}", ['quantity' => 0])->assertRedirect('/collection');

    expect($collection->cards()->count())->toBe(0)
        ->and(Card::whereKey($card->id)->exists())->toBeTrue();
});

test('quantity cannot be negative', function () {
    $user = User::factory()->create();
    $card = Card::create(['provider' => 'scryfall', 'provider_id' => 'validated-card', 'name' => 'Validated Card', 'api_data' => []]);
    $collection = Collection::create(['name' => 'My Collection', 'user_id' => $user->id]);
    $collection->cards()->attach($card, ['quantity' => 1]);

    $response = $this->actingAs($user)->patch("/collection/cards/{$card->id}", ['quantity' => -1]);

    $response->assertSessionHasErrors('quantity');
    expect($collection->cards()->first()->pivot->quantity)->toBe(1);
});
