<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_forms', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('slug', 170)->unique();
            $table->string('category', 20)->default('document');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('component_id')->nullable()->constrained('nstp_components')->nullOnDelete();
            $table->json('accepted_extensions')->nullable();
            $table->unsignedInteger('max_size_kb')->default(5120);
            $table->boolean('requires_submission')->default(true);
            $table->boolean('is_required')->default(true);
            $table->string('template_path')->nullable();
            $table->string('template_original_name')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_form_id')->constrained('document_forms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('nstp_enrollments')->nullOnDelete();
            $table->string('academic_year', 20)->nullable();
            $table->string('semester', 20)->nullable();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('status', 30)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_form_id', 'user_id', 'academic_year', 'semester'], 'document_submission_term_unique');
        });

        if (Schema::hasTable('roles')) {
            foreach (config('role_permissions.defaults', []) as $baseRole => $permissions) {
                DB::table('roles')->where('is_system', true)->where('base_role', $baseRole)
                    ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_submissions');
        Schema::dropIfExists('document_forms');
    }
};
