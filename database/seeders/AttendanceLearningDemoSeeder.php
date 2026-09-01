<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\AttendanceSession;
use App\Models\LearningMaterial;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AttendanceLearningDemoSeeder extends Seeder
{
    public function run(QrCodeService $qrCode): void
    {
        $this->call(SampleAccountsSeeder::class);

        $component = NstpComponent::where('code', 'CWTS')->firstOrFail();
        $student = User::firstOrCreate(
            ['email' => 'student.demo@smartnstp.local'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make(SampleAccountsSeeder::PASSWORD),
                'role' => 'student',
                'status' => 'active',
                'must_change_password' => true,
                'email_verified_at' => now(),
            ],
        );
        $facilitator = User::where('email', 'facilitator.demo@smartnstp.local')->firstOrFail();
        $admin = User::where('email', 'nstpadmin.demo@smartnstp.local')->firstOrFail();
        $section = NstpSection::updateOrCreate(
            ['code' => 'CWTS-01', 'academic_year' => '2026-2027', 'semester' => 'first'],
            ['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'name' => 'Community Service Section 1', 'capacity' => 40, 'status' => 'active'],
        );
        NstpEnrollment::updateOrCreate(
            ['student_id' => $student->id, 'academic_year' => '2026-2027', 'semester' => 'first'],
            ['component_id' => $component->id, 'section_id' => $section->id, 'status' => 'enrolled'],
        );

        LearningMaterial::updateOrCreate(
            ['section_id' => $section->id, 'title' => 'NSTP Orientation Guide'],
            ['component_id' => $component->id, 'created_by' => $admin->id, 'description' => 'Introduction to NSTP, program expectations, and community engagement guidelines.', 'external_url' => 'https://officialgazette.gov.ph/2002/01/23/republic-act-no-9163/', 'status' => 'published', 'published_at' => now()],
        );
        Assessment::updateOrCreate(
            ['section_id' => $section->id, 'title' => 'Community Needs Reflection'],
            ['created_by' => $facilitator->id, 'type' => 'activity', 'instructions' => 'Write a short reflection identifying one community need and a practical NSTP response.', 'max_score' => 100, 'weight' => 20, 'due_at' => now()->addDays(7), 'status' => 'published', 'published_at' => now()],
        );

        $session = AttendanceSession::firstOrNew(['section_id' => $section->id, 'title' => 'Demo QR Attendance']);
        $token = $session->token ?: Str::random(48);
        $payload = url('/student/attendance/check-in/'.$token);
        $session->fill([
            'created_by' => $facilitator->id, 'starts_at' => now()->subMinutes(10), 'late_after' => now()->addMinutes(15), 'ends_at' => now()->addHours(2),
            'token' => $token, 'qr_payload' => $payload, 'qr_svg' => $qrCode->generateSvg($payload), 'status' => 'open',
        ])->save();
    }
}
