<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_page_media', function (Blueprint $table): void {
            $table->date('event_date')->nullable()->after('description');
            $table->string('location', 180)->nullable()->after('event_date');
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_media', function (Blueprint $table): void {
            $table->dropColumn(['event_date', 'location']);
        });
    }
};
