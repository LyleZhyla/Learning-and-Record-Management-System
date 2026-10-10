<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NstpSerialNumberRelease extends Model
{
    protected $fillable = [
        'component_id', 'academic_year', 'semester', 'received_at', 'source_file_path',
        'source_file_original_name', 'source_file_mime_type', 'notes', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'date'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'component_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(NstpStudentSerialNumber::class, 'release_id');
    }
}
