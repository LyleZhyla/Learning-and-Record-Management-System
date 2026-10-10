<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->json('channels');
            $table->string('title_template', 180);
            $table->text('body_template');
            $table->string('schedule_mode', 20)->default('immediate');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('student_notifications', function (Blueprint $table): void {
            $table->timestamp('available_at')->nullable()->after('body')->index();
        });

        $now = now();
        foreach (config('notification_rules', []) as $eventKey => $rule) {
            DB::table('notification_rules')->insert([
                'event_key' => $eventKey,
                'name' => $rule['name'],
                'description' => $rule['description'],
                'is_enabled' => true,
                'channels' => json_encode(['bell', 'sidebar'], JSON_THROW_ON_ERROR),
                'title_template' => $rule['title_template'],
                'body_template' => $rule['body_template'],
                'schedule_mode' => 'immediate',
                'delay_minutes' => 0,
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
        Schema::table('student_notifications', function (Blueprint $table): void {
            $table->dropIndex(['available_at']);
            $table->dropColumn('available_at');
        });
        Schema::dropIfExists('notification_rules');
    }
};
