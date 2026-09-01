<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 30)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('cor_path');
            $table->string('formal_photo_path');

            $table->string('last_name');
            $table->string('first_name');
            $table->string('extension_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('province');
            $table->string('province_code', 12)->nullable();
            $table->string('city_municipality');
            $table->string('city_municipality_code', 12)->nullable();
            $table->string('barangay');
            $table->string('barangay_code', 12)->nullable();
            $table->date('date_of_birth');
            $table->string('birth_province');
            $table->string('birth_province_code', 12)->nullable();
            $table->string('birth_city_municipality');
            $table->string('birth_city_municipality_code', 12)->nullable();
            $table->string('religion');
            $table->string('sex', 10);
            $table->string('blood_type', 5);
            $table->string('contact_number', 11);
            $table->string('email')->unique();

            $table->string('emergency_contact_name');
            $table->string('emergency_relationship', 30);
            $table->string('emergency_contact_number', 11);
            $table->boolean('emergency_same_address')->default(false);
            $table->text('emergency_address')->nullable();

            $table->string('student_number', 10)->unique();
            $table->string('college');
            $table->string('course');
            $table->string('major')->nullable();
            $table->string('year_section');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_registrations');
    }
};
