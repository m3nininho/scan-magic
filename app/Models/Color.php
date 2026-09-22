<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Color extends Model
{
    protected $fillable = ['name', 'code'];

    /** @return BelongsToMany<Card, $this, Pivot, 'pivot'> */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class);
    }

    /** @return BelongsToMany<Card, $this, Pivot, 'pivot'> */
    public function identityCards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_color_identity');
    }
}
