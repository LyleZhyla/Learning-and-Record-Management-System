<?php

namespace App\Services;

use App\Models\Institution;
use Illuminate\Support\Facades\DB;

class InstitutionProvisioner
{
    public function provision(Institution $institution, ?int $sourceInstitutionId): void
    {
        DB::table('campuses')->insert([
            'institution_id' => $institution->id,
            'name' => 'Main Campus',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($sourceInstitutionId) {
            foreach ([
                'roles' => ['updated_by', 'created_by'],
                'nstp_components' => ['updated_by', 'created_by'],
                'system_settings' => ['updated_by'],
                'review_categories' => ['updated_by', 'created_by'],
                'workflow_definitions' => ['updated_by'],
                'notification_rules' => ['updated_by'],
                'facilitator_requirements' => ['updated_by', 'created_by', 'component_id'],
            ] as $table => $nullableColumns) {
                $this->copyRows($table, $sourceInstitutionId, $institution->id, $nullableColumns);
            }
        }

        if (! DB::table('nstp_components')->where('institution_id', $institution->id)->exists()) {
            foreach ([
                ['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'description' => 'Programs and activities that contribute to community welfare.'],
                ['code' => 'LTS', 'name' => 'Literacy Training Service', 'description' => 'Training students to teach literacy and numeracy skills.'],
                ['code' => 'ROTC', 'name' => "Reserve Officers' Training Corps", 'description' => 'Military training for national defense and emergency response.'],
            ] as $component) {
                DB::table('nstp_components')->insert($component + [
                    'institution_id' => $institution->id,
                    'default_section_capacity' => 40,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $branding = [
            'organization_name' => $institution->name,
            'organization_short_name' => $institution->short_name,
            'program_name' => 'National Service Training Program',
            'program_short_name' => 'NSTP',
            'platform_name' => 'Smart NSTP Management and AI-Integrated Platform',
            'organization_website' => $institution->website,
            'organization_logo_path' => $institution->logo_path,
            'program_logo_path' => null,
        ];
        DB::table('system_settings')->updateOrInsert(
            ['institution_id' => $institution->id, 'key' => 'institution_branding'],
            ['value' => json_encode($branding, JSON_UNESCAPED_SLASHES), 'created_at' => now(), 'updated_at' => now()]
        );

        $academicYear = DB::table('system_settings')->where('institution_id', $institution->id)
            ->where('key', 'student_registration_academic_year')->value('value') ?: now()->year.'-'.(now()->year + 1);
        $semester = DB::table('system_settings')->where('institution_id', $institution->id)
            ->where('key', 'student_registration_semester')->value('value') ?: 'first';
        DB::table('academic_terms')->insert([
            'institution_id' => $institution->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'status' => 'open',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $components = DB::table('nstp_components')->where('institution_id', $institution->id)->get();
        foreach ($components as $component) {
            $definition = config('component_assessment_profiles.profiles.'.$component->code)
                ?? config('component_assessment_profiles.default');
            DB::table('component_assessment_settings')->insert([
                'institution_id' => $institution->id,
                'component_id' => $component->id,
                'allowed_types' => json_encode($definition['allowed_types']),
                'default_type' => $definition['default_type'],
                'default_max_score' => $definition['default_max_score'],
                'rubric_required_types' => json_encode($definition['rubric_required_types']),
                'passing_percentage' => $definition['passing_percentage'],
                'highest_grade' => $definition['highest_grade'],
                'passing_grade' => $definition['passing_grade'],
                'failing_grade' => $definition['failing_grade'],
                'category_templates' => json_encode($definition['categories']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<int, string> $nullableColumns */
    private function copyRows(string $table, int $sourceId, int $targetId, array $nullableColumns): void
    {
        foreach (DB::table($table)->where('institution_id', $sourceId)->get() as $source) {
            $row = (array) $source;
            unset($row['id']);
            $row['institution_id'] = $targetId;
            foreach ($nullableColumns as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = null;
                }
            }
            $row['created_at'] = now();
            $row['updated_at'] = now();
            DB::table($table)->insert($row);
        }
    }
}
