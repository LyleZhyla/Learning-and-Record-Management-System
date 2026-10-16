<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LandingPageMedia extends Model
{
    protected $table = 'landing_page_media';

    protected $fillable = ['type', 'component', 'title', 'description', 'event_date', 'location', 'file_path', 'created_by'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fileUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
