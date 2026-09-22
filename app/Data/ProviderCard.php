<?php

namespace App\Data;

final readonly class ProviderCard
{
    /**
     * @param  array<int, string>  $names
     * @param  array<int, string>  $types
     * @param  array<int, string>  $supertypes
     * @param  array<int, string>  $subtypes
     * @param  array<int, string>  $colors
     * @param  array<int, string>  $colorIdentity
     * @param  array<string, mixed>  $imageUris
     * @param  array<int, string>  $keywords
     * @param  array<int, string>  $producedMana
     * @param  array<int, string>  $colorIndicator
     * @param  array<int, string>  $finishes
     * @param  array<int, string>  $games
     * @param  array<int, string>  $frameEffects
     * @param  array<int, string>  $promoTypes
     * @param  array<int, array<string, mixed>>  $cardFaces
     * @param  array<int, array<string, mixed>>  $allParts
     * @param  array<int, array<string, mixed>>|null  $rulings  Null means not loaded; [] means loaded and empty.
     * @param  array<int, array<string, mixed>>|null  $foreignNames
     * @param  array<int, string>|null  $printings
     * @param  array<string, string>  $legalities
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $provider,
        public string $providerId,
        public string $name,
        public ?string $oracleId = null,
        public array $names = [],
        public ?string $layout = null,
        public ?string $manaCost = null,
        public ?float $cmc = null,
        public ?string $type = null,
        public array $types = [],
        public array $supertypes = [],
        public array $subtypes = [],
        public array $colors = [],
        public array $colorIdentity = [],
        public ?string $rarity = null,
        public ?string $set = null,
        public ?string $setName = null,
        public ?string $setId = null,
        public ?string $setType = null,
        public ?string $text = null,
        public ?string $flavor = null,
        public ?string $artist = null,
        public ?string $number = null,
        public ?string $power = null,
        public ?string $toughness = null,
        public ?string $loyalty = null,
        public ?string $language = null,
        public ?string $imageUrl = null,
        public ?string $imageStatus = null,
        public array $imageUris = [],
        public array $keywords = [],
        public array $producedMana = [],
        public array $colorIndicator = [],
        public array $finishes = [],
        public array $games = [],
        public array $frameEffects = [],
        public array $promoTypes = [],
        public array $cardFaces = [],
        public array $allParts = [],
        public ?string $watermark = null,
        public ?string $border = null,
        public ?bool $timeshifted = null,
        public ?bool $reserved = null,
        public ?bool $starter = null,
        public ?int $hand = null,
        public ?int $life = null,
        public ?string $releaseDate = null,
        public ?string $originalText = null,
        public ?string $originalType = null,
        public ?string $scryfallUri = null,
        public ?string $rulingsUri = null,
        public ?string $printsSearchUri = null,
        public ?bool $booster = null,
        public ?bool $digital = null,
        public ?bool $promo = null,
        public ?bool $reprint = null,
        public ?bool $fullArt = null,
        public ?bool $textless = null,
        public ?bool $oversized = null,
        public ?bool $gameChanger = null,
        public ?int $edhrecRank = null,
        public ?string $securityStamp = null,
        public ?string $frame = null,
        public ?array $rulings = null,
        public ?array $foreignNames = null,
        public ?array $printings = null,
        public array $legalities = [],
        public array $raw = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, string $provider = 'scryfall'): self
    {
        return new self(
            provider: $provider,
            providerId: (string) ($data['id'] ?? $data['multiverseid'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            names: array_values($data['names'] ?? []),
            layout: $data['layout'] ?? null,
            manaCost: $data['manaCost'] ?? null,
            cmc: isset($data['cmc']) ? (float) $data['cmc'] : null,
            type: $data['type'] ?? null,
            types: array_values($data['types'] ?? []),
            supertypes: array_values($data['supertypes'] ?? []),
            subtypes: array_values($data['subtypes'] ?? []),
            colors: array_values($data['colors'] ?? []),
            colorIdentity: array_values($data['colorIdentity'] ?? []),
            rarity: $data['rarity'] ?? null,
            set: $data['set'] ?? null,
            setName: $data['setName'] ?? null,
            text: $data['text'] ?? null,
            flavor: $data['flavor'] ?? null,
            artist: $data['artist'] ?? null,
            number: isset($data['number']) ? (string) $data['number'] : null,
            power: isset($data['power']) ? (string) $data['power'] : null,
            toughness: isset($data['toughness']) ? (string) $data['toughness'] : null,
            loyalty: isset($data['loyalty']) ? (string) $data['loyalty'] : null,
            language: $data['language'] ?? null,
            imageUrl: $data['imageUrl'] ?? null,
            watermark: $data['watermark'] ?? null,
            border: $data['border'] ?? null,
            timeshifted: $data['timeshifted'] ?? null,
            reserved: $data['reserved'] ?? null,
            starter: $data['starter'] ?? null,
            hand: isset($data['hand']) ? (int) $data['hand'] : null,
            life: isset($data['life']) ? (int) $data['life'] : null,
            releaseDate: $data['releaseDate'] ?? null,
            originalText: $data['originalText'] ?? null,
            originalType: $data['originalType'] ?? null,
            rulings: isset($data['rulings']) ? array_values($data['rulings']) : null,
            foreignNames: isset($data['foreignNames']) ? array_values($data['foreignNames']) : null,
            printings: isset($data['printings']) ? array_values($data['printings']) : null,
            legalities: $data['legalities'] ?? [],
            raw: $data,
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromScryfall(array $data): self
    {
        $faces = is_array($data['card_faces'] ?? null) ? $data['card_faces'] : [];
        $typeLine = (string) ($data['type_line'] ?? '');
        $faceTypeLines = array_column($faces, 'type_line');
        $typeLines = $faceTypeLines !== [] ? $faceTypeLines : [$typeLine];
        $typeWords = [];
        $subtypeWords = [];
        foreach ($typeLines as $line) {
            foreach (explode('//', $line) as $faceLine) {
                [$typePart, $subtypePart] = array_pad(explode('—', $faceLine, 2), 2, '');
                $typeWords = array_merge($typeWords, preg_split('/\s+/', trim($typePart), -1, PREG_SPLIT_NO_EMPTY) ?: []);
                $subtypeWords = array_merge($subtypeWords, preg_split('/\s+/', trim($subtypePart), -1, PREG_SPLIT_NO_EMPTY) ?: []);
            }
        }
        $supertypes = ['Basic', 'Legendary', 'Ongoing', 'Snow', 'World'];
        $types = ['Artifact', 'Battle', 'Creature', 'Enchantment', 'Instant', 'Land', 'Planeswalker', 'Sorcery', 'Kindred'];

        return new self(
            provider: 'scryfall',
            providerId: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            oracleId: $data['oracle_id'] ?? null,
            names: array_values(array_map(fn (mixed $face): string => (string) ($face['name'] ?? ''), $faces)),
            layout: $data['layout'] ?? null,
            manaCost: $data['mana_cost'] ?? null,
            cmc: isset($data['cmc']) ? (float) $data['cmc'] : null,
            type: $typeLine,
            types: array_values(array_unique(array_intersect($typeWords, $types))),
            supertypes: array_values(array_unique(array_intersect($typeWords, $supertypes))),
            subtypes: array_values(array_unique($subtypeWords)),
            colors: array_values($data['colors'] ?? []),
            colorIdentity: array_values($data['color_identity'] ?? []),
            rarity: $data['rarity'] ?? null,
            set: $data['set'] ?? null,
            setName: $data['set_name'] ?? null,
            setId: $data['set_id'] ?? null,
            setType: $data['set_type'] ?? null,
            text: $data['oracle_text'] ?? null,
            flavor: $data['flavor_text'] ?? null,
            artist: $data['artist'] ?? null,
            number: isset($data['collector_number']) ? (string) $data['collector_number'] : null,
            power: isset($data['power']) ? (string) $data['power'] : null,
            toughness: isset($data['toughness']) ? (string) $data['toughness'] : null,
            loyalty: isset($data['loyalty']) ? (string) $data['loyalty'] : null,
            language: $data['lang'] ?? null,
            imageUrl: $data['image_uris']['normal'] ?? null,
            imageStatus: $data['image_status'] ?? null,
            imageUris: is_array($data['image_uris'] ?? null) ? $data['image_uris'] : [],
            keywords: is_array($data['keywords'] ?? null) ? $data['keywords'] : [],
            producedMana: is_array($data['produced_mana'] ?? null) ? $data['produced_mana'] : [],
            colorIndicator: is_array($data['color_indicator'] ?? null) ? $data['color_indicator'] : [],
            finishes: is_array($data['finishes'] ?? null) ? $data['finishes'] : [],
            games: is_array($data['games'] ?? null) ? $data['games'] : [],
            frameEffects: is_array($data['frame_effects'] ?? null) ? $data['frame_effects'] : [],
            promoTypes: is_array($data['promo_types'] ?? null) ? $data['promo_types'] : [],
            cardFaces: is_array($data['card_faces'] ?? null) ? $data['card_faces'] : [],
            allParts: is_array($data['all_parts'] ?? null) ? $data['all_parts'] : [],
            watermark: $data['watermark'] ?? null,
            border: $data['border_color'] ?? null,
            reserved: $data['reserved'] ?? null,
            releaseDate: $data['released_at'] ?? null,
            scryfallUri: $data['scryfall_uri'] ?? null,
            rulingsUri: $data['rulings_uri'] ?? null,
            printsSearchUri: $data['prints_search_uri'] ?? null,
            booster: $data['booster'] ?? null,
            digital: $data['digital'] ?? null,
            promo: $data['promo'] ?? null,
            reprint: $data['reprint'] ?? null,
            fullArt: $data['full_art'] ?? null,
            textless: $data['textless'] ?? null,
            oversized: $data['oversized'] ?? null,
            gameChanger: $data['game_changer'] ?? null,
            edhrecRank: isset($data['edhrec_rank']) ? (int) $data['edhrec_rank'] : null,
            securityStamp: $data['security_stamp'] ?? null,
            frame: $data['frame'] ?? null,
            legalities: is_array($data['legalities'] ?? null) ? $data['legalities'] : [],
            raw: $data,
        );
    }
}
