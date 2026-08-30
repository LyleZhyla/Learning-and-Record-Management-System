<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['attendance_records', 'audit_logs', 'announcements'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable()->index();
                $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['attendance_records', 'audit_logs', 'announcements'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('archived_by');
                $table->dropColumn('archived_at');
            });
        }
    }
};
