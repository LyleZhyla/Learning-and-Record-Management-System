<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\StudentNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEventNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $facilitator;

    private User $student;

    private NstpSection $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $this->section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $this->facilitator->id,
            'code' => 'CWTS-01',
            'name' => 'Section 1',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpEnrollment::create([
            'student_id' => $this->student->id,
            'component_id' => $component->id,
            'section_id' => $this->section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
    }

    public function test_published_material_notifies_enrolled_student_and_opens_materials(): void
    {
        $this->actingAs($this->facilitator)->post('/facilitator/materials', [
            'component_id' => $this->section->component_id,
            'section_id' => $this->section->id,
            'title' => 'Community Service Guide',
            'external_url' => 'https://example.test/guide',
            'status' => 'published',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $notification = StudentNotification::where('user_id', $this->student->id)
            ->where('type', StudentNotification::MATERIAL)->firstOrFail();
        $this->actingAs($this->student)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('Community Service Guide')
            ->assertSee('data-notification-count="1"', false);
        $this->actingAs($this->student)->get('/notifications/student/'.$notification->id.'/open')
            ->assertRedirect('/student/materials');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_published_assessment_notifies_student_and_opens_assessment(): void
    {
        $this->actingAs($this->facilitator)->post('/facilitator/assessments', [
            'section_id' => $this->section->id,
            'title' => 'Community Reflection',
            'type' => 'activity',
            'instructions' => 'Write your reflection.',
            'max_score' => 100,
            'status' => 'published',
            'create_answer_sheet' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $notification = StudentNotification::where('user_id', $this->student->id)
            ->where('type', StudentNotification::ASSESSMENT)->firstOrFail();
        $this->actingAs($this->student)->get('/notifications/student/'.$notification->id.'/open')
            ->assertRedirect('/student/assessments/'.$notification->source_id);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_late_and_absent_statuses_notify_student_and_open_attendance(): void
    {
        $session = AttendanceSession::create([
            'section_id' => $this->section->id,
            'created_by' => $this->facilitator->id,
            'title' => 'Saturday Session',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'token' => str()->random(48),
            'qr_payload' => '',
            'qr_svg' => '',
            'status' => 'open',
        ]);

        $this->actingAs($this->facilitator)->post('/facilitator/attendance/'.$session->id.'/mark', [
            'student_id' => $this->student->id,
            'status' => 'late',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $late = StudentNotification::where('type', StudentNotification::LATE_ATTENDANCE)->firstOrFail();
        $this->actingAs($this->student)->get('/notifications/student/'.$late->id.'/open')
            ->assertRedirect('/student/attendance');

        $this->actingAs($this->facilitator)->post('/facilitator/attendance/'.$session->id.'/mark', [
            'student_id' => $this->student->id,
            'status' => 'absent',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('student_notifications', ['id' => $late->id]);
        $this->assertDatabaseHas('student_notifications', [
            'user_id' => $this->student->id,
            'type' => StudentNotification::ABSENT_ATTENDANCE,
            'title' => 'Absent attendance recorded',
        ]);
    }

    public function test_student_cannot_open_another_students_notification(): void
    {
        $otherStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $notification = StudentNotification::create([
            'user_id' => $this->student->id,
            'type' => StudentNotification::MATERIAL,
            'source_id' => 10,
            'title' => 'Private notification',
            'body' => 'Only the intended student can open this.',
        ]);

        $this->actingAs($otherStudent)->get('/notifications/student/'.$notification->id.'/open')->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }
}
