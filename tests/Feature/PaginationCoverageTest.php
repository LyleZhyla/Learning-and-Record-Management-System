<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sectioning_and_schedules_paginate_without_losing_term_filters(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        foreach (range(1, 17) as $number) {
            $this->section($component, $facilitator, sprintf('CWTS-%02d', $number));
        }

        $sectioning = $this->actingAs($admin)->get('/nstp-admin/sections?component_id='.$component->id.'&academic_year=2026-2027&semester=first&page=2');
        $sectioning->assertOk()
            ->assertViewHas('sections', fn ($sections) => $sections->total() === 17
                && $sections->count() === 5
                && str_contains($sections->url(1), 'academic_year=2026-2027')
                && str_contains($sections->url(1), 'component_id='.$component->id))
            ->assertSee('CWTS-17')
            ->assertDontSee('CWTS-01');

        $schedules = $this->actingAs($admin)->get('/nstp-admin/schedules?component_id='.$component->id.'&academic_year=2026-2027&semester=first&page=2');
        $schedules->assertOk()
            ->assertViewHas('sections', fn ($sections) => $sections->total() === 17
                && $sections->count() === 2
                && str_contains($sections->url(1), 'semester=first'))
            ->assertSee('CWTS-17')
            ->assertSee('17</dd>', false);
    }

    public function test_report_preview_paginates_while_report_data_remains_complete(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        foreach (range(1, 21) as $number) {
            User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => sprintf('Student %02d', $number)]);
        }

        $this->actingAs($admin)->get('/admin/reports?type=students&page=1')
            ->assertOk()
            ->assertViewHas('preview', fn ($preview) => $preview->total() === 21 && $preview->count() === 20)
            ->assertViewHas('report', fn (array $report) => $report['rows']->count() === 21)
            ->assertSee('Student 01')
            ->assertDontSee('Student 21');
        $this->actingAs($admin)->get('/admin/reports?type=students&page=2')
            ->assertOk()
            ->assertSee('Student 21')
            ->assertDontSee('Student 01');
    }

    public function test_attendance_and_assessment_rosters_paginate_students(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = $this->section($component, $facilitator, 'CWTS-01');
        foreach (range(1, 21) as $number) {
            $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => sprintf('Student %02d', $number)]);
            NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        }
        $session = AttendanceSession::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Roster check', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => 'test', 'qr_svg' => '<svg></svg>', 'status' => 'open']);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Roster activity', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()]);

        $this->actingAs($facilitator)->get('/facilitator/attendance/'.$session->id.'?page=2')
            ->assertOk()
            ->assertViewHas('enrolledStudents', fn ($students) => $students->total() === 21 && $students->count() === 1)
            ->assertSee('Student 21')
            ->assertDontSee('Student 01');
        $this->actingAs($facilitator)->get('/facilitator/assessments/'.$assessment->id.'?page=2')
            ->assertOk()
            ->assertViewHas('students', fn ($students) => $students->total() === 21 && $students->count() === 6)
            ->assertSee('Student 21')
            ->assertDontSee('Student 01');
    }

    public function test_grouped_report_preview_keeps_section_heading_on_later_pages(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = $this->section($component, $facilitator, 'CWTS-01');
        foreach (range(1, 21) as $number) {
            $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => sprintf('Student %02d', $number)]);
            NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        }

        $this->actingAs($admin)->get('/admin/reports?type=students_by_section&page=2')
            ->assertOk()
            ->assertViewHas('preview', fn ($preview) => $preview->total() === 21 && $preview->count() === 1)
            ->assertSee('CWTS-01')
            ->assertSee('Student 21')
            ->assertDontSee('Student 01');
    }

    public function test_gradebook_and_performance_paginate_without_changing_section_totals(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $section = $this->section($component, $facilitator, 'CWTS-01');
        foreach (range(1, 16) as $number) {
            $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => sprintf('Student %02d', $number)]);
            NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        }

        $this->actingAs($facilitator)->get('/facilitator/grades?section='.$section->id.'&page=2')
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries->total() === 16 && $summaries->count() === 1)
            ->assertSee('Student 16')
            ->assertDontSee('Student 01');
        $this->actingAs($coordinator)->get('/coordinator/performance?section_id='.$section->id.'&page=2')
            ->assertOk()
            ->assertViewHas('summaries', fn ($summaries) => $summaries->total() === 16 && $summaries->count() === 1)
            ->assertSee('Student 16')
            ->assertSee('16</strong><small>Enrolled students', false);
    }

    private function section(NstpComponent $component, User $facilitator, string $code): NstpSection
    {
        return NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => $code,
            'name' => $code,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
    }
}
