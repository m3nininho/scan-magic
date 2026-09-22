<?php

namespace App\Models;

use App\Enums\CardScanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $card_id
 * @property string|null $original_image_path
 * @property string|null $ocr_raw_text
 * @property string|null $detected_name
 * @property float|null $confidence
 * @property array<int, array<string, mixed>>|null $candidates
 * @property CardScanStatus $status
 */
class CardScan extends Model
{
    protected $fillable = ['user_id', 'card_id', 'original_image_path', 'ocr_raw_text', 'detected_name', 'confidence', 'candidates', 'status'];

    protected function casts(): array
    {
        return ['confidence' => 'float', 'candidates' => 'array', 'status' => CardScanStatus::class];
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
