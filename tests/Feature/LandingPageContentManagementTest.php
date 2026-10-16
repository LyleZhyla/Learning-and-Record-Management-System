<?php

namespace Tests\Feature;

use App\Models\LandingPageMedia;
use App\Models\NstpComponent;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingPageContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_super_admin_can_open_the_landing_page_editor(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)
            ->get(route('admin.landing-page.edit'))
            ->assertOk()
            ->assertSee('Landing page content &amp; media', false)
            ->assertSee('Add a photo or video')
            ->assertSee('Upload destination')
            ->assertSee('Public Landing Page → Activities →')
            ->assertSee('Display under component')
            ->assertSee('Media library');

        $this->get(route('landing', ['preview' => 1]))
            ->assertOk()
            ->assertSee('Landing page')
            ->assertSee('Editor mode')
            ->assertSee(SystemSetting::LANDING_PAGE_DEFAULTS['hero_line_1']);

        foreach (['nstp_admin', 'coordinator'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get('/admin/landing-page')->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']))
            ->get('/nstp-admin/landing-page')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'coordinator', 'status' => 'active']))
            ->get('/coordinator/landing-page')->assertNotFound();
    }

    public function test_editor_mode_uses_the_actual_landing_page_with_live_editing_controls(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('landing', ['preview' => 1, 'editor' => 1]))
            ->assertOk()
            ->assertSee('Edit the live landing page')
            ->assertSee('Publish changes')
            ->assertSee('data-landing-preview="hero_line_1"', false)
            ->assertSee('data-landing-editor-field="hero_line_1"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="hero_slide_1_image"', false)
            ->assertSee('class="landing-image-upload"', false)
            ->assertSee('js/landing-editor.js');
    }

    public function test_guest_cannot_activate_landing_page_editor_mode(): void
    {
        $this->get(route('landing', ['preview' => 1, 'editor' => 1]))
            ->assertOk()
            ->assertDontSee('Edit the live landing page')
            ->assertDontSee('Publish changes');
    }

    public function test_removed_non_super_admin_landing_page_route_is_not_available(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($facilitator)
            ->get('/coordinator/landing-page')
            ->assertNotFound();
    }

    public function test_super_admin_can_publish_content_that_appears_on_the_public_landing_page(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $content = array_replace(SystemSetting::LANDING_PAGE_DEFAULTS, [
            'hero_line_1' => 'TAU students lead with purpose.',
            'contact_body' => 'Visit the updated NSTP Office information desk.',
        ]);

        $this->actingAs($superAdmin)
            ->put(route('admin.landing-page.update'), $content)
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'landing_page_content',
            'updated_by' => $superAdmin->id,
        ]);

        $this->post(route('logout'));

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('TAU students lead with purpose.')
            ->assertSee('Visit the updated NSTP Office information desk.');
    }

    public function test_landing_page_editor_rejects_invalid_media_urls(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $content = array_replace(SystemSetting::LANDING_PAGE_DEFAULTS, [
            'hero_slide_2_url' => 'not-a-url',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.landing-page.edit'))
            ->put(route('admin.landing-page.update'), $content)
            ->assertRedirect(route('admin.landing-page.edit'))
            ->assertSessionHasErrors('hero_slide_2_url');
    }

    public function test_super_admin_can_upload_a_hero_image_and_store_its_path_in_the_database(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $content = SystemSetting::LANDING_PAGE_DEFAULTS;
        $content['hero_slide_2_image'] = UploadedFile::fake()->image('nstp-activity.jpg', 1600, 900);

        $this->actingAs($admin)
            ->put(route('admin.landing-page.update'), $content)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $saved = SystemSetting::landingPageContent();
        $this->assertStringStartsWith('/storage/landing-page/hero/', $saved['hero_slide_2_url']);
        $this->assertStringEndsWith('.jpg', $saved['hero_slide_2_url']);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $saved['hero_slide_2_url']));
        $this->assertDatabaseHas('system_settings', [
            'key' => 'landing_page_content',
            'updated_by' => $admin->id,
        ]);
    }

    public function test_super_admin_can_manage_photo_and_video_media_from_the_landing_page_editor(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.landing-page.media.store'), [
            'media_type' => 'photo',
            'media_component' => 'cwts',
            'media_title' => 'Community clean-up day',
            'media_description' => 'Students serving the university community.',
            'media_file' => UploadedFile::fake()->image('cleanup.jpg', 1200, 800),
        ])->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Photo uploaded to Public Landing Page → Activities → CWTS.');

        $this->actingAs($admin)->post(route('admin.landing-page.media.store'), [
            'media_type' => 'video',
            'media_component' => 'rotc',
            'media_title' => 'ROTC field training',
            'media_description' => 'Highlights from the latest field activity.',
            'media_file' => UploadedFile::fake()->create('training.mp4', 1024, 'video/mp4'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $photo = LandingPageMedia::where('type', 'photo')->firstOrFail();
        $video = LandingPageMedia::where('type', 'video')->firstOrFail();
        Storage::disk('public')->assertExists($photo->file_path);
        Storage::disk('public')->assertExists($video->file_path);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Community clean-up day')
            ->assertSee('ROTC field training')
            ->assertSee($photo->fileUrl(), false)
            ->assertSee($video->fileUrl(), false);

        $this->delete(route('admin.landing-page.media.destroy', $photo))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('landing_page_media', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->file_path);
    }

    public function test_nstp_admin_and_component_coordinator_can_manage_component_media(): void
    {
        Storage::fake('public');
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $cwts->id]);

        $this->actingAs($nstpAdmin)->get(route('nstp_admin.landing-media.index'))
            ->assertOk()
            ->assertSee('Component news &amp; media', false)
            ->assertSee('Choose destination')
            ->assertSee('Public Landing Page → Activities →')
            ->assertSee('Display under component');

        $this->actingAs($coordinator)->get(route('coordinator.landing-media.index'))
            ->assertOk()
            ->assertSee('value="CWTS Activities" readonly', false)
            ->assertSee('Public Landing Page → Activities →')
            ->assertDontSee('Choose destination');

        $this->actingAs($coordinator)->post(route('coordinator.landing-media.store'), [
            'media_type' => 'photo',
            'media_component' => 'rotc',
            'media_title' => 'CWTS outreach activity',
            'media_file' => UploadedFile::fake()->image('outreach.webp', 1000, 700),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $media = LandingPageMedia::firstOrFail();
        $this->assertSame('cwts', $media->component);

        $rotcMedia = LandingPageMedia::create([
            'type' => 'photo',
            'component' => 'rotc',
            'title' => 'ROTC activity',
            'file_path' => 'landing-page/media/photo/rotc.jpg',
            'created_by' => $nstpAdmin->id,
        ]);

        $this->actingAs($coordinator)
            ->delete(route('coordinator.landing-media.destroy', $rotcMedia))
            ->assertForbidden();
        $this->assertDatabaseHas('landing_page_media', ['id' => $rotcMedia->id]);
    }
}
