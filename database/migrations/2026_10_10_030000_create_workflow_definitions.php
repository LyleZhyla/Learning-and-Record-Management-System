<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('steps');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        foreach (config('workflows', []) as $key => $workflow) {
            $stepKeys = array_keys($workflow['steps']);
            $steps = collect($workflow['steps'])->map(function (array $step, string $stepKey) use ($stepKeys): array {
                return ['key' => $stepKey, 'enabled' => $step['enabled'], 'mode' => $step['mode'], 'sort_order' => ((array_search($stepKey, $stepKeys, true) + 1) * 10)];
            })->values()->all();
            DB::table('workflow_definitions')->insert([
                'key' => $key,
                'name' => $workflow['name'],
                'description' => $workflow['description'],
                'steps' => json_encode($steps, JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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
        Schema::dropIfExists('workflow_definitions');
    }
};
