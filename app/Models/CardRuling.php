<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardRuling extends Model
{
    protected $fillable = ['card_id', 'ruling_date', 'text'];

    protected function casts(): array
    {
        return ['ruling_date' => 'date'];
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
