<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_role_can_upload_a_profile_picture(): void
    {
        Storage::fake('local');

        foreach ([
            'super_admin' => '/admin/profile',
            'nstp_admin' => '/nstp-admin/profile',
            'coordinator' => '/coordinator/profile',
            'facilitator' => '/facilitator/profile',
            'student' => '/student/profile',
        ] as $role => $endpoint) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)->put($endpoint, [
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo' => UploadedFile::fake()->image($role.'.jpg', 400, 400),
            ])->assertSessionHasNoErrors();

            $path = $user->fresh()->profile_photo_path;
            $this->assertNotNull($path);
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_replacing_a_profile_picture_removes_the_previous_private_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($user)->put('/student/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo' => UploadedFile::fake()->image('first.png'),
        ])->assertSessionHasNoErrors();
        $oldPath = $user->fresh()->profile_photo_path;

        $this->actingAs($user)->put('/student/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo' => UploadedFile::fake()->image('second.webp'),
        ])->assertSessionHasNoErrors();
        $newPath = $user->fresh()->profile_photo_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_non_image_profile_upload_is_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);

        $this->actingAs($user)->put('/coordinator/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('profile_photo');

        $this->assertNull($user->fresh()->profile_photo_path);
    }
}
