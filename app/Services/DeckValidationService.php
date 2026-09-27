<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Collection;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeckValidationService
{
    public const FORMATS = ['commander', 'standard', 'modern', 'pioneer', 'pauper', 'legacy', 'vintage', 'unspecified'];

    /**
     * @param  array<string, mixed>  $input
     * @param  list<int>|null  $allowedIds  IDs actually supplied to the model; null when saving.
     * @return array<string, mixed>
     */
    public function validate(User $user, array $input, ?array $allowedIds = null): array
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'format' => ['required', Rule::in(self::FORMATS)],
            'strategy' => ['nullable', 'string', 'max:240'],
            'commander_card_id' => ['nullable', 'integer', 'min:1'],
            'cards' => ['present', 'array', 'max:500'],
            'cards.*' => ['array:card_id,quantity'],
            'cards.*.card_id' => ['required', 'integer', 'min:1'],
            'cards.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ])->validate();

        $format = $data['format'];
        $maximum = $format === 'commander' ? 100 : PHP_INT_MAX;
        $commanderId = $format === 'commander' && isset($data['commander_card_id']) ? (int) $data['commander_card_id'] : null;
        $requested = [];
        foreach ($data['cards'] as $row) {
            $id = (int) $row['card_id'];
            $requested[$id] = ($requested[$id] ?? 0) + (int) $row['quantity'];
        }
        if ($commanderId !== null) {
            $requested = [$commanderId => $requested[$commanderId] ?? 1] + $requested;
        }
        $collection = Collection::where('user_id', $user->id)->where('name', 'My Collection')->first();
        $owned = $collection?->cards()->whereIn('cards.id', array_keys($requested))->wherePivot('quantity', '>', 0)
            ->with(['colorIdentity', 'formats'])->get()->keyBy('id') ?? collect();
        $commander = $commanderId !== null ? $owned->get($commanderId) : null;
        if ($commanderId !== null && ($commander === null || ! $commander->canBeCommander()
            || ! $this->legal($commander, $format) || ($allowedIds !== null && ! in_array($commanderId, $allowedIds, true)))) {
            throw ValidationException::withMessages(['commander_card_id' => 'Comandante inválido ou indisponível na sua coleção.']);
        }

        $rows = [];
        $used = [];
        $total = 0;
        $warnings = [];
        foreach ($requested as $id => $quantity) {
            $card = $owned->get($id);
            if ($card === null || ($allowedIds !== null && ! in_array($id, $allowedIds, true)) || ! $this->legal($card, $format)
                || ($commander !== null && ! $this->compatible($card, $commander))) {
                $warnings[] = 'Cartas inválidas, indisponíveis ou incompatíveis foram removidas.';

                continue;
            }
            $key = $card->oracle_id ?: strtolower($card->name);
            $limit = $id === $commanderId ? 1 : $this->copyLimit($card, $format);
            $accepted = min($quantity, (int) $card->getRelation('pivot')->getAttribute('quantity'), max(0, $limit - ($used[$key] ?? 0)), $maximum - $total);
            if ($accepted !== $quantity) {
                $warnings[] = 'Quantidades ajustadas às cópias possuídas e às regras do formato.';
            }
            if ($accepted === 0) {
                continue;
            }
            $used[$key] = ($used[$key] ?? 0) + $accepted;
            $total += $accepted;
            $rows[] = ['card_id' => $id, 'name' => $card->name, 'quantity' => $accepted, 'role' => $id === $commanderId ? 'commander' : 'main'];
        }

        return [
            'name' => $data['name'], 'format' => $format, 'strategy' => $data['strategy'] ?? '',
            'commander_card_id' => $commanderId, 'commander' => $commander?->name,
            'cards' => $rows, ...$this->status($format, $total, $commanderId),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function legal(Card $card, string $format): bool
    {
        if ($format === 'unspecified') {
            return true;
        }
        $legality = $card->formats->firstWhere('name', $format)?->getRelation('pivot')->getAttribute('legality');

        return $legality === 'legal' || ($format === 'vintage' && $legality === 'restricted');
    }

    private function compatible(Card $card, Card $commander): bool
    {
        $identity = $card->colorIdentity->pluck('code');
        if (str_contains($card->type ?? '', 'Land')) {
            foreach (['Plains' => 'W', 'Island' => 'U', 'Swamp' => 'B', 'Mountain' => 'R', 'Forest' => 'G'] as $type => $color) {
                if (preg_match('/\b'.$type.'\b/', $card->type ?? '')) {
                    $identity->push($color);
                }
            }
        }

        return $identity->diff($commander->colorIdentity->pluck('code'))->isEmpty();
    }

    private function copyLimit(Card $card, string $format): int
    {
        if ($format === 'vintage' && $card->formats->firstWhere('name', $format)?->getRelation('pivot')->getAttribute('legality') === 'restricted') {
            return 1;
        }
        if (preg_match('/\bBasic\b/', $card->type ?? '') || str_contains($card->text ?? '', 'A deck can have any number of cards named')) {
            return PHP_INT_MAX;
        }
        if (preg_match('/A deck can have up to (\d+|two|three|four|five|six|seven|eight|nine|ten) cards named/', $card->text ?? '', $match)) {
            return is_numeric($match[1]) ? (int) $match[1] : ['two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10][$match[1]];
        }

        return match ($format) {
            'commander' => 1, 'unspecified' => PHP_INT_MAX, default => 4
        };
    }

    /** @return array{total: int, target: int|null, complete: bool} */
    public function status(string $format, int $total, ?int $commanderId): array
    {
        $target = match ($format) {
            'commander' => 100, 'unspecified' => null, default => 60
        };

        return ['total' => $total, 'target' => $target, 'complete' => $target !== null && ($format === 'commander' ? $total === 100 && $commanderId !== null : $total >= $target)];
    }

    /** @return array<string, mixed> */
    public function present(Deck $deck): array
    {
        $rows = $deck->cards->map(fn (Card $card): array => [
            'card_id' => $card->id, 'name' => $card->name, 'quantity' => (int) $card->getRelation('pivot')->getAttribute('quantity'), 'role' => $card->getRelation('pivot')->getAttribute('role'),
        ])->values()->all();

        return [
            'id' => $deck->id, 'name' => $deck->name, 'format' => $deck->format, 'strategy' => $deck->strategy,
            'commander_card_id' => $deck->commander_card_id, 'commander' => $deck->commander?->name,
            'cards' => $rows, ...$this->status($deck->format, array_sum(array_column($rows, 'quantity')), $deck->commander_card_id),
            'warnings' => [],
        ];
    }
}
