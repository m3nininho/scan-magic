<?php

use App\Data\ProviderCard;
use App\Models\Card;
use App\Models\Collection;
use App\Models\User;
use App\Services\CardImportService;
use Database\Seeders\CardSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

test('seeds only the 100 card dataset in bounded queries without duplicates or nested transactions', function () {
    Http::preventStrayRequests();
    $cards = array_map(fn (int $index): array => [
        'id' => "scryfall-test-{$index}",
        'name' => "Test Card {$index}",
        'oracle_id' => '8512bd08-ecba-4e0e-b65c-8209764a65e5',
        'mana_cost' => '{G}',
        'cmc' => 1,
        'type_line' => 'Creature — Elf Druid',
        'colors' => ['G'],
        'color_identity' => ['G', 'U'],
        'set' => 'lea',
        'set_name' => 'Limited Edition Alpha',
        'lang' => 'en',
        'oracle_text' => '{T}: Add {G}.',
        'image_uris' => ['normal' => 'https://example.test/card.jpg'],
        'legalities' => ['commander' => 'legal'],
        'keywords' => ['Vigilance'],
    ], range(1, 175));
    Http::fake(['https://api.scryfall.com/cards/search*' => Http::response(['data' => $cards])]);

    $queries = 0;
    $maxTransactionLevel = 0;
    $initialTransactionLevel = DB::transactionLevel();
    $recording = true;
    DB::listen(function (QueryExecuted $query) use (&$queries, &$maxTransactionLevel, &$recording): void {
        if ($recording) {
            $queries++;
            $maxTransactionLevel = max($maxTransactionLevel, $query->connection->transactionLevel());
        }
    });

    $this->seed(CardSeeder::class);
    $recording = false;
    expect($queries)->toBeLessThanOrEqual(50);
    expect($maxTransactionLevel)->toBe($initialTransactionLevel + 1);
    $originalId = Card::where('provider_id', 'scryfall-test-1')->sole()->id;
    $this->seed(CardSeeder::class);

    $this->assertDatabaseCount('cards', 100);
    $this->assertDatabaseMissing('cards', ['provider_id' => 'scryfall-test-101']);
    $card = Card::where('provider_id', 'scryfall-test-1')->sole();
    expect($card->id)->toBe($originalId)
        ->and($card->provider)->toBe('scryfall')
        ->and($card->name)->toBe('Test Card 1')
        ->and($card->mana_cost)->toBe('{G}')
        ->and($card->text)->toBe('{T}: Add {G}.')
        ->and($card->oracle_id)->toBe('8512bd08-ecba-4e0e-b65c-8209764a65e5')
        ->and($card->cmc)->toBe(1.0)
        ->and($card->type)->toBe('Creature — Elf Druid')
        ->and($card->keywords)->toBe(['Vigilance'])
        ->and($card->image_url)->toBe('https://example.test/card.jpg')
        ->and($card->image_uris)->toBe(['normal' => 'https://example.test/card.jpg'])
        ->and($card->set_code)->toBe('lea')
        ->and($card->set_name)->toBe('Limited Edition Alpha')
        ->and($card->api_data)->toBe($cards[0])
        ->and($card->colors->pluck('code')->all())->toBe(['G'])
        ->and($card->colorIdentity->pluck('code')->all())->toBe(['G', 'U'])
        ->and($card->formats->sole()->name)->toBe('commander')
        ->and($card->legalities->sole()->legality)->toBe('legal');
    $this->assertDatabaseCount('card_color', 100);
    $this->assertDatabaseCount('card_color_identity', 200);
    $this->assertDatabaseCount('card_card_type', 300);
    $this->assertDatabaseCount('card_printing', 100);
    $this->assertDatabaseCount('card_legalities', 100);
    $this->assertDatabaseCount('collections', 1);
    $testUser = User::where('email', 'test@example.com')->sole();
    expect(Hash::check('12345', $testUser->password))->toBeTrue();
    expect(Collection::where('user_id', $testUser->id)->sole()->cards()->count())->toBe(100);
    $this->post(route('login.store'), ['email' => 'test@example.com', 'password' => '12345'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($testUser);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request['q'] === 'legal:commander lang:en'
        && $request['page'] === 1
        && $request['order'] === 'name'
        && $request['dir'] === 'asc'
        && $request['unique'] === 'cards');
});

test('rejects incomplete or duplicate Scryfall results before importing', function (array $cards) {
    Http::preventStrayRequests();
    Http::fake(['https://api.scryfall.com/cards/search*' => Http::response(['data' => $cards])]);

    expect(fn () => $this->seed(CardSeeder::class))
        ->toThrow(RuntimeException::class, 'A Scryfall não retornou 100 cartas distintas. Nenhuma carta foi importada.');

    $this->assertDatabaseCount('cards', 0);
    Http::assertSentCount(1);
})->with([
    'empty page' => [[]],
    'duplicate identifiers' => [array_fill(0, 100, ['id' => 'same-card', 'name' => 'Test Card', 'legalities' => ['commander' => 'legal']])],
    'missing identifiers' => [array_fill(0, 100, ['name' => 'Test Card', 'legalities' => ['commander' => 'legal']])],
    'not legal in commander' => [array_map(fn (int $index): array => ['id' => "card-{$index}", 'name' => "Card {$index}", 'legalities' => ['commander' => 'banned']], range(1, 100))],
]);

test('Scryfall failures abort seeding without importing cards', function () {
    Http::preventStrayRequests();
    Http::fake(['https://api.scryfall.com/cards/search*' => Http::response([], 503)]);

    expect(fn () => $this->seed(CardSeeder::class))->toThrow(RequestException::class);

    $this->assertDatabaseCount('cards', 0);
    Http::assertSentCount(1);
});

test('dataset refresh removes stale colors while preserving full imports and unrelated cards', function () {
    $importer = app(CardImportService::class);
    $existing = $importer->import(ProviderCard::fromScryfall([
        'id' => 'dataset-1',
        'name' => 'Existing Card',
        'type_line' => 'Creature — Elf',
        'colors' => ['R'],
        'color_identity' => ['R'],
        'set' => 'lea',
        'image_uris' => ['normal' => 'https://example.test/retained.jpg'],
        'legalities' => ['commander' => 'banned', 'vintage' => 'legal'],
    ]));
    $otherProvider = Card::create(['provider' => 'other', 'provider_id' => 'dataset-1', 'name' => 'Other Provider', 'api_data' => []]);
    $unrelated = $importer->import(ProviderCard::fromScryfall([
        'id' => 'unrelated', 'name' => 'Unrelated Card', 'colors' => ['R'], 'color_identity' => ['R'],
    ]));
    $cards = array_map(fn (int $index): array => [
        'id' => "dataset-{$index}",
        'name' => "Dataset Card {$index}",
        'type_line' => 'Creature — Elf',
        'colors' => [],
        'color_identity' => [],
        'keywords' => [],
        'legalities' => ['commander' => 'legal', 'modern' => 'legal'],
    ], range(1, 100));
    Http::preventStrayRequests();
    Http::fake(['https://api.scryfall.com/cards/search*' => Http::response(['data' => $cards])]);

    $this->seed(CardSeeder::class);

    $existing->refresh();
    expect($existing->name)->toBe('Dataset Card 1')
        ->and($existing->colors)->toBeEmpty()
        ->and($existing->colorIdentity)->toBeEmpty()
        ->and($existing->keywords)->toBe([])
        ->and($existing->image_url)->toBe('https://example.test/retained.jpg')
        ->and($existing->api_data)->toBe($cards[0])
        ->and($existing->cardTypes->pluck('name')->all())->toBe(['Creature', 'Elf'])
        ->and($existing->printings->pluck('code')->all())->toBe(['lea'])
        ->and($existing->formats->pluck('pivot.legality', 'name')->all())->toBe(['commander' => 'legal', 'vintage' => 'legal', 'modern' => 'legal']);
    expect($otherProvider->fresh()->name)->toBe('Other Provider');
    expect($unrelated->fresh()->colors->pluck('code')->all())->toBe(['R']);
    $this->assertDatabaseCount('cards', 102);
    $this->assertDatabaseHas('formats', ['name' => 'modern']);
    Http::assertSentCount(1);
});

test('adds the dataset only to the test user without changing existing quantities', function (string $environment, int $expectedCount) {
    $this->app->instance('env', $environment);
    $otherUser = User::factory()->create(['name' => 'Luiz Augusto', 'email' => 'luiz@email.com']);
    $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
    $collection = Collection::create(['user_id' => $user->id, 'name' => 'My Collection']);
    $otherCollection = Collection::create(['user_id' => $otherUser->id, 'name' => 'My Collection']);
    foreach (['dataset-1', 'original-2', 'original-3'] as $providerId) {
        $card = Card::create(['provider' => 'scryfall', 'provider_id' => $providerId, 'name' => $providerId, 'api_data' => []]);
        $collection->cards()->attach($card->id, ['quantity' => 3]);
    }
    $originalQuantities = $collection->cards()->pluck('quantity', 'cards.id')->all();
    Http::preventStrayRequests();
    Http::fake(['https://api.scryfall.com/cards/search*' => Http::response(['data' => array_map(fn (int $index): array => [
        'id' => "dataset-{$index}", 'name' => "Dataset Card {$index}", 'legalities' => ['commander' => 'legal'],
    ], range(1, 100))])]);

    $this->artisan('db:seed', ['--class' => CardSeeder::class, '--force' => true])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => CardSeeder::class, '--force' => true])->assertSuccessful();

    expect($collection->cards()->count())->toBe($expectedCount);
    expect($collection->cards()->whereIn('cards.id', array_keys($originalQuantities))->pluck('quantity', 'cards.id')->all())->toBe($originalQuantities);
    expect($collection->cards()->whereNotIn('cards.id', array_keys($originalQuantities))->wherePivot('quantity', '!=', 1)->exists())->toBeFalse();
    expect($otherCollection->cards()->count())->toBe(0);
    Http::assertSentCount(2);
})->with([
    'local' => ['local', 102],
    'development' => ['development', 102],
]);

test('refuses production or non SQLite databases before fetching or writing cards', function (string $environment, string $connection) {
    $this->app->instance('env', $environment);
    $originalConnection = config('database.default');
    config(['database.default' => $connection]);
    Http::preventStrayRequests();
    Http::fake();

    try {
        expect(fn () => $this->artisan('db:seed', ['--class' => CardSeeder::class, '--force' => true])->run())
            ->toThrow(RuntimeException::class, 'CardSeeder só pode ser executado no SQLite local de desenvolvimento/testes.');
    } finally {
        config(['database.default' => $originalConnection]);
    }

    Http::assertNothingSent();
    $this->assertDatabaseCount('cards', 0);
})->with([
    'production SQLite' => ['production', 'sqlite'],
    'local PostgreSQL' => ['local', 'pgsql'],
]);

test('selects different card types and imports double face images and names', function () {
    $cards = array_map(fn (int $index): array => [
        'id' => "varied-{$index}", 'name' => "Creature {$index}", 'type_line' => 'Creature — Elf',
        'legalities' => ['commander' => 'legal'],
    ], range(1, 100));
    foreach (['Land', 'Instant', 'Sorcery', 'Artifact', 'Enchantment', 'Planeswalker'] as $type) {
        $cards[] = ['id' => $type, 'name' => $type, 'type_line' => $type, 'legalities' => ['commander' => 'legal']];
    }
    $cards[100]['card_faces'] = [
        ['name' => 'Front', 'type_line' => 'Land', 'image_uris' => ['normal' => 'https://example.test/front.jpg']],
        ['name' => 'Back', 'type_line' => 'Land', 'image_uris' => ['normal' => 'https://example.test/back.jpg']],
    ];
    Http::preventStrayRequests();
    Http::fake(['https://api.scryfall.com/cards/search*' => Http::response(['data' => $cards])]);

    $this->seed(CardSeeder::class);

    $this->assertDatabaseCount('cards', 100);
    expect(Card::query()->distinct()->pluck('type')->all())->toContain('Creature — Elf', 'Land', 'Instant', 'Sorcery', 'Artifact', 'Enchantment', 'Planeswalker');
    $land = Card::where('provider_id', 'Land')->sole();
    expect($land->image_url)->toBe('https://example.test/front.jpg')
        ->and($land->card_faces)->toBe($cards[100]['card_faces'])
        ->and($land->names->pluck('name')->all())->toBe(['Front', 'Back']);
    Http::assertSentCount(1);
});
