<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registrations', function (Blueprint $table): void {
            $table->string('cor_original_name')->nullable()->after('cor_path');
            $table->string('formal_photo_original_name')->nullable()->after('formal_photo_path');
            $table->string('cor_review_status', 30)->default('pending')->after('formal_photo_original_name');
            $table->string('formal_photo_review_status', 30)->default('pending')->after('cor_review_status');
            $table->text('review_notes')->nullable()->after('formal_photo_review_status');
            $table->foreignId('reviewed_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('student_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'cor_original_name',
                'formal_photo_original_name',
                'cor_review_status',
                'formal_photo_review_status',
                'review_notes',
                'reviewed_at',
            ]);
        });
    }
};
