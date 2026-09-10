<?php

namespace Tests\Feature;

use App\Mail\AccountCreatedMail;
use App\Models\Assessment;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_user_management(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Staff Account Management')
            ->assertSee($staff->name)
            ->assertDontSee($student->name)
            ->assertSee('Edit')
            ->assertSee('Delete');

        $this->actingAs($admin)->get('/admin/students')
            ->assertOk()
            ->assertSee($student->name)
            ->assertDontSee($staff->name)
            ->assertSee('Download QR')
            ->assertSee('Delete')
            ->assertSee('Create student account')
            ->assertSee('href="'.route('admin.users.create', ['role' => 'student']).'"', false);
    }

    public function test_staff_directory_uses_one_creation_button_with_role_choices(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('href="'.route('admin.users.create').'"', false)
            ->assertSee('Create staff account')
            ->assertSee('create-account-button', false)
            ->assertSee('account-create-button.css', false);

        $this->actingAs($admin)->get('/admin/users/create')
            ->assertOk()
            ->assertSee('New staff account')
            ->assertSee('Create staff account')
            ->assertSee('NSTP Admin')
            ->assertSee('Coordinator')
            ->assertSee('Facilitator')
            ->assertDontSee('option value="student"', false)
            ->assertDontSee('option value="super_admin"', false)
            ->assertDontSee('name="password"', false);
    }

    public function test_super_admin_can_create_each_supported_role(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);

        foreach (array_keys(User::ROLE_LABELS) as $index => $role) {
            $response = $this->actingAs($admin)->post('/admin/users', [
                'name' => "Test User {$index}",
                'email' => "role{$index}@example.test",
                'role' => $role,
                'status' => 'active',
                'nstp_component_id' => $role === 'coordinator' ? $component->id : null,
            ])->assertSessionHasNoErrors()->assertSessionHas('temporary_password');

            $createdUser = User::where('email', "role{$index}@example.test")->firstOrFail();
            $temporaryPassword = $response->getSession()->get('temporary_password');
            $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12,}$/', $temporaryPassword);
            $this->assertTrue(Hash::check($temporaryPassword, $createdUser->password));
            $this->assertTrue($createdUser->must_change_password);
            $this->assertSame($role, $createdUser->role);

            Mail::assertSent(AccountCreatedMail::class, fn (AccountCreatedMail $mail): bool => $mail->hasTo($createdUser->email)
                && $mail->recipientName === $createdUser->name
                && $mail->accountEmail === $createdUser->email
                && $mail->temporaryPassword === $temporaryPassword
                && $mail->roleLabel === $createdUser->roleLabel()
            );
        }

        Mail::assertSent(AccountCreatedMail::class, count(User::ROLE_LABELS));
    }

    public function test_super_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/status")
            ->assertSessionHasErrors('status');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_the_last_active_super_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'nstp_admin',
            'status' => 'active',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    public function test_password_reset_requires_a_change_on_next_login(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'must_change_password' => false]);

        $this->actingAs($admin)->put("/admin/users/{$student->id}/password", [
            'password' => 'New!Password2026',
            'password_confirmation' => 'New!Password2026',
        ])->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertTrue(Hash::check('New!Password2026', $student->password));
        $this->assertTrue($student->must_change_password);
    }

    public function test_super_admin_can_delete_an_account_without_deleting_institutional_content(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Preserved Assessment', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published']);

        $this->actingAs($admin)->delete('/admin/users/'.$facilitator->id, [
            'confirmation' => $facilitator->email,
        ])
            ->assertRedirect('/admin/users')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $facilitator->id]);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'created_by' => $admin->id]);
        $this->assertDatabaseHas('nstp_sections', ['id' => $section->id, 'facilitator_id' => null]);
    }

    public function test_super_admin_can_permanently_delete_student_and_coordinator_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);

        foreach ([$student, $coordinator] as $account) {
            $this->actingAs($admin)
                ->get('/admin/users/'.$account->id.'/delete')
                ->assertOk()
                ->assertSee($account->email)
                ->assertSee('Permanently delete account');

            $this->actingAs($admin)->delete('/admin/users/'.$account->id, [
                'confirmation' => $account->email,
            ])->assertSessionHasNoErrors();

            $this->assertDatabaseMissing('users', ['id' => $account->id]);
        }
    }

    public function test_account_deletion_requires_the_exact_email_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($admin)->from('/admin/users/'.$facilitator->id.'/delete')
            ->delete('/admin/users/'.$facilitator->id, ['confirmation' => 'wrong@example.test'])
            ->assertRedirect('/admin/users/'.$facilitator->id.'/delete')
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('users', ['id' => $facilitator->id]);
    }

    public function test_deleting_a_student_removes_private_profile_and_document_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'profile_photo_path' => 'profile-photos/student.jpg',
        ]);
        $student->studentProfile()->create([
            'last_name' => 'Student',
            'first_name' => 'Imported',
            'province' => 'Pangasinan',
            'province_code' => '015500000',
            'city_municipality' => 'Lingayen',
            'city_municipality_code' => '015522000',
            'barangay' => 'Poblacion',
            'barangay_code' => '015522001',
            'date_of_birth' => '2006-05-20',
            'birth_province' => 'Pangasinan',
            'birth_province_code' => '015500000',
            'birth_city_municipality' => 'Lingayen',
            'birth_city_municipality_code' => '015522000',
            'religion' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09171234567',
            'emergency_contact_name' => 'Maria Student',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09981234567',
            'emergency_same_address' => true,
            'student_number' => '2026000011',
            'college' => 'College of Engineering and Technology',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '1A',
            'cor_path' => 'student-imports/cor/student.pdf',
            'formal_photo_path' => 'student-imports/formal-photos/student.jpg',
        ]);

        foreach (['profile-photos/student.jpg', 'student-imports/cor/student.pdf', 'student-imports/formal-photos/student.jpg'] as $path) {
            Storage::disk('local')->put($path, 'private file');
        }

        $this->actingAs($admin)->delete('/admin/users/'.$student->id, [
            'confirmation' => $student->email,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseMissing('student_profiles', ['user_id' => $student->id]);
        Storage::disk('local')->assertMissing('profile-photos/student.jpg');
        Storage::disk('local')->assertMissing('student-imports/cor/student.pdf');
        Storage::disk('local')->assertMissing('student-imports/formal-photos/student.jpg');
    }

    public function test_admin_accounts_are_not_available_to_the_permanent_account_delete_function(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        foreach ([$admin, $nstpAdmin] as $protectedAccount) {
            $this->actingAs($admin)->get('/admin/users/'.$protectedAccount->id.'/delete')->assertNotFound();
            $this->actingAs($admin)->delete('/admin/users/'.$protectedAccount->id, [
                'confirmation' => $protectedAccount->email,
            ])->assertNotFound();
            $this->assertDatabaseHas('users', ['id' => $protectedAccount->id]);
        }
    }
}
