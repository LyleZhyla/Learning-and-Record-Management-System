<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        foreach ([
            'default_passing_percentage' => '75',
            'default_passing_grade' => '3',
            'required_documents_enforced' => '1',
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        if (Schema::hasTable('roles')) {
            foreach (config('role_permissions.defaults', []) as $baseRole => $permissions) {
                DB::table('roles')->where('is_system', true)->where('base_role', $baseRole)
                    ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->whereIn('key', [
                'default_passing_percentage',
                'default_passing_grade',
                'required_documents_enforced',
            ])->delete();
        }
    }
};
