<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nstp_sections', function (Blueprint $table): void {
            $table->unique(
                ['component_id', 'academic_year', 'semester', 'name'],
                'nstp_sections_term_name_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('nstp_sections', function (Blueprint $table): void {
            $table->dropUnique('nstp_sections_term_name_unique');
        });
    }
};
