<?php

use App\Ai\Agents\MagicDeckAgent;
use App\Models\Card;
use App\Models\Collection;
use App\Models\Color;
use App\Models\Deck;
use App\Models\Format;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;

function deckCollection(?User $user = null): Collection
{
    return Collection::create(['name' => 'My Collection', 'user_id' => ($user ?? User::factory()->create())->id]);
}

function ownedDeckCard(Collection $collection, array $attributes = [], int $quantity = 1, array $colors = ['G'], string $legality = 'legal', string $format = 'commander'): Card
{
    $card = Card::create(array_merge([
        'provider' => 'scryfall', 'provider_id' => (string) Str::uuid(), 'name' => 'Elf '.Str::random(8),
        'type' => 'Creature — Elf', 'text' => 'Reach', 'mana_cost' => '{G}', 'cmc' => 1, 'api_data' => [],
    ], $attributes));
    $collection->cards()->attach($card, ['quantity' => $quantity]);
    foreach ($colors as $code) {
        $card->colorIdentity()->attach(Color::firstOrCreate(['code' => $code], ['name' => $code]));
    }
    $card->formats()->attach(Format::firstOrCreate(['name' => $format]), ['legality' => $legality]);

    return $card;
}

function deckInput(?Card $commander, array $cards, string $format = 'commander'): array
{
    return ['name' => 'Meu deck', 'format' => $format, 'strategy' => 'Atacar com criaturas.', 'commander_card_id' => $commander?->id, 'cards' => $cards];
}

test('structured generation filters invalid candidates and persists only on save without further AI calls', function () {
    $user = User::factory()->create();
    $collection = deckCollection($user);
    $leader = ownedDeckCard($collection, ['name' => 'Elven Leader', 'type' => 'Legendary Creature — Elf']);
    $elf = ownedDeckCard($collection);
    $red = ownedDeckCard($collection, colors: ['R']);
    $foreign = ownedDeckCard(deckCollection());
    MagicDeckAgent::fake([deckInput($leader, [
        ['card_id' => $leader->id, 'quantity' => 1], ['card_id' => $elf->id, 'quantity' => 1],
        ['card_id' => $red->id, 'quantity' => 1], ['card_id' => $foreign->id, 'quantity' => 1], ['card_id' => 999999, 'quantity' => 1],
    ])])->preventStrayPrompts();

    $generated = $this->actingAs($user)->postJson(route('deck-chat.send'), ['message' => 'Monte um Commander com minha coleção'])
        ->assertOk()->assertJsonPath('deck.total', 2)->assertJsonPath('deck.target', 100)->assertJsonPath('deck.complete', false);
    expect(array_column($generated->json('deck.cards'), 'card_id'))->toBe([$leader->id, $elf->id]);
    $this->assertDatabaseCount('decks', 0);
    $this->assertDatabaseCount('deck_cards', 0);
    MagicDeckAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->generatingDeck && $prompt->model === 'gpt-4.1-nano');

    $saved = $this->postJson(route('decks.store'), deckInput($leader, [
        ['card_id' => $leader->id, 'quantity' => 1], ['card_id' => $elf->id, 'quantity' => 1],
    ]))->assertCreated()->assertJsonPath('deck.total', 2);
    $this->get(route('decks.index'))->assertOk();
    $this->get(route('decks.show', $saved->json('deck.id')))->assertOk();
    MagicDeckAgent::assertPromptedTimes(1);
    $this->assertDatabaseHas('decks', ['id' => $saved->json('deck.id'), 'user_id' => $user->id]);
});

test('saving discards unknown and unowned ids and clamps quantities to owned copies', function () {
    $user = User::factory()->create();
    $collection = deckCollection($user);
    $leader = ownedDeckCard($collection, ['type' => 'Legendary Creature — Elf']);
    $land = ownedDeckCard($collection, ['type' => 'Basic Land — Forest'], 3);
    $foreign = ownedDeckCard(deckCollection());
    MagicDeckAgent::fake()->preventStrayPrompts();

    $saved = $this->actingAs($user)->postJson(route('decks.store'), deckInput($leader, [
        ['card_id' => $land->id, 'quantity' => 50], ['card_id' => 99999, 'quantity' => 1], ['card_id' => $foreign->id, 'quantity' => 1],
    ]) + ['user_id' => 99999])->assertCreated()->assertJsonPath('deck.total', 4)->assertJsonPath('deck.complete', false);

    $this->assertDatabaseHas('decks', ['id' => $saved->json('deck.id'), 'user_id' => $user->id]);
    $this->assertDatabaseHas('deck_cards', ['card_id' => $land->id, 'quantity' => 3]);
    $this->assertDatabaseMissing('deck_cards', ['card_id' => $foreign->id]);
    MagicDeckAgent::assertNeverPrompted();
});

test('invalid or unowned commanders are rejected without an AI call', function (bool $foreign) {
    $user = User::factory()->create();
    $collection = deckCollection($user);
    $commander = ownedDeckCard($foreign ? deckCollection() : $collection, ['type' => $foreign ? 'Legendary Creature — Elf' : 'Creature — Elf']);
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->actingAs($user)->postJson(route('decks.store'), deckInput($commander, []))->assertUnprocessable()->assertJsonValidationErrors('commander_card_id');
    $this->assertDatabaseCount('decks', 0);
    MagicDeckAgent::assertNeverPrompted();
})->with([true, false]);

test('generated invalid commanders are rejected locally without retrying', function () {
    $user = User::factory()->create();
    $card = ownedDeckCard(deckCollection($user));
    MagicDeckAgent::fake([deckInput($card, [['card_id' => $card->id, 'quantity' => 1]])])->preventStrayPrompts();
    $this->actingAs($user)->postJson(route('deck-chat.send'), ['message' => 'Monte um Commander'])->assertUnprocessable()->assertJsonValidationErrors('commander_card_id');
    MagicDeckAgent::assertPromptedTimes(1);
    $this->assertDatabaseCount('decks', 0);
});

test('commander validation removes incompatible colors and illegal cards', function () {
    $user = User::factory()->create();
    $collection = deckCollection($user);
    $leader = ownedDeckCard($collection, ['type' => 'Legendary Creature — Elf']);
    $invalid = [ownedDeckCard($collection, colors: ['G', 'U']), ownedDeckCard($collection, legality: 'banned'),
        ownedDeckCard($collection, legality: 'not_legal'), ownedDeckCard($collection, ['type' => 'Land — Island'], colors: [])];
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->actingAs($user)->postJson(route('decks.store'), deckInput($leader, collect($invalid)
        ->map(fn (Card $card): array => ['card_id' => $card->id, 'quantity' => 1])->all()))
        ->assertCreated()->assertJsonPath('deck.total', 1)->assertJsonCount(1, 'deck.cards');
    $this->assertDatabaseCount('deck_cards', 1);
    MagicDeckAgent::assertNeverPrompted();
});

test('commander limits group printings and basics can fill a complete deck counting the commander once', function () {
    $user = User::factory()->create();
    $collection = deckCollection($user);
    $leader = ownedDeckCard($collection, ['type' => 'Legendary Creature — Elf']);
    $oracle = (string) Str::uuid();
    $first = ownedDeckCard($collection, ['oracle_id' => $oracle], 3);
    $second = ownedDeckCard($collection, ['oracle_id' => $oracle], 3);
    $land = ownedDeckCard($collection, ['type' => 'Basic Land — Forest'], 120);
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->actingAs($user)->postJson(route('decks.store'), deckInput($leader, [
        ['card_id' => $leader->id, 'quantity' => 2], ['card_id' => $first->id, 'quantity' => 3],
        ['card_id' => $second->id, 'quantity' => 3], ['card_id' => $land->id, 'quantity' => 120],
    ]))->assertCreated()->assertJsonPath('deck.total', 100)->assertJsonPath('deck.complete', true);
    $this->assertDatabaseHas('deck_cards', ['card_id' => $leader->id, 'quantity' => 1, 'role' => 'commander']);
    $this->assertDatabaseHas('deck_cards', ['card_id' => $first->id, 'quantity' => 1]);
    $this->assertDatabaseMissing('deck_cards', ['card_id' => $second->id]);
    $this->assertDatabaseHas('deck_cards', ['card_id' => $land->id, 'quantity' => 98]);
    MagicDeckAgent::assertNeverPrompted();
});

test('constructed formats enforce copy and restricted limits', function (string $format, string $legality, int $copies) {
    $user = User::factory()->create();
    $card = ownedDeckCard(deckCollection($user), quantity: 10, legality: $legality, format: $format);
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->actingAs($user)->postJson(route('decks.store'), deckInput(null, [['card_id' => $card->id, 'quantity' => 10]], $format))
        ->assertCreated()->assertJsonPath('deck.total', $copies)->assertJsonPath('deck.target', 60);
    MagicDeckAgent::assertNeverPrompted();
})->with([['standard', 'legal', 4], ['vintage', 'restricted', 1]]);

test('partial decks without commanders are saved as partial', function () {
    $user = User::factory()->create();
    $card = ownedDeckCard(deckCollection($user));
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->actingAs($user)->postJson(route('decks.store'), deckInput(null, [['card_id' => $card->id, 'quantity' => 1]]))
        ->assertCreated()->assertJsonPath('deck.total', 1)->assertJsonPath('deck.complete', false)->assertJsonPath('deck.commander', null);
    MagicDeckAgent::assertNeverPrompted();
});

test('saving checks the collection again after generation', function () {
    $user = User::factory()->create();
    $collection = deckCollection($user);
    $leader = ownedDeckCard($collection, ['type' => 'Legendary Creature — Elf']);
    $land = ownedDeckCard($collection, ['type' => 'Basic Land — Forest'], 20);
    $data = deckInput($leader, [['card_id' => $land->id, 'quantity' => 20]]);
    MagicDeckAgent::fake([$data])->preventStrayPrompts();
    $this->actingAs($user)->postJson(route('deck-chat.send'), ['message' => 'Monte um Commander'])->assertOk()->assertJsonPath('deck.total', 21);
    $collection->cards()->updateExistingPivot($land->id, ['quantity' => 2]);
    $this->postJson(route('decks.store'), $data)->assertCreated()->assertJsonPath('deck.total', 3);
    MagicDeckAgent::assertPromptedTimes(1);
});

test('listing and details only expose decks owned by the authenticated user', function () {
    $user = User::factory()->create();
    $own = Deck::factory()->for($user)->create(['name' => 'Meu deck privado']);
    $foreign = Deck::factory()->create(['name' => 'Deck de outra pessoa']);
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->actingAs($user)->get(route('decks.index'))->assertOk()->assertInertia(fn ($page) => $page->component('decks/index')->has('decks.data', 1)->where('decks.data.0.id', $own->id));
    $this->get(route('decks.show', $own))->assertOk()->assertInertia(fn ($page) => $page->component('decks/show')->where('deck.id', $own->id));
    $this->get(route('decks.show', $foreign))->assertNotFound();
    $this->patchJson('/decks/'.$foreign->id, ['name' => 'Invadido'])->assertMethodNotAllowed();
    $this->deleteJson('/decks/'.$foreign->id)->assertMethodNotAllowed();
    $this->assertDatabaseHas('decks', ['id' => $foreign->id, 'name' => 'Deck de outra pessoa']);
    MagicDeckAgent::assertNeverPrompted();
});

test('guests cannot list open or save decks', function () {
    $deck = Deck::factory()->create();
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->get(route('decks.index'))->assertRedirect(route('login'));
    $this->get(route('decks.show', $deck))->assertRedirect(route('login'));
    $this->postJson(route('decks.store'), [])->assertUnauthorized();
    MagicDeckAgent::assertNeverPrompted();
});

test('a question about commanders does not request a structured deck', function () {
    $user = User::factory()->create();
    ownedDeckCard(deckCollection($user), ['type' => 'Legendary Creature — Elf']);
    MagicDeckAgent::fake(['Este comandante combina com suas criaturas.'])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('deck-chat.send'), ['message' => 'Qual é o melhor comandante da minha coleção?'])
        ->assertOk()->assertJsonMissingPath('deck');
    MagicDeckAgent::assertPrompted(fn (AgentPrompt $prompt): bool => ! $prompt->agent->generatingDeck);
    $this->assertDatabaseCount('decks', 0);
});

test('commander eligibility uses the front face and the current vehicle rules', function (array $attributes, bool $eligible) {
    $card = ownedDeckCard(deckCollection(), $attributes);

    expect($card->canBeCommander())->toBe($eligible);
})->with([
    'vehicle' => [['type' => 'Legendary Artifact — Vehicle'], true],
    'spacecraft with stats' => [['type' => 'Legendary Artifact — Spacecraft', 'power' => '5', 'toughness' => '5'], true],
    'spacecraft without stats' => [['type' => 'Legendary Artifact — Spacecraft'], false],
    'explicit permission' => [['type' => 'Legendary Planeswalker', 'text' => 'This card can be your commander.'], true],
    'legendary back only' => [['type' => 'Enchantment // Legendary Creature', 'card_faces' => [['type_line' => 'Enchantment'], ['type_line' => 'Legendary Creature']]], false],
]);

test('oracle text copy exceptions remain bounded by ownership', function (string $text, int $expected) {
    $user = User::factory()->create();
    $card = ownedDeckCard(deckCollection($user), ['text' => $text], quantity: 12);
    MagicDeckAgent::fake()->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('decks.store'), deckInput(null, [['card_id' => $card->id, 'quantity' => 20]]))
        ->assertCreated()->assertJsonPath('deck.total', $expected);
    MagicDeckAgent::assertNeverPrompted();
})->with([
    ['A deck can have any number of cards named Test.', 12],
    ['A deck can have up to seven cards named Test.', 7],
]);

test('empty or malformed decks create no records', function (array $input) {
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->actingAs(User::factory()->create())->postJson(route('decks.store'), $input)->assertUnprocessable();
    $this->assertDatabaseCount('decks', 0);
    MagicDeckAgent::assertNeverPrompted();
})->with([
    'empty cards' => [deckInput(null, [])],
    'negative quantity' => [deckInput(null, [['card_id' => 1, 'quantity' => -1]])],
    'unknown format' => [deckInput(null, [], 'inventado')],
]);
