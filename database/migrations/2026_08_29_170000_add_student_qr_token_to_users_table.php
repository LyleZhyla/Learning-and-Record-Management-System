<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('student_qr_token', 64)->nullable()->unique()->after('role');
        });

        DB::table('users')->where('role', 'student')->orderBy('id')->eachById(function (object $student): void {
            DB::table('users')->where('id', $student->id)->update([
                'student_qr_token' => Str::random(48),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['student_qr_token']);
            $table->dropColumn('student_qr_token');
        });
    }
};
