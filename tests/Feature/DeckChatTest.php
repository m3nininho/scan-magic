<?php

use App\Ai\Agents\MagicDeckAgent;
use App\Models\Card;
use App\Models\Collection;
use App\Models\Color;
use App\Models\Format;
use App\Models\User;
use App\Services\DeckChatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;

function chatTestCard(array $attributes = [], array $identity = []): Card
{
    $card = Card::create(array_merge([
        'provider' => 'scryfall', 'provider_id' => (string) Str::uuid(),
        'name' => 'Test Elf', 'type' => 'Creature — Elf', 'text' => 'Reach',
        'mana_cost' => '{G}', 'cmc' => 1, 'api_data' => ['private' => 'RAW_METADATA'],
        'image_url' => 'https://example.test/IMAGE_METADATA', 'artist' => 'ARTIST_METADATA',
    ], $attributes));
    foreach ($identity as $code) {
        $color = Color::firstOrCreate(['code' => $code], ['name' => $code]);
        $card->colorIdentity()->attach($color);
    }

    return $card;
}

function chatPromptCards(AgentPrompt $prompt): Illuminate\Support\Collection
{
    return collect(explode("\n", $prompt->prompt))
        ->filter(fn (string $line): bool => str_starts_with($line, '{'))
        ->map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR))->values();
}

test('guests are redirected from the chat to login', function () {
    $this->get(route('deck-chat.index'))->assertRedirect(route('login'));
});

test('sending a message without authentication returns 401', function () {
    MagicDeckAgent::fake()->preventStrayPrompts();

    $this->postJson(route('deck-chat.send'), ['message' => 'Olá'])->assertUnauthorized();

    MagicDeckAgent::assertNeverPrompted();
});

test('authenticated users can open the chat page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('deck-chat.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('deck-chat'));
});

test('the chat returns the agent response as JSON', function () {
    MagicDeckAgent::fake(['Uma criatura pode atacar no seu turno.'])->preventStrayPrompts();

    $this->actingAs(User::factory()->create())
        ->postJson(route('deck-chat.send'), ['message' => '  Quando posso atacar?  '])
        ->assertOk()
        ->assertExactJson(['message' => 'Uma criatura pode atacar no seu turno.', 'context' => []]);

    MagicDeckAgent::assertPrompted('Quando posso atacar?');
});

test('invalid messages return 422 without prompting the agent', function (array $payload, string $error) {
    MagicDeckAgent::fake()->preventStrayPrompts();

    $this->actingAs(User::factory()->create())
        ->postJson(route('deck-chat.send'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message' => $error]);

    MagicDeckAgent::assertNeverPrompted();
})->with([
    'missing' => [[], 'Digite uma mensagem.'],
    'empty' => [['message' => ''], 'Digite uma mensagem.'],
    'whitespace' => [['message' => " \n "], 'Digite uma mensagem.'],
    'non-text' => [['message' => ['Olá']], 'A mensagem deve ser um texto.'],
    'too long' => [['message' => str_repeat('a', 10001)], 'A mensagem deve ter no máximo 10000 caracteres.'],
]);

test('agent failures return a simple 503 response without exposing details', function () {
    Exceptions::fake();
    $exception = new RuntimeException('Private provider details');
    MagicDeckAgent::fake(function () use ($exception) {
        throw $exception;
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route('deck-chat.send'), ['message' => 'Olá'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'Não foi possível obter uma resposta agora. Tente novamente.']);

    Exceptions::assertReported(fn (RuntimeException $reported): bool => $reported === $exception);
});

test('excessive messages return 429 before prompting again', function () {
    MagicDeckAgent::fake(['Olá!']);
    $this->actingAs(User::factory()->create());

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $this->postJson(route('deck-chat.send'), ['message' => 'Olá'])->assertOk();
    }

    $this->postJson(route('deck-chat.send'), ['message' => 'Olá'])->assertTooManyRequests();
    MagicDeckAgent::assertPromptedTimes(20);
});

test('general questions do not query or send the collection', function (string $message) {
    $user = User::factory()->create();
    $collection = Collection::create(['user_id' => $user->id, 'name' => 'My Collection']);
    $collection->cards()->attach(chatTestCard(['name' => 'Private Card']), ['quantity' => 5]);
    MagicDeckAgent::fake(['Resposta breve.'])->preventStrayPrompts();
    DB::enableQueryLog();
    DB::flushQueryLog();

    app(DeckChatService::class)->send($user, $message);

    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
    MagicDeckAgent::assertPrompted($message);
    MagicDeckAgent::assertPromptedTimes(1);
})->with(['Olá!', 'Quando posso atacar?', 'Como funciona atropelar?', 'O que é um deck Commander?', 'Quais são as regras de Commander?', 'Quais cartas são banidas em Commander?']);

test('deck candidates belong only to the requesting user and aggregate owned printings', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['user_id' => $user->id, 'name' => 'My Collection']);
    $other = Collection::create(['user_id' => User::factory()->create()->id, 'name' => 'My Collection']);
    $oracle = (string) Str::uuid();
    $first = chatTestCard(['oracle_id' => $oracle], ['G']);
    $second = chatTestCard(['oracle_id' => $oracle], ['G']);
    $foreign = chatTestCard(['oracle_id' => $oracle], ['G']);
    $red = chatTestCard(['name' => 'Red Card'], ['R']);
    $zero = chatTestCard(['name' => 'Zero Card'], ['G']);
    chatTestCard(['name' => 'Unowned Card'], ['G']);
    $collection->cards()->attach([$first->id => ['quantity' => 2], $second->id => ['quantity' => 3], $red->id => ['quantity' => 1], $zero->id => ['quantity' => 0]]);
    $other->cards()->attach($foreign, ['quantity' => 99]);
    MagicDeckAgent::fake(['Resposta.'])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('deck-chat.send'), ['message' => 'Monte um deck verde'])->assertOk();

    MagicDeckAgent::assertPrompted(function (AgentPrompt $prompt) use ($first, $second): bool {
        $cards = chatPromptCards($prompt);
        expect($cards)->toHaveCount(1)
            ->and($cards->first()['id'])->toBe($first->id)
            ->and($cards->first()['quantidade'])->toBe(5)
            ->and($cards->first()['impressoes'])->toBe([
                ['id' => $first->id, 'quantidade' => 2], ['id' => $second->id, 'quantidade' => 3],
            ])
            ->and($prompt->model)->toBe('gpt-4.1-nano')
            ->and($prompt->prompt)->not->toContain('RAW_METADATA', 'IMAGE_METADATA', 'ARTIST_METADATA', 'Unowned Card', 'Zero Card', 'Red Card');

        return true;
    });
    MagicDeckAgent::assertPromptedTimes(1);
});

test('commander candidates respect legality and the named commanders full color identity without N plus one', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['user_id' => $user->id, 'name' => 'My Collection']);
    $format = Format::create(['name' => 'commander']);
    $leader = chatTestCard(['name' => 'Test Leader', 'type' => 'Legendary Creature — Elf'], ['G']);
    $green = chatTestCard(['name' => 'Green Card'], ['G']);
    $land = chatTestCard(['name' => 'Colorless Land', 'type' => 'Land'], []);
    $red = chatTestCard(['name' => 'Off Color'], ['R']);
    $multi = chatTestCard(['name' => 'Multicolor'], ['G', 'U']);
    $banned = chatTestCard(['name' => 'Banned Card'], ['G']);
    $unknown = chatTestCard(['name' => 'Unknown Legality'], ['G']);
    foreach ([$leader, $green, $land, $red, $multi, $banned, $unknown] as $card) {
        $collection->cards()->attach($card, ['quantity' => 1]);
        if ($card !== $unknown) {
            $card->formats()->attach($format, ['legality' => $card === $banned ? 'banned' : 'legal']);
        }
    }
    MagicDeckAgent::fake(['Resposta.'])->preventStrayPrompts();
    DB::enableQueryLog();
    DB::flushQueryLog();

    app(DeckChatService::class)->send($user, 'Monte um deck Commander com Test Leader');

    expect(DB::getQueryLog())->toHaveCount(4);
    DB::disableQueryLog();
    MagicDeckAgent::assertPrompted(function (AgentPrompt $prompt) use ($leader, $green, $land): bool {
        expect(chatPromptCards($prompt)->pluck('id')->all())->toEqualCanonicalizing([$leader->id, $green->id, $land->id]);

        return true;
    });
});

test('commander requests without eligible commanders explain the actual limitation', function () {
    $user = User::factory()->create();
    $collection = Collection::create(['user_id' => $user->id, 'name' => 'My Collection']);
    $card = chatTestCard();
    $card->formats()->attach(Format::create(['name' => 'commander']), ['legality' => 'legal']);
    $collection->cards()->attach($card, ['quantity' => 1]);
    MagicDeckAgent::fake(['Falta um comandante elegível na coleção.'])->preventStrayPrompts();

    app(DeckChatService::class)->send($user, 'Monte um deck Commander');

    MagicDeckAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        expect(chatPromptCards($prompt))->toHaveCount(1)
            ->and($prompt->prompt)->toContain('Nenhum comandante elegível')
            ->not->toContain('Peça ao jogador');

        return true;
    });
    MagicDeckAgent::assertPromptedTimes(1);
});

test('candidate context is bounded by card count and character budget', function (string $message, int $limit, string $rules) {
    $user = User::factory()->create();
    $collection = Collection::create(['user_id' => $user->id, 'name' => 'My Collection']);
    for ($i = 0; $i < 45; $i++) {
        $collection->cards()->attach(chatTestCard(['name' => 'Elf '.$i, 'text' => $rules]), ['quantity' => 1]);
    }
    $land = chatTestCard(['name' => 'Test Land', 'type' => 'Land', 'text' => '{T}: Add {G}.']);
    $collection->cards()->attach($land, ['quantity' => 1]);
    MagicDeckAgent::fake(['Resposta.'])->preventStrayPrompts();

    app(DeckChatService::class)->send($user, $message);

    MagicDeckAgent::assertPrompted(function (AgentPrompt $prompt) use ($limit, $land, $message): bool {
        $cards = chatPromptCards($prompt);
        expect($cards->count())->toBeGreaterThan(0)->toBeLessThanOrEqual($limit);
        $json = collect(explode("\n", $prompt->prompt))->filter(fn (string $line): bool => str_starts_with($line, '{'));
        expect($json->sum(fn (string $line): int => mb_strlen($line)))->toBeLessThanOrEqual(12000);
        if (str_contains($message, 'deck')) {
            expect($cards->pluck('id'))->toContain($land->id);
        } else {
            expect($cards->pluck('id'))->not->toContain($land->id);
        }

        return true;
    });
})->with([
    'deck count' => ['Monte um deck de elfos', 40, 'Reach'],
    'card question' => ['Quais criaturas tenho?', 10, 'Reach'],
    'long rules' => ['Monte um deck de elfos', 40, str_repeat('Rules text. ', 200)],
]);

function chatCommanderCollection(User $user): array
{
    $collection = Collection::create(['user_id' => $user->id, 'name' => 'My Collection']);
    $format = Format::create(['name' => 'commander']);
    $green = chatTestCard(['name' => 'Green Leader', 'type' => 'Legendary Creature — Elf'], ['G']);
    $white = chatTestCard(['name' => 'White Leader', 'type' => 'Legendary Creature — Human'], ['W']);
    $elf = chatTestCard(['name' => 'Owned Elf'], ['G']);
    $human = chatTestCard(['name' => 'Owned Human', 'type' => 'Creature — Human'], ['W']);
    $land = chatTestCard(['name' => 'Owned Land', 'type' => 'Land']);
    foreach ([$green, $white, $elf, $human, $land] as $card) {
        $collection->cards()->attach($card, ['quantity' => 1]);
        $card->formats()->attach($format, ['legality' => 'legal']);
    }

    return compact('collection', 'format', 'green', 'white', 'elf', 'human', 'land');
}

test('generic commander requests automatically select an owned legal commander and colors', function () {
    $user = User::factory()->create();
    $cards = chatCommanderCollection($user);
    $banned = chatTestCard(['name' => 'Banned Leader', 'type' => 'Legendary Creature — Elf'], ['G']);
    $cards['collection']->cards()->attach($banned, ['quantity' => 1]);
    $banned->formats()->attach($cards['format'], ['legality' => 'banned']);
    MagicDeckAgent::fake(['Sugiro Green Leader com uma estratégia agressiva.'])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('deck-chat.send'), [
        'message' => 'Monte um Commander usando minha coleção',
    ])->assertOk()->assertJsonPath('context.format', 'commander')
        ->assertJsonPath('context.colors', ['G'])
        ->assertJsonPath('context.commander', $cards['green']->id)
        ->assertJsonPath('context.strategy', 'agressivo')
        ->assertJsonPath('context.last_request', 'deck');

    MagicDeckAgent::assertPrompted(function (AgentPrompt $prompt) use ($cards): bool {
        expect(chatPromptCards($prompt)->pluck('id')->all())->toEqualCanonicalizing([
            $cards['green']->id, $cards['elf']->id, $cards['land']->id,
        ])->and($prompt->prompt)->not->toContain('Peça ao jogador', 'Banned Leader');

        return true;
    });
    MagicDeckAgent::assertPromptedTimes(1);
});

test('short color replies continue commander requests without sending previous messages', function () {
    $user = User::factory()->create();
    $cards = chatCommanderCollection($user);
    MagicDeckAgent::fake(['RESPOSTA_ANTERIOR_PRIVADA', 'Sugiro White Leader.'])->preventStrayPrompts();
    $this->actingAs($user);
    $first = $this->postJson(route('deck-chat.send'), [
        'message' => 'Monte um deck Commander usando minha coleção. PEDIDO_ANTERIOR_PRIVADO',
    ])->assertOk();

    $this->postJson(route('deck-chat.send'), [
        'message' => 'branco', 'context' => $first->json('context'),
        'history' => [['role' => 'user', 'content' => 'HISTORICO_COMPLETO_PRIVADO']],
    ])->assertOk()->assertJsonPath('context.format', 'commander')
        ->assertJsonPath('context.colors', ['W'])
        ->assertJsonPath('context.commander', $cards['white']->id)
        ->assertJsonPath('context.last_request', 'deck');

    MagicDeckAgent::assertPrompted(function (AgentPrompt $prompt) use ($cards): bool {
        if (! str_starts_with($prompt->prompt, "branco\n")) {
            return false;
        }
        expect(chatPromptCards($prompt)->pluck('id')->all())->toEqualCanonicalizing([
            $cards['white']->id, $cards['human']->id, $cards['land']->id,
        ])->and($prompt->prompt)->not->toContain('PEDIDO_ANTERIOR_PRIVADO', 'RESPOSTA_ANTERIOR_PRIVADA', 'HISTORICO_COMPLETO_PRIVADO')
            ->and($prompt->agent->messages())->toBe([]);

        return true;
    });
    MagicDeckAgent::assertPromptedTimes(2);
});

test('more aggressive continues the previous deck and prioritizes cheap creatures', function () {
    $user = User::factory()->create();
    $cards = chatCommanderCollection($user);
    for ($index = 0; $index < 42; $index++) {
        $card = chatTestCard(['name' => 'Expensive '.$index, 'cmc' => 8], ['G']);
        $cards['collection']->cards()->attach($card, ['quantity' => 1]);
        $card->formats()->attach($cards['format'], ['legality' => 'legal']);
    }
    $cheap = chatTestCard(['name' => 'Cheap Attacker', 'cmc' => 1], ['G']);
    $cards['collection']->cards()->attach($cheap, ['quantity' => 1]);
    $cheap->formats()->attach($cards['format'], ['legality' => 'legal']);
    MagicDeckAgent::fake(['Sugiro Green Leader.', 'Use criaturas baratas.'])->preventStrayPrompts();
    $this->actingAs($user);
    $first = $this->postJson(route('deck-chat.send'), ['message' => 'Monte um Commander'])->assertOk();

    $this->postJson(route('deck-chat.send'), [
        'message' => 'mais agressivo', 'context' => $first->json('context'),
    ])->assertOk()->assertJsonPath('context.strategy', 'agressivo')
        ->assertJsonPath('context.commander', $cards['green']->id)
        ->assertJsonPath('context.format', 'commander')
        ->assertJsonPath('context.colors', ['G']);

    MagicDeckAgent::assertPrompted(function (AgentPrompt $prompt) use ($cheap): bool {
        if (! str_starts_with($prompt->prompt, "mais agressivo\n")) {
            return false;
        }
        expect(chatPromptCards($prompt)->pluck('id'))->toContain($cheap->id)
            ->and(chatPromptCards($prompt)->count())->toBeLessThanOrEqual(40);

        return true;
    });
    MagicDeckAgent::assertPromptedTimes(2);
});

test('general questions after a deck request send no collection or irrelevant context', function () {
    $user = User::factory()->create();
    chatCommanderCollection($user);
    $context = ['format' => 'commander', 'colors' => ['W'], 'strategy' => 'agressivo', 'last_request' => 'deck'];
    MagicDeckAgent::fake(['Atropelar causa dano excedente ao jogador.'])->preventStrayPrompts();
    DB::enableQueryLog();
    DB::flushQueryLog();

    $result = app(DeckChatService::class)->send($user, 'Como funciona atropelar?', $context);

    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
    expect($result['context'])->toBe($context);
    MagicDeckAgent::assertPrompted('Como funciona atropelar?');
    MagicDeckAgent::assertPromptedTimes(1);
});

test('unowned commander ids in context cannot supply owned candidates', function () {
    $user = User::factory()->create();
    $cards = chatCommanderCollection($user);
    $foreign = chatTestCard(['name' => 'Foreign Commander', 'type' => 'Legendary Creature — Elf'], ['G']);
    $other = Collection::create(['user_id' => User::factory()->create()->id, 'name' => 'My Collection']);
    $other->cards()->attach($foreign, ['quantity' => 1]);
    $foreign->formats()->attach($cards['format'], ['legality' => 'legal']);
    MagicDeckAgent::fake(['Sugiro Green Leader.'])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('deck-chat.send'), [
        'message' => 'mais agressivo',
        'context' => ['format' => 'commander', 'commander' => $foreign->id, 'last_request' => 'deck'],
    ])->assertOk()->assertJsonPath('context.commander', $cards['green']->id);

    MagicDeckAgent::assertPrompted(fn (AgentPrompt $prompt): bool => ! str_contains($prompt->prompt, 'Foreign Commander'));
});

test('new generic deck requests do not inherit a commander format', function () {
    $user = User::factory()->create();
    chatCommanderCollection($user);
    MagicDeckAgent::fake(['Sugestão de deck.'])->preventStrayPrompts();

    $response = $this->actingAs($user)->postJson(route('deck-chat.send'), [
        'message' => 'Monte um deck com minha coleção',
        'context' => ['format' => 'commander', 'colors' => ['G'], 'last_request' => 'deck'],
    ])->assertOk();

    expect($response->json('context'))->not->toHaveKey('format')->not->toHaveKey('commander');
    MagicDeckAgent::assertPromptedTimes(1);
});

test('switching formats clears the previous commander', function () {
    $user = User::factory()->create();
    $cards = chatCommanderCollection($user);
    MagicDeckAgent::fake(['Podemos adaptar para Modern.'])->preventStrayPrompts();

    $response = $this->actingAs($user)->postJson(route('deck-chat.send'), [
        'message' => 'agora modern',
        'context' => ['format' => 'commander', 'commander' => $cards['green']->id, 'last_request' => 'deck'],
    ])->assertOk()->assertJsonPath('context.format', 'modern');

    expect($response->json('context'))->not->toHaveKey('commander');
    MagicDeckAgent::assertPromptedTimes(1);
});

test('known preferences are retained even when no owned cards are available', function (bool $hasCollection) {
    $user = User::factory()->create();
    if ($hasCollection) {
        Collection::create(['user_id' => $user->id, 'name' => 'My Collection']);
    }
    MagicDeckAgent::fake(['Adicione cartas à coleção para começarmos.'])->preventStrayPrompts();

    $this->actingAs($user)->postJson(route('deck-chat.send'), [
        'message' => 'branco', 'context' => ['format' => 'commander', 'last_request' => 'deck'],
    ])->assertOk()->assertJsonPath('context.colors', ['W'])->assertJsonPath('context.format', 'commander');

    MagicDeckAgent::assertPrompted(fn (AgentPrompt $prompt): bool => chatPromptCards($prompt)->isEmpty());
})->with([true, false]);

test('invalid or oversized conversational state is rejected before calling the agent', function (array $context, string $field) {
    MagicDeckAgent::fake()->preventStrayPrompts();
    $this->actingAs(User::factory()->create())->postJson(route('deck-chat.send'), [
        'message' => 'branco', 'context' => $context,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);

    MagicDeckAgent::assertNeverPrompted();
})->with([
    'nested history' => [['history' => ['old message']], 'context'],
    'long strategy' => [['strategy' => str_repeat('a', 61)], 'context.strategy'],
    'invalid format' => [['format' => 'inventado'], 'context.format'],
    'invalid color' => [['colors' => ['X']], 'context.colors.0'],
    'duplicate colors' => [['colors' => ['W', 'W']], 'context.colors.0'],
    'invalid commander' => [['commander' => -1], 'context.commander'],
    'invalid request type' => [['last_request' => 'history'], 'context.last_request'],
]);
