<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CardType extends Model
{
    protected $fillable = ['name', 'category'];

    /** @return BelongsToMany<Card, $this, Pivot, 'pivot'> */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class);
    }
}
