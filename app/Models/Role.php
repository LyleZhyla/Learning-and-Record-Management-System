<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const CUSTOMIZABLE_BASE_ROLES = ['nstp_admin', 'coordinator', 'facilitator', 'student'];

    protected $fillable = ['name', 'slug', 'base_role', 'description', 'permissions', 'is_active'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
