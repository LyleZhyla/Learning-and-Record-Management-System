<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LearningMaterial;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\StudentNotification;
use App\Models\User;
use App\Services\StudentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalEventNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $nstpAdmin;

    private User $coordinator;

    private User $facilitator;

    private User $student;

    private NstpComponent $component;

    private NstpSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $this->coordinator = User::factory()->create([
            'role' => 'coordinator',
            'status' => 'active',
            'nstp_component_id' => $this->component->id,
        ]);
        $this->facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->section = NstpSection::create([
            'component_id' => $this->component->id,
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
            'component_id' => $this->component->id,
            'section_id' => $this->section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
    }

    public function test_material_notifies_accounts_with_a_materials_page_and_opens_the_correct_route(): void
    {
        $material = LearningMaterial::create([
            'component_id' => $this->component->id,
            'section_id' => $this->section->id,
            'created_by' => $this->nstpAdmin->id,
            'title' => 'Disaster Preparedness Guide',
            'external_url' => 'https://example.test/material',
            'published_at' => now(),
            'status' => 'published',
        ]);

        app(StudentNotificationService::class)->learningMaterialPublished($material);

        foreach ([$this->student, $this->facilitator, $this->superAdmin] as $recipient) {
            $this->assertDatabaseHas('student_notifications', [
                'user_id' => $recipient->id,
                'type' => StudentNotification::MATERIAL,
                'source_id' => $material->id,
            ]);
        }
        $this->assertDatabaseMissing('student_notifications', ['user_id' => $this->nstpAdmin->id, 'source_id' => $material->id]);
        $this->assertDatabaseMissing('student_notifications', ['user_id' => $this->coordinator->id, 'source_id' => $material->id]);

        $facilitatorNotification = $this->notificationFor($this->facilitator, StudentNotification::MATERIAL);
        $this->actingAs($this->facilitator)
            ->get('/notifications/events/'.$facilitatorNotification->id.'/open')
            ->assertRedirect('/facilitator/materials');
        $this->assertNotNull($facilitatorNotification->fresh()->read_at);

        $adminNotification = $this->notificationFor($this->superAdmin, StudentNotification::MATERIAL);
        $this->actingAs($this->superAdmin)
            ->get('/notifications/events/'.$adminNotification->id.'/open')
            ->assertRedirect('/admin/materials');
    }

    public function test_assessment_notifies_relevant_accounts_and_coordinator_opens_section_grades(): void
    {
        $assessment = Assessment::create([
            'section_id' => $this->section->id,
            'created_by' => $this->nstpAdmin->id,
            'title' => 'Community Needs Assessment',
            'type' => 'activity',
            'instructions' => 'Complete the activity.',
            'max_score' => 100,
            'published_at' => now(),
            'status' => 'published',
        ]);

        app(StudentNotificationService::class)->assessmentPublished($assessment);

        foreach ([$this->student, $this->facilitator, $this->coordinator, $this->superAdmin] as $recipient) {
            $this->assertDatabaseHas('student_notifications', [
                'user_id' => $recipient->id,
                'type' => StudentNotification::ASSESSMENT,
                'source_id' => $assessment->id,
            ]);
        }
        $this->assertDatabaseMissing('student_notifications', ['user_id' => $this->nstpAdmin->id, 'source_id' => $assessment->id]);

        $coordinatorNotification = $this->notificationFor($this->coordinator, StudentNotification::ASSESSMENT);
        $this->actingAs($this->coordinator)
            ->get('/notifications/events/'.$coordinatorNotification->id.'/open')
            ->assertRedirect('/coordinator/grades?section='.$this->section->id);

        $facilitatorNotification = $this->notificationFor($this->facilitator, StudentNotification::ASSESSMENT);
        $this->actingAs($this->facilitator)
            ->get('/notifications/events/'.$facilitatorNotification->id.'/open')
            ->assertRedirect('/facilitator/assessments/'.$assessment->id);
    }

    public function test_attendance_alert_notifies_relevant_accounts_and_opens_the_session(): void
    {
        $session = AttendanceSession::create([
            'section_id' => $this->section->id,
            'created_by' => $this->nstpAdmin->id,
            'title' => 'Saturday Session',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'token' => str()->random(48),
            'qr_payload' => '',
            'qr_svg' => '',
            'status' => 'open',
        ]);
        $record = AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $this->student->id,
            'status' => 'late',
            'checked_in_at' => now(),
            'source' => 'manual',
            'recorded_by' => $this->nstpAdmin->id,
        ]);

        app(StudentNotificationService::class)->attendanceRecorded($record);

        foreach ([$this->student, $this->facilitator, $this->coordinator, $this->superAdmin] as $recipient) {
            $this->assertDatabaseHas('student_notifications', [
                'user_id' => $recipient->id,
                'type' => StudentNotification::LATE_ATTENDANCE,
                'source_id' => $record->id,
            ]);
        }

        foreach ([
            [$this->facilitator, '/facilitator/attendance/'.$session->id],
            [$this->coordinator, '/coordinator/attendance/'.$session->id],
            [$this->superAdmin, '/admin/attendance/'.$session->id],
        ] as [$recipient, $destination]) {
            $notification = $this->notificationFor($recipient, StudentNotification::LATE_ATTENDANCE);
            $this->actingAs($recipient)
                ->get('/notifications/events/'.$notification->id.'/open')
                ->assertRedirect($destination);
        }
    }

    public function test_staff_notification_bell_and_navigation_show_the_unread_number(): void
    {
        StudentNotification::create([
            'user_id' => $this->facilitator->id,
            'type' => StudentNotification::MATERIAL,
            'source_id' => 99,
            'title' => 'New facilitator material',
            'body' => 'A material is available.',
        ]);

        $this->actingAs($this->facilitator)->get('/facilitator/dashboard')
            ->assertOk()
            ->assertSee('New facilitator material')
            ->assertSee('data-notification-count="1"', false);
    }

    public function test_an_account_cannot_open_another_accounts_event_notification(): void
    {
        $notification = StudentNotification::create([
            'user_id' => $this->facilitator->id,
            'type' => StudentNotification::MATERIAL,
            'source_id' => 99,
            'title' => 'Private facilitator notification',
            'body' => 'Only the recipient can open this.',
        ]);

        $this->actingAs($this->coordinator)
            ->get('/notifications/events/'.$notification->id.'/open')
            ->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    private function notificationFor(User $user, string $type): StudentNotification
    {
        return StudentNotification::where('user_id', $user->id)->where('type', $type)->firstOrFail();
    }
}
