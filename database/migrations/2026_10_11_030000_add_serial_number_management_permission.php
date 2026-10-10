<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updatePermission(true);
    }

    public function down(): void
    {
        $this->updatePermission(false);
    }

    private function updatePermission(bool $add): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('is_system', true)
            ->whereIn('base_role', ['nstp_admin', 'coordinator'])
            ->orderBy('id')
            ->each(function (object $role) use ($add): void {
                $permissions = json_decode((string) $role->permissions, true) ?: [];

                if ($add) {
                    $permissions[] = 'serial_numbers.manage';
                } else {
                    $permissions = array_filter(
                        $permissions,
                        fn (string $permission): bool => $permission !== 'serial_numbers.manage',
                    );
                }

                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_unique($permissions))),
                    'updated_at' => now(),
                ]);
            });
    }
};
