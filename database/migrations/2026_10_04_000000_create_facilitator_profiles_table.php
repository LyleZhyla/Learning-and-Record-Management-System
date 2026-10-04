<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilitator_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_number', 50)->nullable()->unique();
            $table->string('department', 150)->nullable();
            $table->string('designation', 120)->nullable();
            $table->string('employment_status', 30)->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('specialization', 255)->nullable();
            $table->text('professional_summary')->nullable();
            $table->timestamps();

            $table->index(['department', 'employment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilitator_profiles');
    }
};
