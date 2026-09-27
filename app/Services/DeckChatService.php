<?php

namespace App\Services;

use App\Ai\Agents\MagicDeckAgent;
use App\Models\Collection;
use App\Models\User;

class DeckChatService
{
    public function send(User $user, string $message): string
    {
        $collection = Collection::query()
            ->where('user_id', $user->id)
            ->where('name', 'My Collection')
            ->first();

        $cards = $collection?->cards()
            ->select([
                'cards.id',
                'cards.name',
                'cards.type',
                'cards.mana_cost',
                'cards.cmc',
                'cards.text',
            ])
            ->get();

        $cardContext = $cards?->map(function ($card) {
            return implode(' | ', [
                $card->id,
                $card->name,
                $card->type,
                $card->mana_cost,
                $card->text,
            ]);
        })->implode("\n");

        $prompt = <<<PROMPT
            Pedido do jogador:
            {$message}

            Cartas disponíveis na coleção:
            {$cardContext}

            Responda considerando as cartas disponíveis acima.
            Não diga que não possui acesso à coleção.
            Não invente que o jogador possui cartas que não aparecem na lista.
            PROMPT;

        $agent = new MagicDeckAgent();

        $response = $agent->prompt(
            $prompt,
            provider: 'openai',
            model: 'gpt-4.1-nano'
        );

        return $response->text;
    }
}
