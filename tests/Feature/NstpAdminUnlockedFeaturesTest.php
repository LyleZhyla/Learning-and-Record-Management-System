<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NstpAdminUnlockedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_nstp_admin_can_open_facilitators_and_operational_reports(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['name' => 'Visible Facilitator', 'role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($admin)->get('/nstp-admin/accounts?role=facilitator')
            ->assertOk()->assertSee($facilitator->name);
        $this->actingAs($admin)->get('/nstp-admin/reports')
            ->assertOk()->assertSee('Operational reports')->assertSee('Download CSV');
        $this->actingAs($admin)->get('/nstp-admin/reports/students/export')
            ->assertOk()->assertDownload();
    }

    public function test_nstp_admin_can_publish_a_component_targeted_announcement(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $cwtsStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $rotcStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $cwtsSection = NstpSection::create(['component_id' => $cwts->id, 'code' => 'CWTS-01', 'name' => 'CWTS Section', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        $rotcSection = NstpSection::create(['component_id' => $rotc->id, 'code' => 'ROTC-01', 'name' => 'ROTC Section', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $cwtsStudent->id, 'component_id' => $cwts->id, 'section_id' => $cwtsSection->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $rotcStudent->id, 'component_id' => $rotc->id, 'section_id' => $rotcSection->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        $this->actingAs($admin)->post('/nstp-admin/announcements', [
            'title' => 'CWTS Assembly',
            'body' => 'Please attend the component assembly.',
            'audience' => 'students',
            'component_id' => $cwts->id,
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $announcement = Announcement::firstOrFail();
        $this->assertSame($admin->id, $announcement->author_id);
        $this->assertNotNull($announcement->published_at);
        $this->actingAs($cwtsStudent)->get('/student/announcements')->assertOk()->assertSee('CWTS Assembly');
        $this->actingAs($rotcStudent)->get('/student/announcements')->assertOk()->assertDontSee('CWTS Assembly');
    }

    public function test_draft_and_expired_announcements_are_not_visible_to_portal_users(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        Announcement::create(['author_id' => $admin->id, 'title' => 'Hidden Draft', 'body' => 'Draft', 'audience' => 'all', 'status' => 'draft']);
        Announcement::create(['author_id' => $admin->id, 'title' => 'Expired Notice', 'body' => 'Expired', 'audience' => 'all', 'status' => 'published', 'published_at' => now()->subDay(), 'expires_at' => now()->subHour()]);

        $this->actingAs($student)->get('/student/announcements')
            ->assertOk()->assertDontSee('Hidden Draft')->assertDontSee('Expired Notice');
    }
}
