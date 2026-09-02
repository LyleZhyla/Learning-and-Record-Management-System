<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function inactivityTimeoutMinutes(): int
    {
        return max(1, min(1440, (int) static::query()
            ->where('key', 'inactivity_timeout_minutes')
            ->value('value') ?: 30));
    }

    public static function componentSelectionIsOpen(): bool
    {
        return static::query()
            ->where('key', 'component_selection_open')
            ->value('value') !== '0';
    }
}
