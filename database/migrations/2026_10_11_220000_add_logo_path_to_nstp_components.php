<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nstp_components', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('nstp_components', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
