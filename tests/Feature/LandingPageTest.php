<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_public_landing_page(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk()
            ->assertSee('Empowering Students.')
            ->assertSee('Serving Communities.')
            ->assertSee('Student Portal')
            ->assertSee('data-hero-video', false)
            ->assertSee('hf_20261005_182346_a590ff3b-72e5-41ce-8f69-a8c823eaecaa.mp4', false)
            ->assertSee('Scroll for program details')
            ->assertSee('images/characters/snapie-qr.webp', false)
            ->assertSee(route('register'), false)
            ->assertSee(route('serial-numbers.verify'), false);
    }

    public function test_public_landing_page_only_shows_current_student_facing_announcements(): void
    {
        Announcement::create([
            'title' => 'Visible NSTP advisory',
            'body' => 'This notice should be visible on the public landing page.',
            'audience' => 'students',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        Announcement::create([
            'title' => 'Internal facilitator advisory',
            'body' => 'This notice is for facilitators only.',
            'audience' => 'facilitators',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        Announcement::create([
            'title' => 'Draft advisory',
            'body' => 'This notice is still a draft.',
            'audience' => 'all',
            'status' => 'draft',
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Visible NSTP advisory')
            ->assertDontSee('Internal facilitator advisory')
            ->assertDontSee('Draft advisory');
    }

    public function test_authenticated_user_is_redirected_to_their_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)
            ->get(route('landing'))
            ->assertRedirect(route('student.dashboard'));
    }
}
