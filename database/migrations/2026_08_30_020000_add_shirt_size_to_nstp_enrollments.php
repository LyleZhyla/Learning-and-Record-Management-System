<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->string('shirt_size', 10)->nullable()->after('semester');
        });
    }

    public function down(): void
    {
        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->dropColumn('shirt_size');
        });
    }
};
