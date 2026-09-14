<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\NstpComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_nstp_admin_and_coordinator_can_create_their_own_announcements(): void
    {
        $component = NstpComponent::create([
            'code' => 'CWTS', 'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40, 'is_active' => true,
        ]);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $coordinator = User::factory()->create([
            'role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id,
        ]);

        $this->actingAs($nstpAdmin)->post('/nstp-admin/announcements', $this->payload('NSTP Admin Notice', $component->id))
            ->assertRedirect();
        $this->actingAs($coordinator)->post('/coordinator/announcements', $this->payload('Coordinator Notice'))
            ->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'title' => 'NSTP Admin Notice', 'author_id' => $nstpAdmin->id, 'component_id' => $component->id,
        ]);
        $this->assertDatabaseHas('announcements', [
            'title' => 'Coordinator Notice', 'author_id' => $coordinator->id, 'component_id' => $component->id,
        ]);
    }

    public function test_creators_can_only_view_edit_and_delete_their_own_announcements(): void
    {
        $component = NstpComponent::create([
            'code' => 'ROTC', 'name' => 'Reserve Officers Training Corps',
            'default_section_capacity' => 40, 'is_active' => true,
        ]);
        $first = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $second = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $firstAnnouncement = $this->announcement($first, 'First Author Announcement');
        $secondAnnouncement = $this->announcement($second, 'Second Author Announcement', $component->id);

        $this->actingAs($first)->get('/nstp-admin/announcements')
            ->assertOk()
            ->assertViewHas('announcements', fn ($announcements) => $announcements->count() === 1 && $announcements->first()->is($firstAnnouncement));
        $this->actingAs($second)->get('/coordinator/announcements')
            ->assertOk()
            ->assertViewHas('announcements', fn ($announcements) => $announcements->count() === 1 && $announcements->first()->is($secondAnnouncement));

        $this->actingAs($first)->get("/nstp-admin/announcements/{$secondAnnouncement->id}/edit")->assertForbidden();
        $this->actingAs($first)->delete("/nstp-admin/announcements/{$secondAnnouncement->id}")->assertForbidden();
        $this->actingAs($second)->put("/coordinator/announcements/{$firstAnnouncement->id}", $this->payload('Unauthorized change'))->assertForbidden();
        $this->actingAs($second)->delete("/coordinator/announcements/{$firstAnnouncement->id}")->assertForbidden();

        $this->assertDatabaseHas('announcements', ['id' => $firstAnnouncement->id]);
        $this->assertDatabaseHas('announcements', ['id' => $secondAnnouncement->id]);
    }

    public function test_super_admin_has_full_access_to_all_announcements(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);
        $first = $this->announcement($nstpAdmin, 'Created by NSTP Admin');
        $second = $this->announcement($coordinator, 'Created by Coordinator');

        $this->actingAs($superAdmin)->get('/admin/announcements')
            ->assertOk()
            ->assertSee($first->title)
            ->assertSee($second->title)
            ->assertSee($nstpAdmin->name)
            ->assertSee($coordinator->name)
            ->assertSee('+ New announcement');

        $this->actingAs($superAdmin)->get('/admin/announcements/create')->assertOk();
        $this->actingAs($superAdmin)->post('/admin/announcements', $this->payload('Created by Super Admin'))
            ->assertRedirect();
        $this->assertDatabaseHas('announcements', [
            'title' => 'Created by Super Admin',
            'author_id' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)->get("/admin/announcements/{$first->id}/edit")->assertOk();
        $this->actingAs($superAdmin)->put("/admin/announcements/{$first->id}", $this->payload('Updated by Super Admin'))
            ->assertRedirect();
        $this->assertDatabaseHas('announcements', [
            'id' => $first->id,
            'title' => 'Updated by Super Admin',
            'author_id' => $nstpAdmin->id,
        ]);

        $this->actingAs($superAdmin)->delete("/admin/announcements/{$first->id}")
            ->assertRedirect('/admin/announcements');

        $this->assertDatabaseMissing('announcements', ['id' => $first->id]);
        $this->assertDatabaseHas('announcements', ['id' => $second->id]);
    }

    /** @return array<string, mixed> */
    private function payload(string $title, ?int $componentId = null): array
    {
        return [
            'title' => $title,
            'body' => 'Important announcement details.',
            'audience' => 'all',
            'component_id' => $componentId,
            'status' => 'published',
            'expires_at' => null,
        ];
    }

    private function announcement(User $author, string $title, ?int $componentId = null): Announcement
    {
        return Announcement::create([
            'author_id' => $author->id,
            'component_id' => $componentId,
            'title' => $title,
            'body' => 'Announcement body.',
            'audience' => 'all',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
