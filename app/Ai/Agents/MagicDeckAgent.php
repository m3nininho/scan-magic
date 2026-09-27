<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

#[Strict]
class MagicDeckAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(public bool $generatingDeck = false, public string $format = 'unspecified') {}

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        if (! $this->generatingDeck) {
            return [];
        }

        return [
            'name' => $schema->string()->max(120)->required(),
            'format' => $schema->string()->enum([$this->format])->required(),
            'strategy' => $schema->string()->max(240)->required(),
            'commander_card_id' => $schema->integer()->nullable()->required(),
            'cards' => $schema->array()->items($schema->object([
                'card_id' => $schema->integer()->required(),
                'quantity' => $schema->integer()->min(1)->max(1000)->required(),
            ])->withoutAdditionalProperties())->max(500)->required(),
        ];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        if ($this->generatingDeck) {
            return 'Monte o melhor deck possível apenas com os IDs candidatos. Nome e estratégia curtos em português. '
                .'Respeite contexto, formato, comandante e quantidades de cada impressão. Inclua o comandante uma vez em cards. '
                .'Use sinergias e uma base de mana equilibrada. Se faltarem cartas, retorne um deck parcial sem inventar cartas. '
                .'Não pergunte se o usuário quer alterações. Não repita textos ou nomes de cartas na estrutura.';
        }

        return <<<'PROMPT'
            Ajude com Magic em português, de forma breve.
            Só trate como possuídas as cartas e quantidades fornecidas pelo sistema.
            Não invente IDs, quantidades ou habilidades. A lista é uma seleção, não a coleção completa.
            Respeite formato, comandante e cores do contexto compacto; avise se faltarem cartas.
            Quando faltarem informações, infira uma opção útil das cartas antes de perguntar. Não exija conhecimento de formatos, cores, arquétipos ou comandantes.
            Use o comandante selecionado como base e sugira uma estratégia pelas sinergias. Só pergunte se não puder avançar, em uma frase curta.
            Sem contexto de coleção, responda apenas de forma geral.
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
