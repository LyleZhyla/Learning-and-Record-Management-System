<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TENANT_TABLES = [
        'roles', 'nstp_components', 'nstp_sections', 'nstp_enrollments',
        'attendance_sessions', 'attendance_records', 'learning_materials', 'assessments', 'assessment_submissions',
        'omr_sheets', 'omr_scan_results', 'audit_logs', 'grading_categories', 'grading_settings',
        'announcements', 'announcement_reads', 'chat_messages', 'ai_chat_messages', 'ai_chat_conversations',
        'student_registrations', 'student_profiles', 'student_notifications', 'schedule_settings', 'section_schedules',
        'chat_groups', 'chat_group_members', 'chat_group_messages', 'facilitator_profiles', 'ai_learning_recommendations',
        'document_forms', 'document_submissions', 'review_categories', 'workflow_definitions', 'notification_rules',
        'component_assessment_settings', 'ched_applications', 'ched_application_status_histories',
        'community_projects', 'community_project_activities', 'community_project_documents', 'nstp_service_requests',
        'project_tasks', 'evaluation_responses', 'facilitator_requirements', 'facilitator_requirement_submissions',
        'facilitator_honoraria', 'nstp_serial_number_releases', 'nstp_student_serial_numbers',
        'facilitator_accomplishment_reports', 'landing_page_media', 'rubric_templates', 'workflow_step_completions',
    ];

    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 180);
            $table->string('short_name', 50);
            $table->string('slug', 120)->unique();
            $table->string('code', 30)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $branding = json_decode((string) DB::table('system_settings')->where('key', 'institution_branding')->value('value'), true);
        $name = $branding['organization_name'] ?? config('app.name', 'Smart NSTP Institution');
        $shortName = $branding['organization_short_name'] ?? Str::upper(Str::substr($name, 0, 20));
        $defaultInstitutionId = DB::table('institutions')->insertGetId([
            'name' => $name,
            'short_name' => $shortName,
            'slug' => Str::slug($shortName) ?: 'default-institution',
            'code' => Str::upper(Str::slug($shortName, '')) ?: 'DEFAULT',
            'website' => $branding['organization_website'] ?? null,
            'logo_path' => $branding['organization_logo_path'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('campuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 40);
            $table->text('address')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['institution_id', 'code']);
        });
        $mainCampusId = DB::table('campuses')->insertGetId([
            'institution_id' => $defaultInstitutionId,
            'name' => 'Main Campus',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('institution_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('campus_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
            $table->boolean('is_system_admin')->default(false)->after('campus_id')->index();
        });
        DB::table('users')->update(['institution_id' => $defaultInstitutionId, 'campus_id' => $mainCampusId]);
        DB::table('users')->where('role', 'super_admin')->update([
            'institution_id' => null,
            'campus_id' => null,
            'is_system_admin' => true,
        ]);

        foreach (self::TENANT_TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'institution_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('institution_id')->nullable()->index()->constrained()->restrictOnDelete();
            });
            DB::table($tableName)->update(['institution_id' => $defaultInstitutionId]);
        }

        $this->rebuildSystemSettings($defaultInstitutionId);
        $this->makeTenantUniqueIndexes();

        Schema::table('nstp_sections', function (Blueprint $table): void {
            $table->foreignId('campus_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
        });
        Schema::table('community_projects', function (Blueprint $table): void {
            $table->foreignId('campus_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
        });
        DB::table('nstp_sections')->update(['campus_id' => $mainCampusId]);
        DB::table('community_projects')->update(['campus_id' => $mainCampusId]);

        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('academic_year', 9);
            $table->string('semester', 20);
            $table->string('status', 20)->default('open')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();
            $table->unique(['institution_id', 'academic_year', 'semester']);
        });
        DB::table('academic_terms')->insert([
            'institution_id' => $defaultInstitutionId,
            'academic_year' => DB::table('system_settings')->where('institution_id', $defaultInstitutionId)->where('key', 'student_registration_academic_year')->value('value') ?: now()->year.'-'.(now()->year + 1),
            'semester' => DB::table('system_settings')->where('institution_id', $defaultInstitutionId)->where('key', 'student_registration_semester')->value('value') ?: 'first',
            'status' => 'open',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('institution_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('sequence_key', 60);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['institution_id', 'sequence_key']);
        });
    }

    private function rebuildSystemSettings(int $institutionId): void
    {
        $rows = DB::table('system_settings')->get();
        Schema::create('institution_system_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'key']);
        });
        foreach ($rows as $row) {
            DB::table('institution_system_settings')->insert([
                'institution_id' => $institutionId,
                'key' => $row->key,
                'value' => $row->value,
                'updated_by' => $row->updated_by,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
        Schema::drop('system_settings');
        Schema::rename('institution_system_settings', 'system_settings');
    }

    private function makeTenantUniqueIndexes(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('roles_slug_unique');
            $table->unique(['institution_id', 'slug']);
        });
        Schema::table('nstp_components', function (Blueprint $table): void {
            $table->dropUnique('nstp_components_code_unique');
            $table->unique(['institution_id', 'code']);
        });
        Schema::table('nstp_sections', function (Blueprint $table): void {
            $table->dropUnique('nstp_sections_term_code_unique');
            $table->dropUnique('nstp_sections_term_name_unique');
            $table->unique(['institution_id', 'academic_year', 'semester', 'code'], 'nstp_sections_tenant_term_code_unique');
            $table->unique(['institution_id', 'component_id', 'academic_year', 'semester', 'name'], 'nstp_sections_tenant_term_name_unique');
        });
        Schema::table('document_forms', function (Blueprint $table): void {
            $table->dropUnique('document_forms_slug_unique');
            $table->unique(['institution_id', 'slug']);
        });
        Schema::table('review_categories', function (Blueprint $table): void {
            $table->dropUnique('review_categories_scope_slug_unique');
            $table->unique(['institution_id', 'scope', 'slug']);
        });
        Schema::table('workflow_definitions', function (Blueprint $table): void {
            $table->dropUnique('workflow_definitions_key_unique');
            $table->unique(['institution_id', 'key']);
        });
        Schema::table('notification_rules', function (Blueprint $table): void {
            $table->dropUnique('notification_rules_event_key_unique');
            $table->unique(['institution_id', 'event_key']);
        });
        Schema::table('facilitator_requirements', function (Blueprint $table): void {
            $table->dropUnique('facilitator_requirements_slug_unique');
            $table->unique(['institution_id', 'slug']);
        });
        Schema::table('ched_applications', function (Blueprint $table): void {
            $table->dropUnique('ched_applications_reference_number_unique');
            $table->unique(['institution_id', 'reference_number']);
        });
        Schema::table('community_projects', function (Blueprint $table): void {
            $table->dropUnique('community_projects_reference_number_unique');
            $table->unique(['institution_id', 'reference_number']);
        });
        Schema::table('facilitator_honoraria', function (Blueprint $table): void {
            $table->dropUnique('facilitator_honoraria_reference_number_unique');
            $table->unique(['institution_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_number_sequences');
        Schema::dropIfExists('academic_terms');
        Schema::table('community_projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('campus_id'));
        Schema::table('nstp_sections', fn (Blueprint $table) => $table->dropConstrainedForeignId('campus_id'));

        foreach (array_reverse(self::TENANT_TABLES) as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'institution_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('institution_id'));
            }
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('campus_id');
            $table->dropConstrainedForeignId('institution_id');
            $table->dropColumn('is_system_admin');
        });
        Schema::dropIfExists('campuses');
        Schema::dropIfExists('institutions');
    }
};
