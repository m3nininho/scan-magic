<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Format extends Model
{
    protected $fillable = ['name'];

    /** @return BelongsToMany<Card, $this, Pivot, 'pivot'> */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_legalities')->withPivot('legality');
    }

    /** @return HasMany<CardLegality, $this> */
    public function legalities(): HasMany
    {
        return $this->hasMany(CardLegality::class);
    }
}
