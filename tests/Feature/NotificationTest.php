<?php

namespace Tests\Feature;

use App\Models\Announcement;
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
}
