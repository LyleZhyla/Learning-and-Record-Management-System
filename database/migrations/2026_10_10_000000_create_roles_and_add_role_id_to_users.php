<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('base_role', 40)->index();
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $labels = [
            'student' => 'Student',
            'facilitator' => 'Facilitator',
            'coordinator' => 'Coordinator',
            'nstp_admin' => 'NSTP Admin',
            'super_admin' => 'Super Admin',
        ];
        $now = now();

        foreach ($labels as $baseRole => $name) {
            DB::table('roles')->insert([
                'name' => $name,
                'slug' => $baseRole,
                'base_role' => $baseRole,
                'description' => 'Built-in '.$name.' access role.',
                'permissions' => json_encode(config('role_permissions.defaults.'.$baseRole, []), JSON_THROW_ON_ERROR),
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        foreach (DB::table('roles')->pluck('id', 'base_role') as $baseRole => $roleId) {
            DB::table('users')->where('role', $baseRole)->update(['role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('roles');
    }
};
