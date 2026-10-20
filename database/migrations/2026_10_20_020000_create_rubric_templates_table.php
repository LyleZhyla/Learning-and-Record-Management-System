<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facilitator_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('assessment_type', 30)->index();
            $table->string('source_title', 180)->nullable();
            $table->json('criteria');
            $table->decimal('maximum_score', 10, 2);
            $table->char('fingerprint', 64);
            $table->timestamps();

            $table->unique(['facilitator_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_templates');
    }
};
