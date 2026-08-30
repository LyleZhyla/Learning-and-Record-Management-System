<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->string('rotc_proof_path')->nullable()->after('rotc_category');
            $table->string('rotc_proof_original_name')->nullable()->after('rotc_proof_path');
            $table->string('rotc_approval_status', 20)->nullable()->index()->after('rotc_proof_original_name');
            $table->foreignId('rotc_approved_by')->nullable()->after('rotc_approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('rotc_approved_at')->nullable()->after('rotc_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->dropForeign(['rotc_approved_by']);
            $table->dropIndex(['rotc_approval_status']);
            $table->dropColumn([
                'rotc_proof_path',
                'rotc_proof_original_name',
                'rotc_approval_status',
                'rotc_approved_by',
                'rotc_approved_at',
            ]);
        });
    }
};
