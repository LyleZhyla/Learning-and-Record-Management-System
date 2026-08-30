<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->string('rotc_category', 10)->nullable()->after('shirt_size');
        });
    }

    public function down(): void
    {
        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->dropColumn('rotc_category');
        });
    }
};
