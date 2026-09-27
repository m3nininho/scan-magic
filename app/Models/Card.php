<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Card extends Model
{
    protected $fillable = ['provider', 'provider_id', 'name', 'oracle_id', 'layout', 'mana_cost', 'cmc', 'type', 'rarity', 'set_code', 'set_name', 'set_id', 'set_type', 'text', 'flavor', 'artist', 'number', 'power', 'toughness', 'loyalty', 'language', 'image_url', 'image_status', 'image_uris', 'keywords', 'produced_mana', 'color_indicator', 'finishes', 'games', 'frame_effects', 'promo_types', 'card_faces', 'all_parts', 'scryfall_uri', 'rulings_uri', 'prints_search_uri', 'watermark', 'border', 'timeshifted', 'reserved', 'starter', 'booster', 'digital', 'promo', 'reprint', 'full_art', 'textless', 'oversized', 'game_changer', 'edhrec_rank', 'security_stamp', 'frame', 'hand', 'life', 'release_date', 'original_text', 'original_type', 'source', 'api_data'];

    protected function casts(): array
    {
        return ['api_data' => 'array', 'image_uris' => 'array', 'keywords' => 'array', 'produced_mana' => 'array', 'color_indicator' => 'array', 'finishes' => 'array', 'games' => 'array', 'frame_effects' => 'array', 'promo_types' => 'array', 'card_faces' => 'array', 'all_parts' => 'array', 'cmc' => 'float', 'timeshifted' => 'boolean', 'reserved' => 'boolean', 'starter' => 'boolean', 'booster' => 'boolean', 'digital' => 'boolean', 'promo' => 'boolean', 'reprint' => 'boolean', 'full_art' => 'boolean', 'textless' => 'boolean', 'oversized' => 'boolean', 'game_changer' => 'boolean', 'release_date' => 'date'];
    }

    /** @return BelongsToMany<Color, $this, Pivot, 'pivot'> */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(Color::class);
    }

    /** @return BelongsToMany<Color, $this, Pivot, 'pivot'> */
    public function colorIdentity(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'card_color_identity');
    }

    /** @return BelongsToMany<CardType, $this, Pivot, 'pivot'> */
    public function cardTypes(): BelongsToMany
    {
        return $this->belongsToMany(CardType::class);
    }

    /** @return BelongsToMany<Set, $this, Pivot, 'pivot'> */
    public function printings(): BelongsToMany
    {
        return $this->belongsToMany(Set::class, 'card_printing');
    }

    /** @return HasMany<CardLegality, $this> */
    public function legalities(): HasMany
    {
        return $this->hasMany(CardLegality::class);
    }

    /** @return BelongsToMany<Format, $this, Pivot, 'pivot'> */
    public function formats(): BelongsToMany
    {
        return $this->belongsToMany(Format::class, 'card_legalities')->withPivot('legality');
    }

    /** @return HasMany<CardRuling, $this> */
    public function rulings(): HasMany
    {
        return $this->hasMany(CardRuling::class);
    }

    /** @return HasMany<CardForeignName, $this> */
    public function foreignNames(): HasMany
    {
        return $this->hasMany(CardForeignName::class);
    }

    /** @return HasMany<CardName, $this> */
    public function names(): HasMany
    {
        return $this->hasMany(CardName::class);
    }

    /** @return HasMany<CardScan, $this> */
    public function scans(): HasMany
    {
        return $this->hasMany(CardScan::class);
    }

    /** @return BelongsToMany<Collection, $this, Pivot, 'pivot'> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_cards')->withPivot('quantity')->withTimestamps();
    }

    /** @return BelongsToMany<Deck, $this, Pivot, 'pivot'> */
    public function decks(): BelongsToMany
    {
        return $this->belongsToMany(Deck::class, 'deck_cards')->withPivot(['quantity', 'role'])->withTimestamps();
    }

    public function canBeCommander(): bool
    {
        $faces = $this->getAttribute('card_faces');
        $front = is_array($faces) ? ($faces[0] ?? []) : [];
        $type = $front['type_line'] ?? $this->type ?? '';
        $text = $front['oracle_text'] ?? $this->text ?? '';
        $hasStats = ($front['power'] ?? $this->power) !== null && ($front['toughness'] ?? $this->toughness) !== null;

        return (str_contains($type, 'Legendary') && (str_contains($type, 'Creature') || str_contains($type, 'Vehicle') || (str_contains($type, 'Spacecraft') && $hasStats)))
            || str_contains(strtolower($text), 'can be your commander');
    }
}
