<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Collection extends Model
{
    protected $fillable = ['name', 'user_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Card, $this, Pivot, 'pivot'> */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'collection_cards')->withPivot('quantity')->withTimestamps();
    }
}
