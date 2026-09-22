<?php

use App\Contracts\CardProviderInterface;
use App\Data\ProviderCard;
use App\Models\Card;
use App\Models\CardType;
use App\Models\Collection;
use App\Services\CardImportService;
use App\Services\CardNameMatcher;
use App\Services\CollectionService;
use Illuminate\Support\Facades\Http;

function providerCardFixture(): ProviderCard
{
    return ProviderCard::fromArray([
        'id' => 'lightning-001',
        'name' => 'Lightning Bolt',
        'layout' => 'normal',
        'manaCost' => '{R}',
        'cmc' => 1,
        'type' => 'Instant',
        'types' => ['Instant'],
        'colors' => ['R'],
        'colorIdentity' => ['R'],
        'rarity' => 'Common',
        'set' => 'M10',
        'setName' => 'Magic 2010',
        'text' => 'Lightning Bolt deals 3 damage to any target.',
        'artist' => 'Christopher Moeller',
        'number' => '146',
        'printings' => ['LEA', 'M10'],
        'legalities' => ['Commander' => 'Legal', 'Modern' => 'Legal'],
        'rulings' => [['date' => '2009-07-08', 'text' => 'This is a ruling.']],
        'foreignNames' => [['language' => 'Portuguese (Brazil)', 'name' => 'Raio', 'multiverseid' => 1]],
        'names' => ['Lightning Bolt'],
    ]);
}

test('imports a card and all normalized relationships idempotently', function () {
    $service = app(CardImportService::class);
    $service->import(providerCardFixture());
    $card = $service->import(providerCardFixture());

    expect(Card::count())->toBe(1)
        ->and($card->colors)->toHaveCount(1)
        ->and($card->colorIdentity)->toHaveCount(1)
        ->and($card->cardTypes)->toHaveCount(1)
        ->and($card->printings)->toHaveCount(2)
        ->and($card->legalities)->toHaveCount(2)
        ->and($card->rulings)->toHaveCount(1)
        ->and($card->foreignNames)->toHaveCount(1)
        ->and($card->names)->toHaveCount(1)
        ->and($card->api_data['name'])->toBe('Lightning Bolt');
});

test('adds the same card to a collection by incrementing quantity', function () {
    $card = app(CardImportService::class)->import(providerCardFixture());
    $collection = Collection::create(['name' => 'My Collection']);
    $service = app(CollectionService::class);

    $service->addCard($collection, $card);
    $service->addCard($collection, $card);

    expect($collection->cards()->count())->toBe(1)
        ->and($collection->cards()->first()->pivot->quantity)->toBe(2);
});

test('a card can belong to multiple collections', function () {
    $card = app(CardImportService::class)->import(providerCardFixture());
    $first = Collection::create(['name' => 'Binder']);
    $second = Collection::create(['name' => 'Playset']);
    $service = app(CollectionService::class);

    $service->addCard($first, $card, 2);
    $service->addCard($second, $card, 4);

    expect($card->collections()->count())->toBe(2);
});

test('provider maps api responses without requiring the internet', function () {
    Http::fake(['https://api.scryfall.com/*' => Http::response([
        'id' => 'bolt-1',
        'name' => 'Lightning Bolt',
        'set' => 'lea',
        'set_name' => 'Limited Edition Alpha',
        'type_line' => 'Instant',
        'colors' => ['R'],
    ], 200)]);

    $cards = app(CardProviderInterface::class)->searchByName('Lightning Bolt');

    expect($cards)->toHaveCount(1)->and($cards[0]->providerId)->toBe('bolt-1');
    Http::assertSent(fn ($request): bool => parse_url($request->url(), PHP_URL_PATH) === '/cards/named');
});

test('empty OCR text does not search the provider', function () {
    Http::fake();

    $result = app(CardNameMatcher::class)->match('');

    expect($result->detectedName)->toBeNull()->and($result->candidates)->toBeEmpty();
    Http::assertNothingSent();
});

test('duplicate OCR lines search the provider only once', function () {
    $provider = new class implements CardProviderInterface
    {
        public int $searches = 0;

        public function searchByName(string $name): array
        {
            $this->searches++;

            return [new ProviderCard(provider: 'fake', providerId: 'bolt-1', name: 'Lightning Bolt')];
        }

        public function find(string $id): ?ProviderCard
        {
            return null;
        }
    };
    app()->instance(CardProviderInterface::class, $provider);

    $result = app(CardNameMatcher::class)->match("Lightning Bolt\nLightning Bolt");

    expect($result->detectedName)->toBe('Lightning Bolt')
        ->and($provider->searches)->toBe(1);
});

test('provider caches repeated name searches', function () {
    Http::fake(['*' => Http::response([
        'id' => 'cached-card',
        'name' => 'Cached Card',
        'type_line' => 'Artifact',
    ], 200)]);
    $provider = app(CardProviderInterface::class);

    $provider->searchByName('Cached Card');
    $provider->searchByName('Cached Card');

    Http::assertSentCount(1);
});

test('maps a Scryfall card object without losing its type line structure', function () {
    $card = app(CardImportService::class)->import(ProviderCard::fromScryfall([
        'id' => 'a6d58067-337d-43dc-b4a3-c6acc701d450',
        'oracle_id' => '8512bd08-ecba-4e0e-b65c-8209764a65e5',
        'layout' => 'normal',
        'name' => "Cloud, Planet's Champion",
        'type_line' => 'Legendary Creature — Human Soldier Mercenary',
        'set' => 'fin',
        'set_name' => 'Final Fantasy',
        'oracle_text' => 'During your turn, as long as Cloud is equipped.',
        'colors' => ['R', 'W'],
        'color_identity' => ['R', 'W'],
        'legalities' => ['commander' => 'legal', 'modern' => 'legal', 'legacy' => 'legal', 'vintage' => 'legal'],
        'image_uris' => ['normal' => 'https://example.test/cloud.jpg'],
        'keywords' => [],
        'finishes' => ['nonfoil'],
        'games' => ['paper'],
    ]));

    expect($card->name)->toBe("Cloud, Planet's Champion")
        ->and($card->type)->toBe('Legendary Creature — Human Soldier Mercenary')
        ->and($card->cardTypes()->where('category', 'type')->pluck('name')->all())->toContain('Creature')
        ->and($card->cardTypes()->where('category', 'supertype')->pluck('name')->all())->toContain('Legendary')
        ->and($card->cardTypes()->where('category', 'subtype')->pluck('name')->all())->toBe(['Human', 'Soldier', 'Mercenary'])
        ->and($card->provider_id)->toBe('a6d58067-337d-43dc-b4a3-c6acc701d450')
        ->and($card->oracle_id)->toBe('8512bd08-ecba-4e0e-b65c-8209764a65e5')
        ->and($card->colors->pluck('code')->all())->toBe(['R', 'W'])
        ->and($card->colorIdentity->pluck('code')->all())->toBe(['R', 'W'])
        ->and($card->legalities)->toHaveCount(4)
        ->and(CardType::where('name', '')->exists())->toBeFalse()
        ->and($card->api_data['set'])->toBe('fin');
});

test('Scryfall reimport preserves relationships that were not loaded', function () {
    $service = app(CardImportService::class);
    $card = $service->import(providerCardFixture());
    $rulingId = $card->rulings->first()->id;
    $foreignNameId = $card->foreignNames->first()->id;

    $card = $service->import(ProviderCard::fromScryfall([
        'id' => 'lightning-001', 'name' => 'Lightning Bolt', 'type_line' => 'Instant', 'set' => 'm10',
    ]));

    expect($card->rulings->first()->id)->toBe($rulingId)
        ->and($card->foreignNames->first()->id)->toBe($foreignNameId)
        ->and($card->printings->pluck('code')->all())->toContain('LEA', 'M10', 'm10');

    $card = $service->import(ProviderCard::fromArray([
        'id' => 'lightning-001', 'name' => 'Lightning Bolt',
        'rulings' => [], 'foreignNames' => [], 'printings' => [],
    ]));
    expect($card->rulings)->toBeEmpty()
        ->and($card->foreignNames)->toBeEmpty()
        ->and($card->printings)->toBeEmpty();
});

test('imports multiface types without turning the second face into subtypes', function () {
    $line = 'Creature — Human // Land';
    $card = app(CardImportService::class)->import(ProviderCard::fromScryfall([
        'id' => 'multiface-test', 'name' => 'Front // Back', 'layout' => 'modal_dfc', 'type_line' => $line,
        'card_faces' => [
            ['name' => 'Front', 'type_line' => 'Creature — Human'],
            ['name' => 'Back', 'type_line' => 'Land'],
        ],
    ]));
    expect($card->type)->toBe($line)
        ->and($card->cardTypes()->where('category', 'type')->pluck('name')->all())->toBe(['Creature', 'Land'])
        ->and($card->cardTypes()->where('category', 'subtype')->pluck('name')->all())->toBe(['Human']);
});
