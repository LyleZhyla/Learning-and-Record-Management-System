<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_rules')) {
            return;
        }

        $rule = config('notification_rules.service_request');
        DB::table('notification_rules')->updateOrInsert(
            ['event_key' => 'service_request'],
            [
                'name' => $rule['name'],
                'description' => $rule['description'],
                'is_enabled' => true,
                'channels' => json_encode(['bell'], JSON_THROW_ON_ERROR),
                'title_template' => $rule['title_template'],
                'body_template' => $rule['body_template'],
                'schedule_mode' => 'immediate',
                'delay_minutes' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_rules')) {
            DB::table('notification_rules')->where('event_key', 'service_request')->delete();
        }
    }
};
