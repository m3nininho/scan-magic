<?php

use App\Ai\Agents\MagicDeckAgent;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;

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
        ->assertExactJson(['message' => 'Uma criatura pode atacar no seu turno.']);

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
