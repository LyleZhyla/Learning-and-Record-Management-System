<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_has_a_private_reports_page(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Current Student']);
        $otherStudent = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Other Student']);

        $this->actingAs($student)->get('/student/reports')
            ->assertOk()
            ->assertSee('My NSTP report')
            ->assertSee('Current Student')
            ->assertDontSee($otherStudent->name)
            ->assertSee('href="'.route('student.reports.index').'"', false);
    }

    public function test_non_students_cannot_open_the_student_reports_page(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($facilitator)->get('/student/reports')->assertForbidden();
    }
}
