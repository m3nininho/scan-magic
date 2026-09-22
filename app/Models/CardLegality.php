<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardLegality extends Model
{
    public $timestamps = false;

    protected $fillable = ['card_id', 'format_id', 'legality'];

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return BelongsTo<Format, $this> */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }
}
