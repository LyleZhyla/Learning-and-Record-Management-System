<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmrSheet extends Model
{
    protected $fillable = ['assessment_id', 'created_by', 'item_count', 'choice_count', 'answer_key'];

    protected function casts(): array
    {
        return ['answer_key' => 'array', 'item_count' => 'integer', 'choice_count' => 'integer'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(OmrScanResult::class);
    }

    public function hasCompleteAnswerKey(): bool
    {
        $allowed = array_slice(['A', 'B', 'C', 'D', 'E'], 0, $this->choice_count);

        return is_array($this->answer_key)
            && count($this->answer_key) === $this->item_count
            && collect($this->answer_key)->every(fn ($answer) => in_array($answer, $allowed, true));
    }

    public function answerImageBottomMarkerY(): int
    {
        $lastAnswerY = 245 + (($this->item_count - 1) * 34);

        return max(390, $lastAnswerY + 70);
    }

    public function answerImageHeight(): int
    {
        return $this->answerImageBottomMarkerY() + 70;
    }
}
