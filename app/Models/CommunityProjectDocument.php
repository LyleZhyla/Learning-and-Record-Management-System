<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityProjectDocument extends Model
{
    public const CATEGORIES = [
        'document' => 'Accomplishment report',
        'photo' => 'Photo documentation',
        'certificate' => 'Certificate',
        'supporting' => 'Supporting file',
    ];

    protected $fillable = [
        'community_project_id', 'community_project_activity_id', 'category', 'title',
        'description', 'file_path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CommunityProjectActivity::class, 'community_project_activity_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? str($this->category)->headline()->toString();
    }

    public function formattedSize(): string
    {
        if ($this->size_bytes < 1024) {
            return $this->size_bytes.' B';
        }

        if ($this->size_bytes < 1024 * 1024) {
            return number_format($this->size_bytes / 1024, 1).' KB';
        }

        return number_format($this->size_bytes / (1024 * 1024), 1).' MB';
    }
}
