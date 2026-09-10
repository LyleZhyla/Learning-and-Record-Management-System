<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_upload_student_documents')->default(false);
        });

        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('cor_path')->nullable();
            $table->string('formal_photo_path')->nullable();
        });

        DB::table('users')
            ->where('role', 'student')
            ->whereIn('id', DB::table('student_profiles')
                ->whereNull('student_registration_id')
                ->select('user_id'))
            ->update(['must_upload_student_documents' => true]);
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->dropColumn(['cor_path', 'formal_photo_path']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_upload_student_documents');
        });
    }
};
