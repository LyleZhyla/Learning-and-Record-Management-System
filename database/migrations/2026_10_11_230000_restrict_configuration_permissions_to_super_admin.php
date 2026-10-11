<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONFIGURATION_PERMISSIONS = [
        'documents.configure',
        'reviews.configure',
        'workflows.configure',
        'notifications.configure',
        'policies.configure',
        'landing.configure',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')->where('base_role', '!=', 'super_admin')->orderBy('id')->each(function (object $role): void {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];
            $permissions = array_values(array_diff($permissions, self::CONFIGURATION_PERMISSIONS));

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $previousDefaults = [
            'nstp_admin' => self::CONFIGURATION_PERMISSIONS,
            'coordinator' => ['landing.configure'],
        ];

        foreach ($previousDefaults as $baseRole => $restoredPermissions) {
            $role = DB::table('roles')->where('base_role', $baseRole)->where('is_system', true)->first();
            if (! $role) {
                continue;
            }

            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];
            $permissions = array_values(array_unique([...$permissions, ...$restoredPermissions]));

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }
};
