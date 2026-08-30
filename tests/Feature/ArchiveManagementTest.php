<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_archive_and_restore_supported_record_groups(): void
    {
        [$superAdmin, $attendance, $log, $notification, $draft] = $this->records();

        $this->actingAs($superAdmin)->post('/admin/archives/attendance')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(AttendanceRecord::find($attendance->id));
        $this->assertNotNull(AttendanceRecord::onlyArchived()->find($attendance->id));

        $this->actingAs($superAdmin)->post('/admin/archives/system-logs')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(AuditLog::find($log->id));
        $this->assertNotNull(AuditLog::onlyArchived()->find($log->id));

        $this->actingAs($superAdmin)->post('/admin/archives/notifications')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(Announcement::find($notification->id));
        $this->assertNotNull(Announcement::onlyArchived()->find($notification->id));
        $this->assertNotNull(Announcement::find($draft->id));

        $this->actingAs($superAdmin)->get('/admin/archives')
            ->assertOk()
            ->assertSee('Operational records archive')
            ->assertSee('Recently archived records')
            ->assertSee($notification->title);

        foreach (['attendance', 'system-logs', 'notifications'] as $type) {
            $this->actingAs($superAdmin)->patch('/admin/archives/'.$type.'/restore')
                ->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertNotNull(AttendanceRecord::find($attendance->id));
        $this->assertNotNull(AuditLog::find($log->id));
        $this->assertNotNull(Announcement::find($notification->id));
    }

    public function test_non_super_admin_cannot_open_or_modify_archives(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/admin/archives')->assertForbidden();
        $this->actingAs($student)->post('/admin/archives/attendance')->assertForbidden();
        $this->actingAs($student)->patch('/admin/archives/attendance/restore')->assertForbidden();
    }

    public function test_unknown_archive_type_is_not_found(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)->post('/admin/archives/unknown')->assertNotFound();
    }

    private function records(): array
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        $session = AttendanceSession::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Archived Session', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour(), 'token' => str()->random(48), 'qr_payload' => 'archive-test', 'qr_svg' => '', 'status' => 'closed']);
        $attendance = AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'checked_in_at' => now()->subHour(), 'source' => 'qr', 'recorded_by' => $facilitator->id]);
        $log = AuditLog::create(['user_id' => $student->id, 'actor_name' => 'Archive Test Student', 'actor_email' => $student->email, 'actor_role' => 'student', 'action' => 'view', 'description' => 'Original audit record', 'method' => 'GET', 'route_name' => 'test.archive', 'path' => '/test/archive', 'status_code' => 200, 'duration_ms' => 1, 'created_at' => now()->subHour()]);
        $notification = Announcement::create(['author_id' => $superAdmin->id, 'title' => 'Notification to Archive', 'body' => 'Published notification.', 'audience' => 'all', 'status' => 'published', 'published_at' => now()]);
        $draft = Announcement::create(['author_id' => $superAdmin->id, 'title' => 'Draft Must Stay Active', 'body' => 'Draft.', 'audience' => 'all', 'status' => 'draft']);

        return [$superAdmin, $attendance, $log, $notification, $draft];
    }
}
