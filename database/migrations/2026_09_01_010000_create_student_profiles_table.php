<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('student_registration_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('extension_name', 30)->nullable();
            $table->string('middle_name')->nullable();
            $table->string('province');
            $table->string('province_code', 12);
            $table->string('city_municipality');
            $table->string('city_municipality_code', 12);
            $table->string('barangay');
            $table->string('barangay_code', 12);
            $table->date('date_of_birth');
            $table->string('birth_province');
            $table->string('birth_province_code', 12);
            $table->string('birth_city_municipality');
            $table->string('birth_city_municipality_code', 12);
            $table->string('religion');
            $table->string('sex', 10);
            $table->string('blood_type', 5);
            $table->string('contact_number', 11);
            $table->string('emergency_contact_name');
            $table->string('emergency_relationship', 30);
            $table->string('emergency_contact_number', 11);
            $table->boolean('emergency_same_address')->default(false);
            $table->text('emergency_address')->nullable();
            $table->string('student_number', 10)->unique();
            $table->string('college');
            $table->string('course');
            $table->string('major')->nullable();
            $table->string('year_section', 80);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
