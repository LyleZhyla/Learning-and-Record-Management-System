<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\ChatMessage;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\StudentNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_role_has_the_notification_bell(): void
    {
        $author = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        Announcement::create([
            'author_id' => $author->id,
            'title' => 'General NSTP Notice',
            'body' => 'This notification is visible to everyone.',
            'audience' => 'all',
            'status' => 'published',
            'published_at' => now(),
        ]);

        foreach ([
            'super_admin' => '/admin/dashboard',
            'nstp_admin' => '/nstp-admin/dashboard',
            'coordinator' => '/coordinator/dashboard',
            'facilitator' => '/facilitator/dashboard',
            'student' => '/student/dashboard',
        ] as $role => $path) {
            $user = $role === 'nstp_admin' ? $author : User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get($path)
                ->assertOk()
                ->assertSee('notification-bell', false)
                ->assertSee('General NSTP Notice')
                ->assertSee('1 unread');
        }
    }

    public function test_user_can_mark_visible_notifications_as_read(): void
    {
        $author = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $announcement = Announcement::create([
            'author_id' => $author->id,
            'title' => 'Read Me',
            'body' => 'Notification content.',
            'audience' => 'students',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($student)->post('/notifications/'.$announcement->id.'/read')->assertRedirect();
        $this->assertDatabaseHas('announcement_reads', ['announcement_id' => $announcement->id, 'user_id' => $student->id]);
        $this->actingAs($student)->get('/student/dashboard')->assertOk()->assertSee('0 unread');
    }

    public function test_clicking_announcement_marks_it_read_and_opens_announcements(): void
    {
        $author = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $announcement = Announcement::create([
            'author_id' => $author->id,
            'title' => 'Clickable announcement',
            'body' => 'Open the announcement page.',
            'audience' => 'students',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($student)->get('/notifications/announcements/'.$announcement->id.'/open')
            ->assertRedirect('/student/announcements');
        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_user_cannot_mark_an_out_of_scope_notification_as_read(): void
    {
        $author = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $announcement = Announcement::create([
            'author_id' => $author->id,
            'title' => 'Facilitators Only',
            'body' => 'Restricted notification.',
            'audience' => 'facilitators',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($student)->post('/notifications/'.$announcement->id.'/read')->assertNotFound();
        $this->assertDatabaseEmpty('announcement_reads');
    }

    public function test_opening_a_nav_category_clears_only_its_bell_notifications(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $material = StudentNotification::create([
            'user_id' => $student->id,
            'type' => StudentNotification::MATERIAL,
            'source_id' => 10,
            'title' => 'New material',
            'body' => 'A material is available.',
        ]);
        $assessment = StudentNotification::create([
            'user_id' => $student->id,
            'type' => StudentNotification::ASSESSMENT,
            'source_id' => 11,
            'title' => 'New assessment',
            'body' => 'An assessment is available.',
        ]);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('/notifications/categories/materials/open', false)
            ->assertSee('data-notification-count="2"', false);

        $this->actingAs($student)->get('/notifications/categories/materials/open')
            ->assertRedirect('/student/materials');

        $this->assertNotNull($material->fresh()->read_at);
        $this->assertNull($assessment->fresh()->read_at);
        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('data-notification-count="1"', false)
            ->assertDontSee('New material')
            ->assertSee('New assessment')
            ->assertDontSee('data-material-notification-count', false);
    }

    public function test_opening_announcements_from_nav_clears_visible_announcements_from_the_bell(): void
    {
        $author = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $announcement = Announcement::create([
            'author_id' => $author->id,
            'title' => 'Navigation announcement',
            'body' => 'Open this from the navigation.',
            'audience' => 'facilitators',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($facilitator)->get('/notifications/categories/announcements/open')
            ->assertRedirect('/facilitator/announcements');

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $facilitator->id,
        ]);
        $this->actingAs($facilitator)->get('/facilitator/dashboard')
            ->assertOk()
            ->assertSee('data-notification-count="0"', false)
            ->assertDontSee('Navigation announcement');
    }

    public function test_opening_messages_from_nav_clears_unread_messages_from_the_bell(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-NOTIF-01',
            'name' => 'Notification Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
        $message = ChatMessage::create([
            'section_id' => $section->id,
            'sender_id' => $student->id,
            'recipient_id' => $facilitator->id,
            'body' => 'Please check this message.',
        ]);

        $this->actingAs($facilitator)->get('/notifications/categories/messages/open')
            ->assertRedirect('/facilitator/messages');

        $this->assertNotNull($message->fresh()->read_at);
        $this->actingAs($facilitator)->get('/facilitator/dashboard')
            ->assertOk()
            ->assertSee('data-notification-count="0"', false)
            ->assertDontSee('data-unread-message-count', false);
    }

    public function test_category_links_use_the_correct_destination_for_each_account(): void
    {
        foreach ([
            ['super_admin', 'materials', '/admin/materials'],
            ['nstp_admin', 'assessments', '/nstp-admin/assessments'],
            ['coordinator', 'assessments', '/coordinator/grades'],
            ['facilitator', 'attendance', '/facilitator/attendance'],
            ['student', 'announcements', '/student/announcements'],
        ] as [$role, $category, $destination]) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)->get('/notifications/categories/'.$category.'/open')
                ->assertRedirect($destination);
        }
    }
}
