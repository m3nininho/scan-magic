<?php

use App\Contracts\CardRecognitionService;
use App\Enums\CardScanStatus;
use App\Models\Card;
use App\Models\CardScan;
use App\Models\Collection;
use App\Models\User;
use App\Services\CollectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function fakeScryfallCardPayload(): array
{
    return ['id' => 'bolt-1', 'name' => 'Lightning Bolt', 'type_line' => 'Instant', 'colors' => ['R'], 'color_identity' => ['R'], 'set' => 'm10', 'set_name' => 'Magic 2010', 'oracle_text' => 'Deals 3 damage.', 'legalities' => ['commander' => 'legal']];
}

test('scanner stores the upload and exposes provider candidates from fake OCR', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::response(fakeScryfallCardPayload(), 200)]);
    app()->bind(CardRecognitionService::class, fn () => new class implements CardRecognitionService
    {
        public function recognize(string $imagePath): string
        {
            return 'Lightninq BoIt';
        }
    });

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/scanner/scan', ['image' => UploadedFile::fake()->image('card.jpg')]);

    $response->assertOk();
    expect(CardScan::first()->ocr_raw_text)->toBe('Lightninq BoIt')
        ->and(CardScan::first()->user_id)->toBe($user->id)
        ->and(CardScan::first()->detected_name)->toBe('Lightning Bolt')
        ->and(CardScan::first()->status->value)->toBe('needs_confirmation');
    Storage::disk('local')->assertExists(CardScan::first()->original_image_path);
});

test('scanner marks the scan as failed when recognition throws an exception', function () {
    Storage::fake('local');
    app()->bind(CardRecognitionService::class, fn () => new class implements CardRecognitionService
    {
        public function recognize(string $imagePath): string
        {
            throw new RuntimeException('OCR unavailable');
        }
    });
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/scanner/scan', ['image' => UploadedFile::fake()->image('card.jpg')], ['Accept' => 'application/json']);

    $response->assertServiceUnavailable()
        ->assertJsonPath('errors.image.0', 'Não foi possível ler a carta agora. Tente novamente.');
    expect(CardScan::first()->status)->toBe(CardScanStatus::Failed)
        ->and(CardScan::first()->user_id)->toBe($user->id);
});

test('manual search creates a confirmable scan for the authenticated user', function () {
    Http::fake(['*' => Http::response(fakeScryfallCardPayload(), 200)]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/scanner/search', ['name' => 'Lightning Bolt']);

    $scan = CardScan::first();
    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('scanner')
        ->where('scan.id', $scan->id)
        ->where('candidates.0.name', 'Lightning Bolt'));
    expect($scan->user_id)->toBe($user->id)
        ->and($scan->original_image_path)->toBeNull()
        ->and($scan->status)->toBe(CardScanStatus::NeedsConfirmation);
});

test('manual search marks the scan as failed when the provider is unavailable', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/scanner/search', ['name' => 'Lightning Bolt']);

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('scanner')
        ->where('searchError', 'A busca está indisponível no momento. Tente novamente.'));
    expect(CardScan::first()->status)->toBe(CardScanStatus::Failed);
});

test('confirmation imports the selected card and adds it to the user collection', function () {
    Http::fake(['*' => Http::response(fakeScryfallCardPayload(), 200)]);
    $user = User::factory()->create();
    $scan = CardScan::create(['user_id' => $user->id, 'original_image_path' => 'scans/card.jpg', 'detected_name' => 'Lightning Bolt', 'status' => 'needs_confirmation']);

    $response = $this->actingAs($user)->post("/scanner/{$scan->id}/confirm", ['provider_id' => 'bolt-1']);

    $response->assertRedirect('/scanner');
    expect(Card::where('provider_id', 'bolt-1')->count())->toBe(1)
        ->and(Collection::where('user_id', $user->id)->first()->cards()->first()->pivot->quantity)->toBe(1)
        ->and($scan->fresh()->card_id)->toBe(Card::where('provider_id', 'bolt-1')->first()->id)
        ->and($scan->fresh()->status->value)->toBe('confirmed');

    $card = $scan->fresh()->card;
    expect($card->colors->pluck('code')->all())->toBe(['R'])
        ->and($card->colorIdentity->pluck('code')->all())->toBe(['R'])
        ->and($card->cardTypes->pluck('name')->all())->toBe(['Instant'])
        ->and($card->printings->pluck('code')->all())->toBe(['m10'])
        ->and($card->legalities->pluck('legality')->all())->toBe(['legal']);

    $nextScan = CardScan::create(['user_id' => $user->id, 'original_image_path' => 'scans/next.jpg', 'status' => 'needs_confirmation']);
    $this->post("/scanner/{$nextScan->id}/confirm", ['provider_id' => 'bolt-1'])->assertRedirect('/scanner');
    expect(Card::count())->toBe(1)
        ->and(Collection::where('user_id', $user->id)->first()->cards()->first()->pivot->quantity)->toBe(2)
        ->and($nextScan->fresh()->card_id)->toBe($card->id)
        ->and($card->fresh()->legalities)->toHaveCount(1);
});

test('collection and scan roll back together while the imported catalog remains available', function () {
    $this->withoutExceptionHandling();
    $baseline = DB::transactionLevel(); // RefreshDatabase owns the test transaction.
    Http::fake(function () use ($baseline) {
        expect(DB::transactionLevel())->toBe($baseline);

        return Http::response(fakeScryfallCardPayload());
    });
    $user = User::factory()->create();
    $this->actingAs($user);
    $scan = CardScan::create(['user_id' => $user->id, 'original_image_path' => 'scans/card.jpg', 'status' => 'needs_confirmation']);
    $this->app->instance(CollectionService::class, new class($baseline) extends CollectionService
    {
        public function __construct(private int $baseline) {}

        public function addCard(Collection $collection, Card $card, int $quantity = 1): int
        {
            expect(DB::transactionLevel())->toBe($this->baseline + 1);
            parent::addCard($collection, $card, $quantity);
            throw new RuntimeException('Collection write failed');
        }
    });

    expect(fn () => $this->post("/scanner/{$scan->id}/confirm", ['provider_id' => 'bolt-1']))
        ->toThrow(RuntimeException::class, 'Collection write failed');

    expect(Card::count())->toBe(1)
        ->and(Collection::count())->toBe(0)
        ->and(DB::table('collection_cards')->count())->toBe(0)
        ->and($scan->fresh()->card_id)->toBeNull()
        ->and($scan->fresh()->status->value)->toBe('needs_confirmation');
});

test('a user cannot confirm another users scan', function () {
    Http::fake();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $scan = CardScan::create(['user_id' => $owner->id, 'original_image_path' => 'scans/card.jpg', 'status' => 'needs_confirmation']);

    $response = $this->actingAs($otherUser)->post("/scanner/{$scan->id}/confirm", ['provider_id' => 'bolt-1']);

    $response->assertNotFound();
    expect($scan->fresh()->status)->toBe(CardScanStatus::NeedsConfirmation);
    Http::assertNothingSent();
});

test('confirming the same scan twice does not increment the collection twice', function () {
    Http::fake(['*' => Http::response(fakeScryfallCardPayload(), 200)]);
    $user = User::factory()->create();
    $scan = CardScan::create(['user_id' => $user->id, 'original_image_path' => 'scans/card.jpg', 'status' => 'needs_confirmation']);

    $this->actingAs($user)->post("/scanner/{$scan->id}/confirm", ['provider_id' => 'bolt-1'])->assertRedirect('/scanner');
    $response = $this->post("/scanner/{$scan->id}/confirm", ['provider_id' => 'bolt-1']);

    $response->assertRedirect('/scanner');
    expect(Collection::where('user_id', $user->id)->first()->cards()->first()->pivot->quantity)->toBe(1);
    Http::assertSentCount(1);
});

test('confirmation removes the stored image after adding the card', function () {
    Storage::fake('local');
    Storage::disk('local')->put('scans/card.jpg', 'image');
    Http::fake(['*' => Http::response(fakeScryfallCardPayload(), 200)]);
    $user = User::factory()->create();
    $scan = CardScan::create(['user_id' => $user->id, 'original_image_path' => 'scans/card.jpg', 'status' => 'needs_confirmation']);

    $response = $this->actingAs($user)->post("/scanner/{$scan->id}/confirm", ['provider_id' => 'bolt-1']);

    $response->assertRedirect('/scanner');
    Storage::disk('local')->assertMissing('scans/card.jpg');
    expect($scan->fresh()->original_image_path)->toBeNull();
});

test('confirmation preserves the scan when the provider is unavailable', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $user = User::factory()->create();
    $scan = CardScan::create(['user_id' => $user->id, 'original_image_path' => 'scans/card.jpg', 'status' => 'needs_confirmation']);

    $response = $this->actingAs($user)->post("/scanner/{$scan->id}/confirm", ['provider_id' => 'unavailable-card']);

    $response->assertSessionHasErrors('provider_id');
    expect($scan->fresh()->status)->toBe(CardScanStatus::NeedsConfirmation)
        ->and(Collection::count())->toBe(0);
});
