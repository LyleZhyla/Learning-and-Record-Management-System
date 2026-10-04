<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSystemGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_student_system_guide(): void
    {
        $this->get('/student/system-guide')->assertRedirect('/login');
    }

    public function test_non_student_cannot_open_the_student_system_guide(): void
    {
        $user = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($user)->get('/student/system-guide')->assertForbidden();
    }

    public function test_student_can_open_the_guided_system_overview(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)
            ->get('/student/system-guide')
            ->assertOk()
            ->assertSee('Know where to go and what to do.')
            ->assertSee('Use your Student portal')
            ->assertSee('Student safety checklist')
            ->assertSee('Start interactive guided tour')
            ->assertSee('admin-tour.js')
            ->assertSee('data-student-tour-root', false)
            ->assertSee('data-student-tour-page-title', false)
            ->assertSeeInOrder(['<p class="nav-label">Account</p>', 'Profile &amp; Security', 'System Guide'], false)
            ->assertSee(route('student.component.edit'), false)
            ->assertSee(route('student.assessments.index'), false);
    }

    public function test_student_dashboard_links_to_the_system_guide(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)
            ->get('/student/dashboard')
            ->assertOk()
            ->assertSee('Start guided tour')
            ->assertSee('data-start-student-tour', false)
            ->assertSee(route('student.system-guide'), false);
    }
}
