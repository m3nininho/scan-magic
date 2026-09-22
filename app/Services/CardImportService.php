<?php

namespace App\Services;

use App\Data\ProviderCard;
use App\Models\Card;
use App\Models\CardType;
use App\Models\Color;
use App\Models\Format;
use App\Models\Set;
use Illuminate\Support\Facades\DB;

class CardImportService
{
    public function import(ProviderCard $providerCard): Card
    {
        return DB::transaction(function () use ($providerCard): Card {
            $card = Card::updateOrCreate(
                ['provider' => $providerCard->provider, 'provider_id' => $providerCard->providerId],
                array_filter([
                    'name' => $providerCard->name,
                    'oracle_id' => $providerCard->oracleId,
                    'layout' => $providerCard->layout,
                    'mana_cost' => $providerCard->manaCost,
                    'cmc' => $providerCard->cmc,
                    'type' => $providerCard->type,
                    'rarity' => $providerCard->rarity,
                    'set_code' => $providerCard->set,
                    'set_name' => $providerCard->setName,
                    'set_id' => $providerCard->setId,
                    'set_type' => $providerCard->setType,
                    'text' => $providerCard->text,
                    'flavor' => $providerCard->flavor,
                    'artist' => $providerCard->artist,
                    'number' => $providerCard->number,
                    'power' => $providerCard->power,
                    'toughness' => $providerCard->toughness,
                    'loyalty' => $providerCard->loyalty,
                    'language' => $providerCard->language,
                    'image_url' => $providerCard->imageUrl,
                    'image_status' => $providerCard->imageStatus,
                    'image_uris' => $providerCard->imageUris,
                    'keywords' => $providerCard->keywords,
                    'produced_mana' => $providerCard->producedMana,
                    'color_indicator' => $providerCard->colorIndicator,
                    'finishes' => $providerCard->finishes,
                    'games' => $providerCard->games,
                    'frame_effects' => $providerCard->frameEffects,
                    'promo_types' => $providerCard->promoTypes,
                    'card_faces' => $providerCard->cardFaces,
                    'all_parts' => $providerCard->allParts,
                    'scryfall_uri' => $providerCard->scryfallUri,
                    'rulings_uri' => $providerCard->rulingsUri,
                    'prints_search_uri' => $providerCard->printsSearchUri,
                    'watermark' => $providerCard->watermark,
                    'border' => $providerCard->border,
                    'timeshifted' => $providerCard->timeshifted,
                    'reserved' => $providerCard->reserved,
                    'starter' => $providerCard->starter,
                    'booster' => $providerCard->booster,
                    'digital' => $providerCard->digital,
                    'promo' => $providerCard->promo,
                    'reprint' => $providerCard->reprint,
                    'full_art' => $providerCard->fullArt,
                    'textless' => $providerCard->textless,
                    'oversized' => $providerCard->oversized,
                    'game_changer' => $providerCard->gameChanger,
                    'edhrec_rank' => $providerCard->edhrecRank,
                    'security_stamp' => $providerCard->securityStamp,
                    'frame' => $providerCard->frame,
                    'hand' => $providerCard->hand,
                    'life' => $providerCard->life,
                    'release_date' => $providerCard->releaseDate,
                    'original_text' => $providerCard->originalText,
                    'original_type' => $providerCard->originalType,
                    'source' => $providerCard->provider,
                    'api_data' => $providerCard->raw,
                ], fn ($value): bool => $value !== null),
            );

            $card->colors()->sync($this->colorIds($providerCard->colors));
            $card->colorIdentity()->sync($this->colorIds($providerCard->colorIdentity));
            $card->cardTypes()->sync($this->typeIds($providerCard));

            $printingIds = [];
            foreach (array_unique(array_filter(array_merge($providerCard->printings ?? [], [$providerCard->set]))) as $code) {
                $printingIds[] = Set::firstOrCreate(['code' => $code], ['name' => $code === $providerCard->set ? $providerCard->setName : null])->id;
            }
            // A card response identifie                                                                                  complete printing list.
            $card->printings()->sync($printingIds, detaching: $providerCard->printings !== null);

            if ($providerCard->rulings !== null) {
                $card->rulings()->delete();
                foreach ($providerCard->rulings as $ruling) {
                    $card->rulings()->create(['ruling_date' => $ruling['date'] ?? null, 'text' => $ruling['text'] ?? '']);
                }
            }

            if ($providerCard->foreignNames !== null) {
                $card->foreignNames()->delete();
                foreach ($providerCard->foreignNames as $foreignName) {
                    $card->foreignNames()->create(['language' => $foreignName['language'] ?? '', 'name' => $foreignName['name'] ?? '', 'multiverse_id' => isset($foreignName['multiverseid']) ? (string) $foreignName['multiverseid'] : null]);
                }
            }

            $card->names()->delete();
            foreach ($providerCard->names as $position => $name) {
                $card->names()->create(['name' => $name, 'position' => $position]);
            }

            $card->legalities()->delete();
            foreach ($providerCard->legalities as $format => $legality) {
                $formatModel = Format::firstOrCreate(['name' => $format]);
                $card->legalities()->create(['format_id' => $formatModel->id, 'legality' => $legality]);
            }

            return $card->fresh();
        }, 5);
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, int>
     */
    private function colorIds(array $codes): array
    {
        $names = ['W' => 'White', 'U' => 'Blue', 'B' => 'Black', 'R' => 'Red', 'G' => 'Green'];

        return collect($codes)->map(fn (string $code): int => Color::firstOrCreate(['code' => $code], ['name' => $names[$code] ?? $code])->id)->all();
    }

    /** @return array<int, int> */
    private function typeIds(ProviderCard $card): array
    {
        return collect([
            'type' => $card->types,
            'supertype' => $card->supertypes,
            'subtype' => $card->subtypes,
        ])->flatMap(
            fn (array $types, string $category) => collect($types)->map(
                fn (string $name): int => CardType::firstOrCreate([
                    'name' => $name,
                    'category' => $category,
                ])->id
            )
        )
            ->unique()
            ->values()
            ->all();
    }
}
