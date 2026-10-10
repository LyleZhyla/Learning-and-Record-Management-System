<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilitator_honoraria', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number', 40)->nullable()->unique();
            $table->foreignId('facilitator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('component_id')->constrained('nstp_components')->restrictOnDelete();
            $table->string('academic_year', 20);
            $table->string('semester', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('status', 30)->default('pending_approval')->index();
            $table->text('request_notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('requested_at');
            $table->text('approval_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('disbursement_reference')->nullable();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disbursed_at')->nullable();
            $table->foreignId('payslip_generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('payslip_generated_at')->nullable();
            $table->timestamps();
            $table->index(['component_id', 'academic_year', 'semester'], 'honorarium_term_scope_index');
            $table->index(['facilitator_id', 'status'], 'honorarium_facilitator_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilitator_honoraria');
    }
};
