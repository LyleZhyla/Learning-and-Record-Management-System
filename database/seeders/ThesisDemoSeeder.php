<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ThesisDemoSeeder extends Seeder
{
    public const PASSWORD = 'ThesisDemo2026!';

    public const STUDENT_TARGET = 1050;

    public const FACILITATORS_PER_COMPONENT = 10;

    private int $institutionId;

    private int $campusId;

    private string $academicYear;

    private string $semester;

    private array $roleIds = [];

    private array $components = [];

    private array $admins = [];

    private array $coordinators = [];

    private array $facilitators = [];

    private array $students = [];

    private array $sections = [];

    public function run(): void
    {
        DB::disableQueryLog();

        $this->institutionId = (int) DB::table('institutions')->where('is_active', true)->value('id');
        $this->campusId = (int) DB::table('campuses')->where('institution_id', $this->institutionId)->where('is_main', true)->value('id');

        if (! $this->institutionId || ! $this->campusId) {
            throw new \RuntimeException('Create an active institution and main campus before running ThesisDemoSeeder.');
        }

        $term = DB::table('academic_terms')->where('institution_id', $this->institutionId)->where('is_current', true)->first();
        $this->academicYear = $term?->academic_year ?? '2026-2027';
        $this->semester = $term?->semester ?? 'first';
        $this->roleIds = DB::table('roles')->where('institution_id', $this->institutionId)->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();

        $this->call(NstpComponentSeeder::class);
        $this->components = DB::table('nstp_components')
            ->where('institution_id', $this->institutionId)
            ->whereIn('code', ['CWTS', 'LTS', 'ROTC'])
            ->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();

        if (count($this->components) !== 3) {
            throw new \RuntimeException('CWTS, LTS, and ROTC components are required.');
        }

        $this->seedStaff();
        $this->seedStudents();
        $this->seedSectionsAndEnrollments();
        $this->seedLearningAndAttendance();
        $this->seedCommunications();
        $this->seedAdministrativeModules();
        $this->seedCommunityAndMonitoringModules();
        $this->seedSupplementaryModules();
    }

    private function seedStaff(): void
    {
        $password = Hash::make(self::PASSWORD);
        $now = now();

        $this->ensureRoleMinimum('nstp_admin', 2, function (int $number) use ($password, $now): array {
            return $this->userRow(
                "NSTP Administrator {$number}",
                sprintf('thesis.admin.%02d@smartnstp.local', $number),
                'nstp_admin',
                $password,
                $now,
            );
        });

        $this->ensureRoleMinimum('coordinator', 3, function (int $number) use ($password, $now): array {
            $code = array_keys($this->components)[($number - 1) % 3];

            return $this->userRow(
                "{$code} Program Coordinator",
                strtolower($code).'.coordinator@smartnstp.local',
                'coordinator',
                $password,
                $now,
                $this->components[$code],
            );
        });

        $existingFacilitators = DB::table('users')
            ->where('institution_id', $this->institutionId)
            ->where('role', 'facilitator')
            ->orderBy('id')->get();

        $needed = 30 - $existingFacilitators->count();
        $rows = [];
        for ($i = 1; $i <= max(0, $needed); $i++) {
            $code = array_keys($this->components)[($existingFacilitators->count() + $i - 1) % 3];
            $rows[] = $this->userRow(
                "{$code} Facilitator ".str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                sprintf('thesis.facilitator.%02d@smartnstp.local', $i),
                'facilitator',
                $password,
                $now,
                $this->components[$code],
            );
        }
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('users')->insertOrIgnore($chunk);
        }

        $facilitatorIds = DB::table('users')->where('institution_id', $this->institutionId)
            ->where('role', 'facilitator')->orderBy('id')->limit(30)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach (array_values($this->components) as $componentIndex => $componentId) {
            $slice = array_slice($facilitatorIds, $componentIndex * 10, 10);
            DB::table('users')->whereIn('id', $slice)->update(['nstp_component_id' => $componentId, 'updated_at' => $now]);
            foreach ($slice as $id) {
                $this->facilitators[$componentId][] = $id;
            }
        }

        $coordinatorIds = DB::table('users')->where('institution_id', $this->institutionId)
            ->where('role', 'coordinator')->orderBy('id')->limit(3)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach (array_values($this->components) as $index => $componentId) {
            if (isset($coordinatorIds[$index])) {
                DB::table('users')->where('id', $coordinatorIds[$index])->update(['nstp_component_id' => $componentId, 'updated_at' => $now]);
                $this->coordinators[$componentId] = $coordinatorIds[$index];
            }
        }
        $this->admins = DB::table('users')->where('institution_id', $this->institutionId)
            ->where('role', 'nstp_admin')->orderBy('id')->limit(2)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $profiles = [];
        $counter = 1;
        foreach ($this->facilitators as $componentId => $ids) {
            $code = array_search($componentId, $this->components, true);
            foreach ($ids as $id) {
                $profiles[] = [
                    'user_id' => $id,
                    'employee_number' => sprintf('FAC-2026-%03d', $counter),
                    'department' => $code === 'ROTC' ? 'National Defense Studies' : 'Extension and Community Services',
                    'designation' => 'NSTP Facilitator',
                    'employment_status' => $counter % 4 === 0 ? 'Part-time' : 'Full-time',
                    'contact_number' => '0917'.str_pad((string) $counter, 7, '0', STR_PAD_LEFT),
                    'specialization' => match ($code) {
                        'CWTS' => 'Community Development and Disaster Preparedness',
                        'LTS' => 'Literacy, Numeracy, and Teaching Strategies',
                        default => 'Leadership, Discipline, and Emergency Response',
                    },
                    'professional_summary' => 'Experienced NSTP facilitator supporting student formation and community engagement.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $counter++;
            }
        }
        DB::table('facilitator_profiles')->upsert($profiles, ['user_id'], [
            'employee_number', 'department', 'designation', 'employment_status', 'contact_number',
            'specialization', 'professional_summary', 'updated_at',
        ]);
    }

    private function seedStudents(): void
    {
        $existingCount = DB::table('users')->where('institution_id', $this->institutionId)->where('role', 'student')->count();
        $needed = max(0, self::STUDENT_TARGET - $existingCount);
        $password = Hash::make(self::PASSWORD);
        $now = now();
        $firstNames = ['Andrea', 'Angelo', 'Bianca', 'Carlo', 'Clarisse', 'Daniel', 'Elaine', 'Francis', 'Gabriela', 'Joshua', 'Kathleen', 'Lorenzo', 'Maria', 'Nathaniel', 'Patricia', 'Rafael', 'Samantha', 'Tristan', 'Vanessa', 'Miguel'];
        $lastNames = ['Aquino', 'Bautista', 'Castillo', 'Dela Cruz', 'Evangelista', 'Flores', 'Garcia', 'Hernandez', 'Ignacio', 'Jimenez', 'Lopez', 'Mendoza', 'Navarro', 'Ocampo', 'Pascual', 'Reyes', 'Santos', 'Torres', 'Valdez', 'Villanueva'];
        $rows = [];
        $serial = 1;

        while (count($rows) < $needed) {
            $email = sprintf('thesis.student.%04d@smartnstp.local', $serial);
            if (! DB::table('users')->where('email', $email)->exists()) {
                $first = $firstNames[($serial - 1) % count($firstNames)];
                $last = $lastNames[(int) floor(($serial - 1) / count($firstNames)) % count($lastNames)];
                $rows[] = $this->userRow("{$first} {$last}", $email, 'student', $password, $now);
            }
            $serial++;
        }
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('users')->insertOrIgnore($chunk);
        }

        $this->students = DB::table('users')->where('institution_id', $this->institutionId)->where('role', 'student')
            ->orderBy('id')->limit(self::STUDENT_TARGET)->get(['id', 'name', 'email'])->map(fn ($student) => [
                'id' => (int) $student->id, 'name' => $student->name, 'email' => $student->email,
            ])->all();

        $existingProfiles = DB::table('student_profiles')->whereIn('user_id', array_column($this->students, 'id'))->pluck('user_id')->flip();
        $profileRows = [];
        foreach ($this->students as $index => $student) {
            if ($existingProfiles->has($student['id'])) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($student['name']));
            $first = array_shift($parts) ?: 'Student';
            $last = array_pop($parts) ?: 'Demo';
            $profileRows[] = [
                'institution_id' => $this->institutionId,
                'user_id' => $student['id'],
                'student_registration_id' => null,
                'last_name' => $last,
                'first_name' => $first,
                'middle_name' => $index % 4 === 0 ? 'Santos' : null,
                'extension_name' => null,
                'province' => 'Tarlac', 'province_code' => '036900000',
                'city_municipality' => 'Camiling', 'city_municipality_code' => '036904000',
                'barangay' => 'Poblacion', 'barangay_code' => '036904001',
                'date_of_birth' => Carbon::create(2005, 1, 1)->addDays($index % 700)->toDateString(),
                'birth_province' => 'Tarlac', 'birth_province_code' => '036900000',
                'birth_city_municipality' => 'Camiling', 'birth_city_municipality_code' => '036904000',
                'religion' => $index % 8 === 0 ? 'Iglesia ni Cristo' : 'Roman Catholic',
                'sex' => $index % 2 === 0 ? 'Female' : 'Male',
                'blood_type' => ['O+', 'A+', 'B+', 'AB+'][$index % 4],
                'contact_number' => '09'.str_pad((string) (($student['id'] * 7919) % 1000000000), 9, '0', STR_PAD_LEFT),
                'emergency_contact_name' => "Parent of {$first}",
                'emergency_relationship' => 'Parent',
                'emergency_contact_number' => '09'.str_pad((string) (($student['id'] * 3571) % 1000000000), 9, '0', STR_PAD_LEFT),
                'emergency_same_address' => true,
                'emergency_address' => 'Poblacion, Camiling, Tarlac',
                'student_number' => '26'.str_pad((string) $student['id'], 8, '0', STR_PAD_LEFT),
                'college' => ['College of Agriculture and Forestry', 'College of Engineering and Technology', 'College of Education', 'College of Business and Management'][$index % 4],
                'course' => ['BS Agriculture', 'BS Information Technology', 'Bachelor of Elementary Education', 'BS Business Administration'][$index % 4],
                'major' => $index % 4 === 0 ? 'Crop Science' : null,
                'year_section' => 'First Year - '.chr(65 + ($index % 5)),
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($profileRows, 250) as $chunk) {
            DB::table('student_profiles')->insertOrIgnore($chunk);
        }
    }

    private function seedSectionsAndEnrollments(): void
    {
        $now = now();
        foreach ($this->components as $code => $componentId) {
            foreach ($this->facilitators[$componentId] as $index => $facilitatorId) {
                $number = $index + 1;
                $sectionCode = sprintf('DEMO-%s-%02d', $code, $number);
                DB::table('nstp_sections')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'academic_year' => $this->academicYear, 'semester' => $this->semester, 'code' => $sectionCode],
                    [
                        'campus_id' => $this->campusId, 'component_id' => $componentId, 'facilitator_id' => $facilitatorId,
                        'name' => "{$code} Thesis Section ".str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                        'capacity' => 50, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                    ],
                );
                $sectionId = (int) DB::table('nstp_sections')->where('institution_id', $this->institutionId)
                    ->where('academic_year', $this->academicYear)->where('semester', $this->semester)->where('code', $sectionCode)->value('id');
                $this->sections[] = ['id' => $sectionId, 'component_id' => $componentId, 'code' => $sectionCode, 'facilitator_id' => $facilitatorId];
                DB::table('section_schedules')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'section_id' => $sectionId],
                    ['day_of_week' => 6, 'starts_at' => $number <= 5 ? '08:00:00' : '13:00:00', 'ends_at' => $number <= 5 ? '12:00:00' : '17:00:00', 'is_automatic' => true, 'updated_by' => $this->admins[0], 'created_at' => $now, 'updated_at' => $now],
                );
            }
            DB::table('schedule_settings')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'component_id' => $componentId, 'academic_year' => $this->academicYear, 'semester' => $this->semester],
                ['day_of_week' => 6, 'day_start' => '08:00:00', 'day_end' => '17:00:00', 'break_start' => '12:00:00', 'break_end' => '13:00:00', 'session_minutes' => 240, 'updated_by' => $this->admins[0], 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $enrollmentRows = [];
        foreach ($this->students as $index => $student) {
            $section = $this->sections[$index % count($this->sections)];
            $enrollmentRows[] = [
                'institution_id' => $this->institutionId, 'student_id' => $student['id'],
                'component_id' => $section['component_id'], 'section_id' => $section['id'],
                'academic_year' => $this->academicYear, 'semester' => $this->semester,
                'status' => 'enrolled', 'shirt_size' => ['S', 'M', 'L', 'XL'][$index % 4],
                'rotc_category' => $section['component_id'] === $this->components['ROTC'] ? ($index % 2 ? 'basic' : 'advanced') : null,
                'rotc_approval_status' => $section['component_id'] === $this->components['ROTC'] ? 'approved' : null,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($enrollmentRows, 250) as $chunk) {
            DB::table('nstp_enrollments')->upsert($chunk, ['student_id', 'academic_year', 'semester'], [
                'institution_id', 'component_id', 'section_id', 'status', 'shirt_size', 'rotc_category', 'rotc_approval_status', 'updated_at',
            ]);
        }
    }

    private function seedLearningAndAttendance(): void
    {
        $now = now();
        foreach ($this->sections as $sectionIndex => $section) {
            $code = array_search($section['component_id'], $this->components, true);
            foreach (['Program Orientation', 'Core Values and Citizenship', 'Community Engagement Handbook'] as $materialIndex => $title) {
                DB::table('learning_materials')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'section_id' => $section['id'], 'title' => "{$code}: {$title}"],
                    ['component_id' => $section['component_id'], 'created_by' => $section['facilitator_id'], 'description' => "Reference material for {$title}, with practical activities and reflection questions.", 'external_url' => 'https://officialgazette.gov.ph/2002/01/23/republic-act-no-9163/', 'published_at' => $now->copy()->subDays(30 - $materialIndex), 'status' => 'published', 'created_at' => $now, 'updated_at' => $now],
                );
            }

            $sectionStudents = DB::table('nstp_enrollments')->where('section_id', $section['id'])
                ->where('academic_year', $this->academicYear)->where('semester', $this->semester)->pluck('student_id')->map(fn ($id) => (int) $id)->all();

            foreach ([21, 14, 7] as $week => $daysAgo) {
                $startsAt = $now->copy()->subDays($daysAgo)->setTime(8, 0);
                $token = hash('sha256', "thesis-{$section['code']}-{$week}");
                DB::table('attendance_sessions')->updateOrInsert(
                    ['token' => $token],
                    ['institution_id' => $this->institutionId, 'section_id' => $section['id'], 'created_by' => $section['facilitator_id'], 'title' => 'NSTP Session '.($week + 1), 'starts_at' => $startsAt, 'late_after' => $startsAt->copy()->addMinutes(15), 'ends_at' => $startsAt->copy()->addHours(4), 'token' => $token, 'qr_payload' => url('/student/attendance/check-in/'.$token), 'qr_svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><rect width="120" height="120" fill="white"/><text x="12" y="65">DEMO QR</text></svg>', 'status' => 'closed', 'created_at' => $startsAt, 'updated_at' => $now],
                );
                $sessionId = (int) DB::table('attendance_sessions')->where('token', $token)->value('id');
                $recordRows = [];
                foreach ($sectionStudents as $studentIndex => $studentId) {
                    $absent = ($studentIndex + $week + $sectionIndex) % 17 === 0;
                    $late = ! $absent && ($studentIndex + $week) % 11 === 0;
                    $recordRows[] = [
                        'institution_id' => $this->institutionId, 'attendance_session_id' => $sessionId, 'student_id' => $studentId,
                        'status' => $absent ? 'absent' : ($late ? 'late' : 'present'),
                        'checked_in_at' => $absent ? null : $startsAt->copy()->addMinutes($late ? 22 : 5 + ($studentIndex % 7)),
                        'checked_out_at' => $absent ? null : $startsAt->copy()->addHours(4),
                        'source' => $studentIndex % 8 === 0 ? 'manual' : 'qr', 'recorded_by' => $late ? $section['facilitator_id'] : null,
                        'created_at' => $startsAt, 'updated_at' => $now,
                    ];
                }
                DB::table('attendance_records')->upsert($recordRows, ['attendance_session_id', 'student_id'], ['status', 'checked_in_at', 'checked_out_at', 'source', 'recorded_by', 'updated_at']);
            }

            foreach (['Reflection Journal' => 'activity', 'Midterm Knowledge Check' => 'quiz'] as $title => $type) {
                DB::table('assessments')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'section_id' => $section['id'], 'title' => $title],
                    ['created_by' => $section['facilitator_id'], 'type' => $type, 'instructions' => 'Apply the NSTP concepts discussed during the session and support your answers with concrete examples.', 'max_score' => 100, 'weight' => $type === 'quiz' ? 30 : 20, 'due_at' => $now->copy()->addDays($type === 'quiz' ? 5 : 2), 'published_at' => $now->copy()->subDays(10), 'status' => 'published', 'created_at' => $now, 'updated_at' => $now],
                );
                $assessmentId = (int) DB::table('assessments')->where('section_id', $section['id'])->where('title', $title)->value('id');
                $submissionRows = [];
                foreach ($sectionStudents as $studentIndex => $studentId) {
                    if (($studentIndex + $sectionIndex) % 13 === 0) {
                        continue;
                    }
                    $score = 72 + (($studentId + $sectionIndex) % 27);
                    $submissionRows[] = [
                        'institution_id' => $this->institutionId, 'assessment_id' => $assessmentId, 'student_id' => $studentId,
                        'answer_text' => 'This submission connects personal responsibility, teamwork, and service with the needs of the partner community.',
                        'submitted_at' => $now->copy()->subDays(3), 'score' => $score,
                        'feedback' => $score >= 85 ? 'Excellent application of NSTP concepts.' : 'Good work. Add more specific community examples.',
                        'graded_by' => $section['facilitator_id'], 'graded_at' => $now->copy()->subDays(2), 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                DB::table('assessment_submissions')->upsert($submissionRows, ['assessment_id', 'student_id'], ['answer_text', 'submitted_at', 'score', 'feedback', 'graded_by', 'graded_at', 'updated_at']);
            }
        }
    }

    private function seedCommunications(): void
    {
        $now = now();
        foreach ($this->components as $code => $componentId) {
            foreach (['Welcome to the NSTP Semester', 'Saturday Activity Reminder', 'Community Engagement Week'] as $index => $title) {
                DB::table('announcements')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'component_id' => $componentId, 'title' => "{$code}: {$title}"],
                    ['author_id' => $this->coordinators[$componentId], 'body' => "Please review the {$code} schedule, required materials, and assigned community activities. Contact your facilitator for questions.", 'audience' => 'students', 'status' => 'published', 'published_at' => $now->copy()->subDays(12 - $index * 4), 'expires_at' => $now->copy()->addDays(30), 'is_public' => $index === 0, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        foreach (array_slice($this->students, 0, 60) as $index => $student) {
            $section = $this->sections[$index % count($this->sections)];
            DB::table('chat_messages')->insertOrIgnore([
                'institution_id' => $this->institutionId, 'section_id' => $section['id'], 'sender_id' => $student['id'],
                'recipient_id' => $section['facilitator_id'], 'body' => $index % 2 ? 'Good day, may I confirm the materials needed for Saturday?' : 'I have submitted my reflection activity. Thank you!',
                'read_at' => $index % 5 === 0 ? null : $now->copy()->subHours(2), 'created_at' => $now->copy()->subHours(5 + $index), 'updated_at' => $now,
            ]);
            DB::table('student_notifications')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'user_id' => $student['id'], 'type' => 'thesis_demo', 'source_id' => 100000 + $index],
                ['title' => 'Upcoming NSTP Activity', 'body' => 'Your next NSTP session is scheduled this Saturday. Please arrive 15 minutes early.', 'read_at' => $index % 3 === 0 ? null : $now->copy()->subDay(), 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    private function seedAdministrativeModules(): void
    {
        $now = now();
        foreach ($this->components as $code => $componentId) {
            DB::table('facilitator_requirements')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'slug' => 'thesis-'.$code.'-faculty-load'],
                ['title' => "{$code} Faculty Load and Teaching Assignment", 'description' => 'Current semester faculty load and official teaching assignment.', 'instructions' => 'Upload one signed PDF copy.', 'component_id' => $componentId, 'accepted_extensions' => json_encode(['pdf']), 'max_size_kb' => 5120, 'is_required' => true, 'is_active' => true, 'sort_order' => 1, 'created_by' => $this->admins[0], 'updated_by' => $this->admins[0], 'created_at' => $now, 'updated_at' => $now],
            );
            $requirementId = (int) DB::table('facilitator_requirements')->where('institution_id', $this->institutionId)->where('slug', 'thesis-'.$code.'-faculty-load')->value('id');

            foreach ($this->facilitators[$componentId] as $index => $facilitatorId) {
                DB::table('facilitator_requirement_submissions')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'facilitator_requirement_id' => $requirementId, 'facilitator_id' => $facilitatorId],
                    ['file_path' => "demo/facilitator-requirements/{$facilitatorId}-load.pdf", 'original_name' => "{$code}-faculty-load.pdf", 'mime_type' => 'application/pdf', 'size_bytes' => 245000 + $index * 1000, 'facilitator_notes' => 'Submitted for current semester compliance.', 'status' => $index % 5 === 0 ? 'pending' : 'approved', 'review_notes' => $index % 5 === 0 ? null : 'Verified and complete.', 'reviewed_by' => $index % 5 === 0 ? null : $this->admins[0], 'reviewed_at' => $index % 5 === 0 ? null : $now->copy()->subDays(2), 'submitted_at' => $now->copy()->subDays(7), 'created_at' => $now, 'updated_at' => $now],
                );
                DB::table('facilitator_honoraria')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'reference_number' => sprintf('HON-%s-2026-%02d', $code, $index + 1)],
                    ['facilitator_id' => $facilitatorId, 'component_id' => $componentId, 'academic_year' => $this->academicYear, 'semester' => $this->semester, 'period_start' => $now->copy()->startOfMonth()->toDateString(), 'period_end' => $now->copy()->endOfMonth()->toDateString(), 'gross_amount' => 6500, 'deductions' => 650, 'net_amount' => 5850, 'status' => ['for_document_preparation', 'documents_prepared', 'forwarded_to_cashier'][$index % 3], 'request_notes' => 'Monthly NSTP facilitation honorarium.', 'requested_by' => $this->coordinators[$componentId], 'requested_at' => $now->copy()->subDays(8), 'approved_by' => $this->admins[0], 'approved_at' => $now->copy()->subDays(5), 'created_at' => $now, 'updated_at' => $now],
                );
                DB::table('facilitator_accomplishment_reports')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'facilitator_id' => $facilitatorId, 'component_id' => $componentId, 'academic_year' => $this->academicYear, 'semester' => $this->semester, 'report_month' => $now->copy()->startOfMonth()->toDateString()],
                    ['accomplishment_summary' => 'Conducted orientation, learning sessions, attendance monitoring, assessment checking, and community project preparation.', 'file_path' => "demo/accomplishments/{$facilitatorId}.pdf", 'original_name' => "{$code}-monthly-accomplishment.pdf", 'mime_type' => 'application/pdf', 'file_size' => 325000, 'status' => $index % 4 === 0 ? 'submitted' : 'approved', 'submitted_at' => $now->copy()->subDays(3), 'reviewed_by' => $index % 4 === 0 ? null : $this->coordinators[$componentId], 'reviewed_at' => $index % 4 === 0 ? null : $now->copy()->subDay(), 'review_notes' => $index % 4 === 0 ? null : 'Accomplishments verified.', 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        foreach (range(1, 12) as $index) {
            DB::table('nstp_service_requests')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'reference_code' => sprintf('SR-THESIS-%03d', $index)],
                ['request_type' => $index % 2 ? 'student_record' : 'community_assistance', 'assistance_type' => $index % 2 ? null : 'volunteer_support', 'requester_name' => "Sample Requester {$index}", 'email' => "requester{$index}@example.test", 'contact_number' => '0917000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT), 'student_number' => $index % 2 ? sprintf('2026%06d', $index) : null, 'program' => $index % 2 ? 'BS Information Technology' : null, 'purpose' => $index % 2 ? 'Request for NSTP completion verification.' : 'Volunteer support for a barangay clean-up drive.', 'event_name' => $index % 2 ? null : 'Clean and Green Community Day', 'event_date' => $index % 2 ? null : $now->copy()->addDays(20)->toDateString(), 'event_location' => $index % 2 ? null : 'Camiling, Tarlac', 'expected_participants' => $index % 2 ? null : 80, 'status' => ['submitted', 'in_review', 'completed'][$index % 3], 'status_note' => 'Demo request prepared for thesis presentation.', 'processed_by' => $index % 3 === 0 ? $this->admins[0] : null, 'processed_at' => $index % 3 === 0 ? $now->copy()->subDay() : null, 'privacy_accepted_at' => $now->copy()->subDays($index), 'created_at' => $now->copy()->subDays($index), 'updated_at' => $now],
            );
        }

        foreach ($this->components as $code => $componentId) {
            DB::table('ched_applications')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'reference_number' => "CHED-THESIS-{$code}-2026"],
                ['academic_year' => $this->academicYear, 'semester' => $this->semester, 'student_count' => DB::table('nstp_enrollments')->where('component_id', $componentId)->where('academic_year', $this->academicYear)->where('semester', $this->semester)->count(), 'status' => $code === 'CWTS' ? 'acknowledged' : 'submitted', 'submission_email' => 'regionaloffice@ched.gov.ph', 'ched_reference' => "CHED-RO3-{$code}-2026", 'notes' => 'Consolidated NSTP student application for the current term.', 'prepared_at' => $now->copy()->subDays(12), 'submitted_at' => $now->copy()->subDays(10), 'acknowledged_at' => $code === 'CWTS' ? $now->copy()->subDays(7) : null, 'created_by' => $this->admins[0], 'updated_by' => $this->admins[0], 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    private function seedCommunityAndMonitoringModules(): void
    {
        $now = now();
        $partnerNames = ['Barangay Poblacion Council', 'Camiling Central Elementary School', 'Tarlac Provincial Disaster Risk Reduction Office'];
        foreach ($partnerNames as $index => $name) {
            DB::table('community_partners')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'name' => $name],
                ['partner_type' => ['barangay', 'school', 'government_agency'][$index], 'contact_person' => ['Hon. Teresa Ramos', 'Dr. Manuel Garcia', 'Mr. Paolo Mendoza'][$index], 'email' => 'partner'.($index + 1).'@example.test', 'phone' => '0917555000'.($index + 1), 'address' => 'Camiling, Tarlac', 'province' => 'Tarlac', 'city_municipality' => 'Camiling', 'barangay' => 'Poblacion', 'service_areas' => json_encode(['education', 'environment', 'disaster preparedness']), 'available_resources' => json_encode(['venue', 'volunteers', 'local coordination']), 'verification_status' => 'verified', 'verification_notes' => 'Partner credentials and contact details verified.', 'verified_by' => $this->admins[0], 'verified_at' => $now->copy()->subMonth(), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
        $partners = DB::table('community_partners')->where('institution_id', $this->institutionId)->whereIn('name', $partnerNames)->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($this->components as $code => $componentId) {
            foreach (range(1, 3) as $projectNumber) {
                $section = collect($this->sections)->first(fn ($item) => $item['component_id'] === $componentId && str_ends_with($item['code'], str_pad((string) $projectNumber, 2, '0', STR_PAD_LEFT)));
                $reference = sprintf('CP-%s-2026-%02d', $code, $projectNumber);
                DB::table('community_projects')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'reference_number' => $reference],
                    ['campus_id' => $this->campusId, 'community_partner_id' => $partners[($projectNumber - 1) % count($partners)], 'title' => match ($code) {
                        'CWTS' => "Barangay Health and Environment Drive {$projectNumber}", 'LTS' => "Basa at Bilang Learning Camp {$projectNumber}", default => "Community Disaster Preparedness Drill {$projectNumber}"
                    }, 'component_id' => $componentId, 'section_id' => $section['id'], 'proposed_by' => $section['facilitator_id'], 'description' => 'A student-led service initiative designed from validated community needs and implemented with local partners.', 'objectives' => 'Strengthen community capacity, provide measurable service outcomes, and develop responsible student leaders.', 'available_skills' => json_encode(['facilitation', 'documentation', 'first aid', 'teaching']), 'beneficiaries' => 'Children, families, and community volunteers', 'beneficiary_count' => 80 + $projectNumber * 25, 'location' => 'Camiling, Tarlac', 'budget' => 15000 + $projectNumber * 5000, 'need_match_score' => 88 + $projectNumber, 'start_date' => $now->copy()->addDays($projectNumber * 7)->toDateString(), 'end_date' => $now->copy()->addDays($projectNumber * 7 + 2)->toDateString(), 'approval_status' => 'approved', 'approval_notes' => 'Approved based on relevance, safety plan, and available resources.', 'approved_by' => $this->coordinators[$componentId], 'approved_at' => $now->copy()->subDays(5), 'implementation_status' => ['planned', 'ongoing', 'completed'][$projectNumber - 1], 'implementation_notes' => 'Coordination with the partner community is ongoing.', 'feedback_token' => hash('sha256', $reference), 'feedback_is_open' => true, 'created_at' => $now, 'updated_at' => $now],
                );
                $projectId = (int) DB::table('community_projects')->where('institution_id', $this->institutionId)->where('reference_number', $reference)->value('id');
                foreach (['Coordinate with partner', 'Prepare materials and logistics', 'Document outcomes and feedback'] as $taskIndex => $taskTitle) {
                    DB::table('project_tasks')->updateOrInsert(
                        ['institution_id' => $this->institutionId, 'community_project_id' => $projectId, 'title' => $taskTitle],
                        ['description' => 'Complete the assigned project deliverable and submit supporting notes.', 'assigned_to' => $section['facilitator_id'], 'assigned_by' => $this->coordinators[$componentId], 'priority' => $taskIndex === 1 ? 'high' : 'normal', 'status' => ['completed', 'in_progress', 'pending'][$taskIndex], 'due_at' => $now->copy()->addDays(5 + $taskIndex * 3), 'progress_percentage' => [100, 60, 15][$taskIndex], 'submission_notes' => $taskIndex === 0 ? 'Coordination meeting completed.' : null, 'reviewed_by' => $taskIndex === 0 ? $this->coordinators[$componentId] : null, 'reviewed_at' => $taskIndex === 0 ? $now->copy()->subDay() : null, 'completed_at' => $taskIndex === 0 ? $now->copy()->subDay() : null, 'created_at' => $now, 'updated_at' => $now],
                    );
                }
            }
        }

        foreach ($this->components as $componentId) {
            DB::table('risk_thresholds')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'component_id' => $componentId],
                ['minimum_attendance_percentage' => 75, 'minimum_grade_percentage' => 75, 'maximum_missing_requirements' => 1, 'follow_up_days' => 7, 'escalation_days' => 14, 'minimum_improvement_points' => 5, 'updated_by' => $this->admins[0], 'created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach (array_slice($this->students, 0, 30) as $index => $student) {
            $enrollment = DB::table('nstp_enrollments')->where('student_id', $student['id'])->where('academic_year', $this->academicYear)->where('semester', $this->semester)->first();
            $reasons = json_encode(['Low attendance', 'Missing or late activity submission']);
            $demoSnapshotIds = DB::table('student_risk_snapshots')->where('institution_id', $this->institutionId)
                ->where('student_id', $student['id'])->where('reasons', $reasons)->orderBy('id')->pluck('id');
            $snapshotValues = ['section_id' => $enrollment->section_id, 'component_id' => $enrollment->component_id, 'risk_score' => 45 + ($index % 50), 'status' => $index % 3 === 0 ? 'high_risk' : 'at_risk', 'attendance_percentage' => 58 + ($index % 18), 'grade_percentage' => 65 + ($index % 14), 'missing_requirements' => $index % 3, 'reasons' => $reasons, 'calculated_at' => substr($this->academicYear, 0, 4).'-08-01 08:00:00', 'updated_at' => $now];
            if ($demoSnapshotIds->isEmpty()) {
                DB::table('student_risk_snapshots')->insert($snapshotValues + ['institution_id' => $this->institutionId, 'student_id' => $student['id'], 'created_at' => $now]);
            } else {
                DB::table('student_risk_snapshots')->where('id', $demoSnapshotIds->first())->update($snapshotValues);
                DB::table('student_risk_snapshots')->whereIn('id', $demoSnapshotIds->slice(1))->delete();
            }
        }

        foreach (range(1, 5) as $index) {
            DB::table('data_privacy_requests')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'reference_number' => sprintf('DPR-THESIS-%03d', $index)],
                ['requester_user_id' => $this->students[$index - 1]['id'], 'requester_name' => $this->students[$index - 1]['name'], 'requester_email' => $this->students[$index - 1]['email'], 'request_type' => ['access', 'correction', 'access', 'deletion', 'correction'][$index - 1], 'details' => 'Sample privacy request for thesis module demonstration.', 'status' => ['submitted', 'in_review', 'completed', 'submitted', 'completed'][$index - 1], 'resolution_notes' => $index % 2 ? null : 'Identity verified and request completed.', 'handled_by' => $index % 2 ? null : $this->admins[0], 'due_at' => $now->copy()->addDays(15), 'completed_at' => $index % 2 ? null : $now->copy()->subDay(), 'created_at' => $now->copy()->subDays($index), 'updated_at' => $now],
            );
        }
    }

    private function seedSupplementaryModules(): void
    {
        $now = now();

        foreach (range(1, 15) as $index) {
            $student = $this->students[$index - 1];
            $status = ['pending', 'under_review', 'approved'][$index % 3];
            DB::table('student_registrations')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'reference_code' => sprintf('REG-THESIS-%04d', $index)],
                [
                    'status' => $status, 'academic_year' => $this->academicYear, 'semester' => $this->semester, 'nstp_level' => 'nstp_1',
                    'cor_path' => "demo/registrations/{$index}-cor.pdf", 'cor_original_name' => 'Certificate-of-Registration.pdf',
                    'formal_photo_path' => "demo/registrations/{$index}-photo.jpg", 'formal_photo_original_name' => 'Formal-Photo.jpg',
                    'cor_review_status' => $status === 'approved' ? 'approved' : 'pending', 'formal_photo_review_status' => $status === 'approved' ? 'approved' : 'pending',
                    'review_notes' => $status === 'approved' ? 'Documents verified and registration approved.' : null,
                    'reviewed_by' => $status === 'approved' ? $this->admins[0] : null, 'reviewed_at' => $status === 'approved' ? $now->copy()->subDay() : null,
                    'last_name' => Str::afterLast($student['name'], ' '), 'first_name' => Str::before($student['name'], ' '), 'middle_name' => null, 'extension_name' => null,
                    'province' => 'Tarlac', 'province_code' => '036900000', 'city_municipality' => 'Camiling', 'city_municipality_code' => '036904000',
                    'barangay' => 'Poblacion', 'barangay_code' => '036904001', 'date_of_birth' => Carbon::create(2005, 1, 1)->addDays($index * 11)->toDateString(),
                    'birth_province' => 'Tarlac', 'birth_province_code' => '036900000', 'birth_city_municipality' => 'Camiling', 'birth_city_municipality_code' => '036904000',
                    'religion' => 'Roman Catholic', 'sex' => $index % 2 ? 'Female' : 'Male', 'blood_type' => ['O+', 'A+', 'B+'][$index % 3],
                    'contact_number' => '0918000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT), 'email' => "pending.student.{$index}@example.test",
                    'emergency_contact_name' => 'Sample Parent '.$index, 'emergency_relationship' => 'Parent', 'emergency_contact_number' => '0919000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'emergency_same_address' => true, 'emergency_address' => 'Poblacion, Camiling, Tarlac', 'student_number' => sprintf('R%09d', $index),
                    'college' => 'College of Education', 'course' => 'Bachelor of Elementary Education', 'major' => null, 'year_section' => 'First Year - A',
                    'created_at' => $now->copy()->subDays($index), 'updated_at' => $now,
                ],
            );
        }

        foreach ($this->components as $code => $componentId) {
            DB::table('document_forms')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'slug' => 'thesis-'.$code.'-reflection-portfolio'],
                ['title' => "{$code} Reflection Portfolio", 'category' => 'document', 'description' => 'Consolidated reflection and activity evidence for the semester.', 'instructions' => 'Upload one PDF file with a cover page and signed activity logs.', 'component_id' => $componentId, 'accepted_extensions' => json_encode(['pdf']), 'max_size_kb' => 10240, 'requires_submission' => true, 'is_required' => true, 'is_active' => true, 'sort_order' => 5, 'created_by' => $this->admins[0], 'updated_by' => $this->admins[0], 'created_at' => $now, 'updated_at' => $now],
            );
            $formId = (int) DB::table('document_forms')->where('institution_id', $this->institutionId)->where('slug', 'thesis-'.$code.'-reflection-portfolio')->value('id');
            $componentEnrollments = DB::table('nstp_enrollments')->where('component_id', $componentId)->where('academic_year', $this->academicYear)->where('semester', $this->semester)->limit(35)->get();
            foreach ($componentEnrollments as $index => $enrollment) {
                DB::table('document_submissions')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'document_form_id' => $formId, 'user_id' => $enrollment->student_id, 'academic_year' => $this->academicYear, 'semester' => $this->semester],
                    ['enrollment_id' => $enrollment->id, 'file_path' => "demo/student-documents/{$enrollment->student_id}-portfolio.pdf", 'original_filename' => "{$code}-Reflection-Portfolio.pdf", 'status' => $index % 5 === 0 ? 'pending' : 'approved', 'review_notes' => $index % 5 === 0 ? null : 'Complete and properly organized.', 'reviewed_by' => $index % 5 === 0 ? null : $this->coordinators[$componentId], 'reviewed_at' => $index % 5 === 0 ? null : $now->copy()->subDay(), 'created_at' => $now->copy()->subDays(3), 'updated_at' => $now],
                );
            }
        }

        foreach (array_slice($this->sections, 0, 6) as $section) {
            $assessmentId = (int) DB::table('assessments')->where('section_id', $section['id'])->where('type', 'quiz')->value('id');
            DB::table('omr_sheets')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'assessment_id' => $assessmentId],
                ['created_by' => $section['facilitator_id'], 'item_count' => 20, 'choice_count' => 4, 'answer_key' => json_encode(array_fill(0, 20, 'A')), 'created_at' => $now, 'updated_at' => $now],
            );
            $sheetId = (int) DB::table('omr_sheets')->where('assessment_id', $assessmentId)->value('id');
            $studentIds = DB::table('nstp_enrollments')->where('section_id', $section['id'])->limit(20)->pluck('student_id');
            foreach ($studentIds as $index => $studentId) {
                $correct = 13 + ($index % 8);
                DB::table('omr_scan_results')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'omr_sheet_id' => $sheetId, 'student_id' => $studentId],
                    ['scanned_by' => $section['facilitator_id'], 'answers' => json_encode(array_fill(0, 20, 'A')), 'correct_count' => $correct, 'blank_count' => 0, 'score' => $correct * 5, 'confidence' => 96.50, 'created_at' => $now->copy()->subDays(2), 'updated_at' => $now],
                );
            }
        }

        foreach ($this->components as $code => $componentId) {
            $release = DB::table('nstp_serial_number_releases')->where('institution_id', $this->institutionId)->where('component_id', $componentId)
                ->where('academic_year', $this->academicYear)->where('semester', $this->semester)->where('notes', 'Thesis demonstration serial-number batch.')->first();
            $releaseId = $release?->id ?? DB::table('nstp_serial_number_releases')->insertGetId([
                'institution_id' => $this->institutionId, 'component_id' => $componentId, 'academic_year' => $this->academicYear, 'semester' => $this->semester,
                'received_at' => $now->copy()->subDays(20)->toDateString(), 'source_file_path' => "demo/serials/{$code}-serials.xlsx", 'source_file_original_name' => "{$code}-NSTP-Serials.xlsx",
                'source_file_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'notes' => 'Thesis demonstration serial-number batch.', 'uploaded_by' => $this->coordinators[$componentId], 'created_at' => $now, 'updated_at' => $now,
            ]);
            $enrollments = DB::table('nstp_enrollments')->where('component_id', $componentId)->where('academic_year', $this->academicYear)->where('semester', $this->semester)->limit(40)->get();
            foreach ($enrollments as $index => $enrollment) {
                DB::table('nstp_student_serial_numbers')->updateOrInsert(
                    ['institution_id' => $this->institutionId, 'enrollment_id' => $enrollment->id],
                    ['release_id' => $releaseId, 'student_id' => $enrollment->student_id, 'serial_number' => sprintf('NSTP-%s-2026-%05d', $code, $index + 1), 'encoded_by' => $this->coordinators[$componentId], 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        foreach (array_slice($this->students, 0, 30) as $index => $student) {
            $enrollment = DB::table('nstp_enrollments')->where('student_id', $student['id'])->where('academic_year', $this->academicYear)->where('semester', $this->semester)->first();
            if (! DB::table('ai_learning_recommendations')->where('student_id', $student['id'])->where('nstp_enrollment_id', $enrollment->id)->exists()) {
                DB::table('ai_learning_recommendations')->insert([
                    'institution_id' => $this->institutionId, 'student_id' => $student['id'], 'nstp_enrollment_id' => $enrollment->id,
                    'preferences' => json_encode(['learning_style' => $index % 2 ? 'visual' : 'hands-on', 'interest' => 'community service']),
                    'guidance' => json_encode(['summary' => 'Focus on attendance consistency and reflective learning.', 'recommended_actions' => ['Review the orientation guide', 'Complete the pending reflection', 'Join the project planning meeting']]),
                    'created_at' => $now->copy()->subDays(2), 'updated_at' => $now,
                ]);
            }
            $conversationId = DB::table('ai_chat_conversations')->where('user_id', $student['id'])->where('title', 'My NSTP learning plan')->value('id');
            if (! $conversationId) {
                $conversationId = DB::table('ai_chat_conversations')->insertGetId(['institution_id' => $this->institutionId, 'user_id' => $student['id'], 'title' => 'My NSTP learning plan', 'created_at' => $now, 'updated_at' => $now]);
                DB::table('ai_chat_messages')->insert([
                    ['institution_id' => $this->institutionId, 'user_id' => $student['id'], 'conversation_id' => $conversationId, 'role' => 'user', 'content' => 'How can I improve my participation in NSTP?', 'created_at' => $now, 'updated_at' => $now],
                    ['institution_id' => $this->institutionId, 'user_id' => $student['id'], 'conversation_id' => $conversationId, 'role' => 'assistant', 'content' => 'Attend consistently, review each learning material, and volunteer for one concrete role in your section project.', 'created_at' => $now, 'updated_at' => $now],
                ]);
            }
            $section = collect($this->sections)->firstWhere('id', (int) $enrollment->section_id);
            DB::table('evaluation_responses')->updateOrInsert(
                ['institution_id' => $this->institutionId, 'type' => 'facilitator', 'section_id' => $enrollment->section_id, 'evaluator_id' => $student['id'], 'subject_user_id' => $section['facilitator_id']],
                ['answers' => json_encode(['preparation' => 5, 'clarity' => 4, 'engagement' => 5, 'responsiveness' => 5]), 'comments' => 'The facilitator explained the activities clearly and encouraged active participation.', 'submitted_at' => $now->copy()->subDay(), 'created_at' => $now, 'updated_at' => $now],
            );
        }

        if (! DB::table('audit_logs')->where('institution_id', $this->institutionId)->where('description', 'Thesis demo dataset reviewed by NSTP administrator.')->exists()) {
            DB::table('audit_logs')->insert([
                'institution_id' => $this->institutionId, 'user_id' => $this->admins[0], 'actor_name' => 'NSTP Administrator', 'actor_email' => 'nstpadmin.demo@smartnstp.local',
                'actor_role' => 'nstp_admin', 'action' => 'view_report', 'description' => 'Thesis demo dataset reviewed by NSTP administrator.', 'method' => 'GET',
                'route_name' => 'admin.reports.index', 'path' => 'admin/reports', 'ip_address' => '127.0.0.1', 'user_agent' => 'Smart NSTP Thesis Demo',
                'status_code' => 200, 'duration_ms' => 145, 'metadata' => json_encode(['source' => 'ThesisDemoSeeder']), 'created_at' => $now,
            ]);
        }
    }

    private function ensureRoleMinimum(string $role, int $minimum, callable $factory): void
    {
        $count = DB::table('users')->where('institution_id', $this->institutionId)->where('role', $role)->count();
        $rows = [];
        for ($number = $count + 1; $number <= $minimum; $number++) {
            $rows[] = $factory($number);
        }
        if ($rows) {
            DB::table('users')->insertOrIgnore($rows);
        }
    }

    private function userRow(string $name, string $email, string $role, string $password, $now, ?int $componentId = null): array
    {
        return [
            'institution_id' => $this->institutionId, 'campus_id' => $this->campusId, 'is_system_admin' => false,
            'name' => $name, 'last_name_sort_key' => Str::lower(Str::afterLast($name, ' ')), 'email' => $email,
            'email_verified_at' => $now, 'password' => $password, 'role' => $role,
            'role_id' => $this->roleIds[$role] ?? null, 'nstp_component_id' => $componentId,
            'status' => 'active', 'must_change_password' => false, 'must_complete_student_profile' => false,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }
}
