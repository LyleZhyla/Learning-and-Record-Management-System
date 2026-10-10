<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('is_system', true)
            ->whereIn('base_role', ['nstp_admin', 'coordinator'])
            ->orderBy('id')
            ->each(function (object $role): void {
                $permissions = json_decode((string) $role->permissions, true) ?: [];

                if (! in_array('landing.configure', $permissions, true)) {
                    $permissions[] = 'landing.configure';
                }

                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_unique($permissions))),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('is_system', true)
            ->whereIn('base_role', ['nstp_admin', 'coordinator'])
            ->orderBy('id')
            ->each(function (object $role): void {
                $permissions = array_values(array_filter(
                    json_decode((string) $role->permissions, true) ?: [],
                    fn (string $permission): bool => $permission !== 'landing.configure',
                ));

                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode($permissions),
                    'updated_at' => now(),
                ]);
            });
    }
};
