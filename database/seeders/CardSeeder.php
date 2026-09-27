<?php

namespace Database\Seeders;

use App\Data\ProviderCard;
use App\Models\Card;
use App\Models\CardType;
use App\Models\Collection;
use App\Models\Color;
use App\Models\Format;
use App\Models\Set;
use App\Models\User;
use App\Providers\ScryfallCardProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ScryfallCardProvider $provider): void
    {
        if (! app()->environment('local', 'development', 'testing') || DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('CardSeeder só pode ser executado no SQLite local de desenvolvimento/testes.');
        }

        $cards = collect($provider->searchPage('legal:commander lang:en'))
            ->filter(fn (ProviderCard $card): bool => $card->providerId !== ''
                && $card->name !== ''
                && ($card->legalities['commander'] ?? null) === 'legal')
            ->unique('providerId');

        $varied = collect();
        foreach (['Creature', 'Land', 'Instant', 'Sorcery', 'Artifact', 'Enchantment', 'Planeswalker'] as $type) {
            $varied = $varied->concat($cards->filter(fn (ProviderCard $card): bool => in_array($type, $card->types, true))->take(10));
        }
        $cards = $varied->concat($cards)->unique('providerId')->take(100)->values();

        if ($cards->count() !== 100) {
            throw new RuntimeException('A Scryfall não retornou 100 cartas distintas. Nenhuma carta foi importada.');
        }

        DB::transaction(function () use ($cards): void {
            $rows = $cards->map(fn (ProviderCard $card): array => $this->cardAttributes($card));
            foreach ($rows->groupBy(fn (array $row): string => implode('|', array_keys($row))) as $group) {
                foreach ($group->chunk(10) as $chunk) {
                    Card::upsert($chunk->all(), ['provider', 'provider_id'], array_diff(array_keys($chunk->first()), ['provider', 'provider_id']));
                }
            }

            $cardIds = Card::where('provider', 'scryfall')
                ->whereIn('provider_id', $cards->pluck('providerId'))
                ->pluck('id', 'provider_id');

            $now = now();
            $colorNames = ['W' => 'White', 'U' => 'Blue', 'B' => 'Black', 'R' => 'Red', 'G' => 'Green'];
            $colorCodes = $cards->flatMap(fn (ProviderCard $card): array => array_merge($card->colors, $card->colorIdentity))
                ->unique()->values();

            if ($colorCodes->isNotEmpty()) {
                Color::insertOrIgnore($colorCodes->map(fn (string $code): array => [
                    'code' => $code,
                    'name' => $colorNames[$code] ?? $code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }

            $colorIds = Color::whereIn('code', $colorCodes)->pluck('id', 'code');
            $formatNames = $cards->flatMap(fn (ProviderCard $card): array => array_keys($card->legalities))->unique();
            Format::insertOrIgnore($formatNames->map(fn (string $name): array => ['name' => $name, 'created_at' => $now, 'updated_at' => $now])->all());
            $formatIds = Format::whereIn('name', $formatNames)->pluck('id', 'name');

            $types = [];
            $sets = [];
            foreach ($cards as $card) {
                foreach (['type' => $card->types, 'supertype' => $card->supertypes, 'subtype' => $card->subtypes] as $category => $names) {
                    foreach ($names as $name) {
                        $types[$category.'|'.$name] = ['category' => $category, 'name' => $name, 'created_at' => $now, 'updated_at' => $now];
                    }
                }
                if ($card->set !== null) {
                    $sets[$card->set] = ['code' => $card->set, 'name' => $card->setName, 'created_at' => $now, 'updated_at' => $now];
                }
            }
            if ($types !== []) {
                CardType::insertOrIgnore(array_values($types));
            }
            if ($sets !== []) {
                Set::insertOrIgnore(array_values($sets));
            }
            $typeIds = CardType::all()->mapWithKeys(fn (CardType $type): array => [$type->category.'|'.$type->name => $type->id]);
            $setIds = Set::whereIn('code', array_keys($sets))->pluck('id', 'code');

            $colors = [];
            $identities = [];
            $legalities = [];
            $cardTypes = [];
            $printings = [];
            $faceNames = [];
            foreach ($cards as $card) {
                $cardId = $cardIds[$card->providerId];
                foreach (array_unique($card->colors) as $code) {
                    $colors[] = ['card_id' => $cardId, 'color_id' => $colorIds[$code]];
                }
                foreach (array_unique($card->colorIdentity) as $code) {
                    $identities[] = ['card_id' => $cardId, 'color_id' => $colorIds[$code]];
                }
                foreach ($card->legalities as $format => $legality) {
                    $legalities[] = ['card_id' => $cardId, 'format_id' => $formatIds[$format], 'legality' => $legality];
                }
                foreach (['type' => $card->types, 'supertype' => $card->supertypes, 'subtype' => $card->subtypes] as $category => $names) {
                    foreach (array_unique($names) as $name) {
                        $cardTypes[] = ['card_id' => $cardId, 'card_type_id' => $typeIds[$category.'|'.$name]];
                    }
                }
                if ($card->set !== null) {
                    $printings[] = ['card_id' => $cardId, 'set_id' => $setIds[$card->set]];
                }
                foreach ($card->names as $position => $name) {
                    $faceNames[] = ['card_id' => $cardId, 'name' => $name, 'position' => $position, 'created_at' => $now, 'updated_at' => $now];
                }
            }

            foreach (['card_color' => $colors, 'card_color_identity' => $identities, 'card_card_type' => $cardTypes, 'card_names' => $faceNames] as $table => $pivots) {
                DB::table($table)->whereIn('card_id', $cardIds->values())->delete();
                if ($pivots !== []) {
                    foreach (array_chunk($pivots, 150) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            foreach (array_chunk($legalities, 200) as $chunk) {
                DB::table('card_legalities')->upsert($chunk, ['card_id', 'format_id'], ['legality']);
            }
            if ($printings !== []) {
                DB::table('card_printing')->insertOrIgnore($printings);
            }

            $user = User::where('email', 'test@example.com')->first() ?? new User(['email' => 'test@example.com']);
            $user->fill(['name' => 'Test User', 'password' => '12345']);
            $user->setAttribute('email_verified_at', $user->email_verified_at ?? $now);
            $user->save();
            $collection = Collection::where('user_id', $user->id)->where('name', 'My Collection')->first()
                ?? new Collection(['user_id' => $user->id, 'name' => 'My Collection']);
            $collection->save();
            $missingIds = $cardIds->values()->diff($collection->cards()->pluck('cards.id'));
            if ($missingIds->isNotEmpty()) {
                $collection->cards()->attach($missingIds->all(), ['quantity' => 1]);
            }
        });

        $this->command->info('100 cartas da Scryfall importadas ou atualizadas.');
    }

    /** @return array<string, mixed> */
    private function cardAttributes(ProviderCard $card): array
    {
        $attributes = [];
        foreach ((new Card)->getFillable() as $column) {
            $property = match ($column) {
                'set_code' => 'set',
                'source' => 'provider',
                'api_data' => 'raw',
                default => Str::camel($column),
            };
            if (property_exists($card, $property) && $card->{$property} !== null) {
                $attributes[$column] = $card->{$property};
            }
        }
        $attributes['image_url'] = $card->imageUrl ?? $card->cardFaces[0]['image_uris']['normal'] ?? null;

        return (new Card(array_filter($attributes, fn (mixed $value): bool => $value !== null)))->getAttributes();
    }
}
