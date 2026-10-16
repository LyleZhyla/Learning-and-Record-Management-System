<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_media', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('component', 20)->nullable();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'component']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_media');
    }
};
