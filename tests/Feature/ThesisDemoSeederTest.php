<?php

namespace Tests\Feature;

use Database\Seeders\ThesisDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThesisDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_balanced_and_idempotent_thesis_demo_dataset(): void
    {
        $this->seed(ThesisDemoSeeder::class);
        $this->seed(ThesisDemoSeeder::class);

        $institutionId = DB::table('institutions')->value('id');

        $this->assertSame(ThesisDemoSeeder::STUDENT_TARGET, DB::table('users')->where('institution_id', $institutionId)->where('role', 'student')->count());
        $this->assertSame(30, DB::table('users')->where('institution_id', $institutionId)->where('role', 'facilitator')->count());
        $this->assertSame(3, DB::table('users')->where('institution_id', $institutionId)->where('role', 'coordinator')->count());
        $this->assertSame(2, DB::table('users')->where('institution_id', $institutionId)->where('role', 'nstp_admin')->count());

        $distribution = DB::table('users')->join('nstp_components', 'nstp_components.id', '=', 'users.nstp_component_id')
            ->where('users.role', 'facilitator')->groupBy('nstp_components.code')
            ->selectRaw('nstp_components.code, count(*) as total')->pluck('total', 'nstp_components.code');

        $this->assertSame(['CWTS' => 10, 'LTS' => 10, 'ROTC' => 10], $distribution->sortKeys()->map(fn ($count) => (int) $count)->all());
        $this->assertSame(30, DB::table('nstp_sections')->where('code', 'like', 'DEMO-%')->count());
        $this->assertSame(ThesisDemoSeeder::STUDENT_TARGET, DB::table('nstp_enrollments')->count());
        $this->assertGreaterThan(0, DB::table('attendance_records')->count());
        $this->assertGreaterThan(0, DB::table('assessment_submissions')->count());
        $this->assertGreaterThan(0, DB::table('community_projects')->count());
        $this->assertGreaterThan(0, DB::table('facilitator_honoraria')->count());
        $this->assertSame(15, DB::table('student_registrations')->count());
        $this->assertSame(105, DB::table('document_submissions')->count());
        $this->assertSame(120, DB::table('omr_scan_results')->count());
        $this->assertSame(120, DB::table('nstp_student_serial_numbers')->count());
        $this->assertSame(30, DB::table('ai_learning_recommendations')->count());
        $this->assertSame(30, DB::table('evaluation_responses')->count());
        $this->assertSame(30, DB::table('student_risk_snapshots')->count());
    }
}
