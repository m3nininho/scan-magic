<?php

namespace App\Services;

use App\Ai\Agents\MagicDeckAgent;
use App\Models\Card;
use App\Models\Collection;
use App\Models\Color;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * @phpstan-type ChatContext array{format?: string, colors?: list<string>, strategy?: string, commander?: int, last_request?: string}
 */
class DeckChatService
{
    /**
     * @param  ChatContext  $context
     * @return array{message: string, context: ChatContext, deck?: array<string, mixed>}
     */
    public function send(User $user, string $message, array $context = []): array
    {
        $context = Arr::only($context, ['format', 'colors', 'strategy', 'commander', 'last_request']);
        $text = Str::lower(Str::ascii($message));
        $deck = preg_match('/\b(deck|baralho|commander|comandante|edh)\b/', $text) === 1;
        $owned = preg_match('/\b(colecao|minhas? cartas|meus? decks?|possuo|tenho|disponiveis)\b/', $text) === 1;
        $request = preg_match('/\b(mont\w*|cri[ae]\w*|quero|sug\w*|recomend\w*|melhor\w*|build|quais|selecione)\b/', $text) === 1;
        $needsCards = $owned || ($request && ($deck || preg_match('/\b(cartas?|criaturas?|terrenos?)\b/', $text)))
            || preg_match('/^(deck|baralho)\b/', $text);
        $general = ! $owned && preg_match('/\b(como funciona|o que e|quais (sao )?as regras|quantas cartas|cartas (sao )?(banidas|legais))\b/', $text);
        $colors = $this->requestedColors($text);
        $strategy = $this->requestedStrategy($text);
        $continuation = ! $general && ($context['last_request'] ?? null) === 'deck'
            && ! $needsCards && mb_strlen($text) <= 120
            && ($colors !== null || $strategy !== null || preg_match('/^(com |use |prefiro |troque |mude |agora |mais |menos )/', $text));
        if ($general) {
            $needsCards = false;
        } elseif ($continuation) {
            $needsCards = $deck = true;
        }

        $prompt = $message;
        $candidateIds = [];
        if ($needsCards) {
            if (! $continuation) {
                $context = [];
            }
            $context['last_request'] = $deck ? 'deck' : 'cards';
            if (preg_match('/\b(commander|comandante|edh|standard|modern|pioneer|pauper|legacy|vintage)\b/', $text, $format)) {
                $newFormat = in_array($format[1], ['comandante', 'edh']) ? 'commander' : $format[1];
                if ($newFormat !== ($context['format'] ?? null)) {
                    unset($context['commander']);
                }
                $context['format'] = $newFormat;
            }
            if ($strategy !== null) {
                $context['strategy'] = $strategy;
            }
            $cards = $this->cardContext($user, $text, $deck, $context, $candidateIds);
            $prompt .= "\nContexto: ".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n".$cards;
        }

        $generate = $needsCards && $deck && $candidateIds !== [] && ($continuation || preg_match('/\b(mont\w*|cri[ae]\w*|quero|build|sugira|gere|gerar)\b/', $text) || preg_match('/^(deck|baralho)\b/', $text));
        $answer = (new MagicDeckAgent((bool) $generate, $context['format'] ?? 'unspecified'))->prompt($prompt, provider: 'openai', model: 'gpt-4.1-nano');
        if ($generate && $answer instanceof StructuredAgentResponse) {
            $validated = app(DeckValidationService::class)->validate($user, ['format' => $context['format'] ?? 'unspecified'] + $answer->toArray(), $candidateIds);
            if ($validated['commander_card_id'] !== null) {
                $context['commander'] = $validated['commander_card_id'];
            } else {
                unset($context['commander']);
            }
            if ($validated['strategy'] !== '') {
                $context['strategy'] = Str::limit($validated['strategy'], 60, '');
            }

            return ['message' => 'Deck selecionado e validado.', 'context' => $context, 'deck' => $validated];
        }
        $response = $answer->text;
        if ($needsCards && $deck && ! isset($context['strategy'])) {
            $suggestedStrategy = $this->requestedStrategy(Str::lower(Str::ascii($response)));
            if ($suggestedStrategy !== null) {
                $context['strategy'] = $suggestedStrategy;
            }
        }

        return [
            'message' => $response,
            'context' => $context,
        ];
    }

    /**
     * @param  ChatContext  $context
     * @param  list<int>  $candidateIds
     */
    private function cardContext(User $user, string $text, bool $deck, array &$context, array &$candidateIds): string
    {
        $collection = Collection::where('user_id', $user->id)->where('name', 'My Collection')->first();
        if ($collection === null) {
            unset($context['commander']);
            if (($colors = $this->requestedColors($text)) !== null) {
                $context['colors'] = $colors;
            }

            return 'Nenhuma coleção encontrada para este usuário. Não presuma cartas possuídas.';
        }

        $commander = ($context['format'] ?? null) === 'commander';
        $query = $collection->cards()->wherePivot('quantity', '>', 0)
            ->select(['cards.id', 'cards.oracle_id', 'cards.name', 'cards.mana_cost', 'cards.cmc', 'cards.type', 'cards.text', 'cards.power', 'cards.toughness', 'cards.loyalty', 'cards.card_faces'])
            ->with(['colorIdentity:id,code', 'cardTypes:id,name,category'])
            ->orderBy('cards.id');
        if ($commander) {
            $query->whereHas('formats', fn (Builder $formats) => $formats
                ->where('formats.name', 'commander')->where('card_legalities.legality', 'legal'));
        }
        $cards = $query->get();
        if ($cards->isEmpty()) {
            unset($context['commander']);
            if (($colors = $this->requestedColors($text)) !== null) {
                $context['colors'] = $colors;
            }

            return 'Nenhuma carta da coleção disponível com estes filtros.';
        }
        $named = $cards->filter(fn (Card $card): bool => str_contains($text, Str::lower(Str::ascii($card->name))));

        $colorRequest = $text;
        foreach ($named as $card) {
            $colorRequest = str_replace(Str::lower(Str::ascii($card->name)), '', $colorRequest);
        }
        $requestedColors = $this->requestedColors($colorRequest);
        if ($requestedColors !== null && $requestedColors !== ($context['colors'] ?? null)) {
            unset($context['commander']);
        }
        $colors = $requestedColors ?? ($context['colors'] ?? null);
        if ($commander) {
            $leaders = $cards->filter(fn (Card $card): bool => $card->canBeCommander())
                ->unique(fn (Card $card): string => $card->oracle_id ?: 'card:'.$card->id);
            $leader = $leaders->first(fn (Card $card): bool => $named->contains('id', $card->id));
            if ($leader === null) {
                $eligible = $leaders->filter(fn (Card $card): bool => $colors === null || $card->colorIdentity->pluck('code')->diff($colors)->isEmpty());
                $leader = $eligible->firstWhere('id', $context['commander'] ?? null)
                    ?? $eligible->sortByDesc(fn (Card $candidate): int => $cards
                        ->filter(fn (Card $card): bool => $card->colorIdentity->pluck('code')->diff($candidate->colorIdentity->pluck('code'))->isEmpty())
                        ->unique(fn (Card $card): string => $card->oracle_id ?: 'card:'.$card->id)->count())->first();
            }
            unset($context['commander']);
            if ($leader !== null) {
                $context['commander'] = $leader->id;
                $colors = array_values($leader->colorIdentity->map(fn (Color $color): string => $color->code)->sort()->all());
                $named = $named->push($leader);
            }
        }
        if ($colors !== null) {
            $context['colors'] = $colors;
            $cards = $cards->filter(fn (Card $card): bool => $card->colorIdentity->pluck('code')->diff($colors)->isEmpty());
        }

        if ($deck && ! isset($context['strategy'])) {
            $tribes = ['elfos' => 'Elf', 'goblins' => 'Goblin', 'zumbis' => 'Zombie', 'vampiros' => 'Vampire', 'dragões' => 'Dragon'];
            $counts = collect($tribes)->map(fn (string $type): int => $cards->filter(fn (Card $card): bool => str_contains($card->type ?? '', $type))
                ->unique(fn (Card $card): string => $card->oracle_id ?: 'card:'.$card->id)->count())->sortDesc();
            $dominantTribe = $counts->keys()->first();
            if ($dominantTribe !== null && $counts->first() >= 3) {
                $context['strategy'] = $dominantTribe;
            }
        }
        $text .= ' '.Str::lower(Str::ascii($context['strategy'] ?? ''));

        $terms = [];
        foreach ([
            'elfos?' => 'elf', 'goblins?' => 'goblin', 'zumbis?' => 'zombie', 'vampiros?' => 'vampire', 'dragoes|dragao' => 'dragon',
            'criaturas?' => 'creature', 'terrenos?' => 'land', 'artefatos?' => 'artifact', 'encantamentos?' => 'enchantment',
            'instantaneas?' => 'instant', 'feiticos?' => 'sorcery', 'comprar|compra' => 'draw', 'remocao|remocoes' => 'destroy',
        ] as $pattern => $term) {
            if (preg_match('/\b('.$pattern.')\b/', $text)) {
                $terms[] = $term;
            }
        }
        if (! $deck && $named->isNotEmpty()) {
            $cards = $cards->whereIn('id', $named->pluck('id'));
        } elseif (! $deck && $terms !== []) {
            $cards = $cards->filter(fn (Card $card): bool => Str::contains(Str::lower(($card->type ?? '').' '.($card->text ?? '')), $terms));
        }

        $candidates = [];
        foreach ($cards->groupBy(fn (Card $card): string => $card->oracle_id ?: 'card:'.$card->id) as $printings) {
            $card = $printings->first();
            $quantity = $printings->sum(fn (Card $printing): int => (int) $printing->getRelation('pivot')->getAttribute('quantity'));
            $row = [
                'id' => $card->id, 'nome' => $card->name, 'quantidade' => $quantity,
                'mana_cost' => $card->mana_cost, 'cmc' => $card->cmc, 'tipo' => $card->type,
                'texto' => $card->text, 'identidade' => $card->colorIdentity->pluck('code')->sort()->values()->all(),
            ];
            if ($printings->count() > 1) {
                $row['impressoes'] = $printings->map(fn (Card $printing): array => ['id' => $printing->id, 'quantidade' => (int) $printing->getRelation('pivot')->getAttribute('quantity')])->values()->all();
            }
            foreach (['power', 'toughness', 'loyalty'] as $attribute) {
                if ($card->{$attribute} !== null) {
                    $row[$attribute] = $card->{$attribute};
                }
            }
            $faces = $card->getAttribute('card_faces');
            if (is_array($faces) && $faces !== []) {
                $row['faces'] = array_map(fn (array $face): array => Arr::only($face, ['name', 'mana_cost', 'type_line', 'oracle_text', 'power', 'toughness', 'loyalty']), $faces);
            }
            $searchable = Str::lower(($card->type ?? '').' '.($card->text ?? ''));
            $score = count(array_filter($terms, fn (string $term): bool => str_contains($searchable, $term)));
            $score += $named->contains('id', $card->id) ? 100 : 0;
            if (($context['strategy'] ?? null) === 'agressivo' && str_contains($card->type ?? '', 'Creature') && $card->cmc !== null && $card->cmc <= 3) {
                $score += 10;
            }
            $bucket = str_contains($card->type ?? '', 'Land') ? 'Land' : ($card->cardTypes->firstWhere('category', 'type')->name ?? 'Other');
            $candidates[] = ['row' => $row, 'score' => $score, 'bucket' => $bucket];
        }

        $buckets = collect($candidates)->sortByDesc('score')->groupBy('bucket')->map->values();
        $selected = [];
        $characters = 0;
        $limit = $deck ? 40 : 10;
        while ($buckets->flatten(1)->isNotEmpty() && count($selected) < $limit) {
            foreach ($buckets as $bucket) {
                $candidate = $bucket->shift();
                if ($candidate === null) {
                    continue;
                }
                $json = json_encode($candidate['row'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                if ($characters + mb_strlen($json) <= 12000 && count($selected) < $limit) {
                    $selected[] = $json;
                    $characters += mb_strlen($json);
                    $candidateIds[] = $candidate['row']['id'];
                    foreach ($candidate['row']['impressoes'] ?? [] as $printing) {
                        $candidateIds[] = $printing['id'];
                    }
                }
            }
        }

        $header = $commander && ! isset($context['commander']) ? "Nenhum comandante elegível para estas cores na coleção. Explique a limitação brevemente e sugira um próximo passo.\n" : '';
        $header .= "Seleção parcial da coleção; quantidade é o total das impressões listadas. Não extrapole IDs/quantidades.\n";

        return $header.($selected === [] ? 'Nenhuma candidata encontrada com estes filtros.' : implode("\n", $selected));
    }

    private function requestedStrategy(string $text): ?string
    {
        foreach (['agressivo' => 'agressiv[oa]|aggro', 'controle' => 'controle|control', 'elfos' => 'elfos?', 'goblins' => 'goblins?', 'zumbis' => 'zumbis?', 'vampiros' => 'vampiros?', 'dragões' => 'dragao|dragoes', 'fichas' => 'fichas|tokens'] as $strategy => $pattern) {
            if (preg_match('/\b('.$pattern.')\b/', $text)) {
                return $strategy;
            }
        }

        return null;
    }

    /** @return list<string>|null */
    private function requestedColors(string $text): ?array
    {
        if (preg_match('/\b(sem|exceto|menos|nao|without|not)\b/', $text)) {
            return null;
        }
        $colors = [];
        foreach (['W' => 'branc[oa]s?|white', 'U' => 'azul|azuis|blue', 'B' => 'pret[oa]s?|negr[oa]s?|black', 'R' => 'vermelh[oa]s?|red', 'G' => 'verdes?|green'] as $code => $pattern) {
            if (preg_match('/\b('.$pattern.')\b|\{'.$code.'\}/i', $text)) {
                $colors[] = $code;
            }
        }

        return $colors !== [] || preg_match('/\b(incolor|incolores|colorless)\b/', $text) ? $colors : null;
    }
}
