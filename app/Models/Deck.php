<?php

namespace App\Models;

use Database\Factories\DeckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Deck extends Model
{
    /** @use HasFactory<DeckFactory> */
    use HasFactory;

    protected $fillable = ['name', 'format', 'strategy', 'commander_card_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Card, $this> */
    public function commander(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'commander_card_id');
    }

    /** @return BelongsToMany<Card, $this, Pivot, 'pivot'> */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'deck_cards')->withPivot(['quantity', 'role'])->withTimestamps();
    }
}
