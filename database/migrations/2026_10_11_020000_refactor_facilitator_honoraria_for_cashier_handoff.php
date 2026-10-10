<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilitator_honoraria', function (Blueprint $table): void {
            $table->string('disbursement_voucher_number', 100)->nullable()->after('requested_at');
            $table->string('obligation_request_number', 100)->nullable()->after('disbursement_voucher_number');
            $table->string('payroll_reference', 100)->nullable()->after('obligation_request_number');
            $table->text('preparation_notes')->nullable()->after('payroll_reference');
            $table->foreignId('prepared_by')->nullable()->after('preparation_notes')->constrained('users')->nullOnDelete();
            $table->dateTime('prepared_at')->nullable()->after('prepared_by');
            $table->dateTime('cashier_forwarded_at')->nullable()->after('prepared_at');
            $table->foreignId('voucher_generated_by')->nullable()->after('cashier_forwarded_at')->constrained('users')->nullOnDelete();
            $table->dateTime('voucher_generated_at')->nullable()->after('voucher_generated_by');
        });

        DB::table('facilitator_honoraria')->where('status', 'pending_approval')->update(['status' => 'for_document_preparation']);
        DB::table('facilitator_honoraria')->where('status', 'approved')->update(['status' => 'documents_prepared']);
        DB::table('facilitator_honoraria')->where('status', 'rejected')->update(['status' => 'returned_for_correction']);
        DB::table('facilitator_honoraria')->where('status', 'disbursed')->update(['status' => 'forwarded_to_cashier']);
    }

    public function down(): void
    {
        DB::table('facilitator_honoraria')->where('status', 'for_document_preparation')->update(['status' => 'pending_approval']);
        DB::table('facilitator_honoraria')->where('status', 'documents_prepared')->update(['status' => 'approved']);
        DB::table('facilitator_honoraria')->where('status', 'returned_for_correction')->update(['status' => 'rejected']);
        DB::table('facilitator_honoraria')->where('status', 'forwarded_to_cashier')->update(['status' => 'disbursed']);

        Schema::table('facilitator_honoraria', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropConstrainedForeignId('voucher_generated_by');
            $table->dropColumn([
                'disbursement_voucher_number',
                'obligation_request_number',
                'payroll_reference',
                'preparation_notes',
                'prepared_at',
                'cashier_forwarded_at',
                'voucher_generated_at',
            ]);
        });
    }
};
