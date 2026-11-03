<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('partner_type', 40)->index();
            $table->string('contact_person', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address');
            $table->string('province', 120)->nullable();
            $table->string('city_municipality', 120)->nullable();
            $table->string('barangay', 120)->nullable();
            $table->json('service_areas')->nullable();
            $table->json('available_resources')->nullable();
            $table->string('verification_status', 20)->default('pending')->index();
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['institution_id', 'name']);
        });

        Schema::create('partnership_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_partner_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('title', 180);
            $table->string('reference_number', 80)->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('community_needs_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_partner_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->date('assessed_on');
            $table->string('location');
            $table->string('beneficiary_group', 255);
            $table->unsignedInteger('estimated_beneficiaries')->nullable();
            $table->text('assessment_methods');
            $table->longText('findings');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('community_need_priorities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_needs_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('problem_title', 180);
            $table->text('problem_statement');
            $table->string('category', 50)->index();
            $table->string('priority_level', 20)->index();
            $table->json('sdg_codes');
            $table->string('location');
            $table->decimal('estimated_budget', 12, 2)->nullable();
            $table->json('required_skills')->nullable();
            $table->text('recommended_response')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();
        });

        Schema::table('community_projects', function (Blueprint $table): void {
            $table->foreignId('community_partner_id')->nullable()->after('campus_id')->constrained()->nullOnDelete();
            $table->foreignId('community_need_priority_id')->nullable()->after('community_partner_id')->constrained()->nullOnDelete();
            $table->json('available_skills')->nullable()->after('objectives');
            $table->unsignedTinyInteger('need_match_score')->nullable()->after('budget');
            $table->json('need_match_snapshot')->nullable()->after('need_match_score');
        });

        Schema::create('project_impact_indicators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->string('indicator_name', 180);
            $table->string('unit', 60);
            $table->decimal('baseline_value', 14, 2);
            $table->decimal('target_value', 14, 2)->nullable();
            $table->decimal('post_project_value', 14, 2)->nullable();
            $table->date('baseline_measured_on')->nullable();
            $table->date('post_measured_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['community_project_id', 'indicator_name'],
                'project_impact_project_indicator_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_impact_indicators');
        Schema::table('community_projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('community_need_priority_id');
            $table->dropConstrainedForeignId('community_partner_id');
            $table->dropColumn(['available_skills', 'need_match_score', 'need_match_snapshot']);
        });
        Schema::dropIfExists('community_need_priorities');
        Schema::dropIfExists('community_needs_assessments');
        Schema::dropIfExists('partnership_documents');
        Schema::dropIfExists('community_partners');
    }
};
