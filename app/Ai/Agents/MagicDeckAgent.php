<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

class MagicDeckAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            Você é um especialista em Magic: The Gathering.

            Sua função é ajudar jogadores a montar decks
            utilizando as cartas que possuem.

            Regras:
            - Responda em português ou inglês.
            - Entenda o formato e a estratégia solicitados.
            - Priorize as cartas da coleção do jogador.
            - Não invente cartas ou habilidades.
            - Explique suas sugestões de forma simples.
            - Seja breve para economizar tokens.

            Por enquanto, você ainda não tem acesso
            à coleção de cartas do jogador.
            PROMPT;

    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return [];
    }
}
