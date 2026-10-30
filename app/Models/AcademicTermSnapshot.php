<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AcademicTermSnapshot extends Model
{
    protected $fillable = ['academic_term_id', 'version', 'payload', 'checksum', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Finalized academic-term snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Finalized academic-term snapshots are immutable.'));
    }
}
