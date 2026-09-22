<?php

use App\Models\Card;
use App\Models\Collection;
use App\Models\Color;
use App\Models\User;

it('shows only the authenticated user collection', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $card = Card::create([
        'provider' => 'scryfall',
        'provider_id' => 'collection-card',
        'name' => 'Collection Card',
        'set_code' => 'tst',
        'set_name' => 'Test Set',
        'api_data' => [],
    ]);
    $color = Color::create(['code' => 'R', 'name' => 'Vermelho']);
    $card->colors()->attach($color);
    $userCollection = Collection::create(['name' => 'My Collection', 'user_id' => $user->id]);
    $otherCollection = Collection::create(['name' => 'My Collection', 'user_id' => $otherUser->id]);
    $userCollection->cards()->attach($card, ['quantity' => 2]);
    $otherCollection->cards()->attach($card, ['quantity' => 5]);

    $response = $this->actingAs($user)->get('/collection');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('collection')
        ->where('cards.data.0.id', $card->id)
        ->where('cards.data.0.pivot.quantity', 2)
        ->where('cards.total', 1)
        ->where('summary.totalCopies', 2)
        ->where('filterOptions.colors.0.value', 'R')
        ->where('filterOptions.colors.0.label', 'Vermelho')
        ->where('filterOptions.sets.0.value', 'tst')
        ->where('filterOptions.sets.0.label', 'Test Set'));
});
