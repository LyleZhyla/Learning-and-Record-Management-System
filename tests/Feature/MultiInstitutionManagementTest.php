<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Services\InstitutionContext;
use App\Services\InstitutionNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiInstitutionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_provision_and_switch_to_an_isolated_institution(): void
    {
        $systemAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'institution_id' => null,
            'is_system_admin' => true,
        ]);
        $defaultInstitution = Institution::firstOrFail();

        $this->actingAs($systemAdmin)->post('/admin/institutions', [
            'name' => 'Northern State University',
            'short_name' => 'NSU',
            'slug' => 'northern-state-university',
            'code' => 'NSU',
            'email' => 'nstp@nsu.example',
            'website' => 'https://nsu.example',
            'address' => 'Northern Campus',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $institution = Institution::where('code', 'NSU')->firstOrFail();
        $this->assertDatabaseHas('campuses', ['institution_id' => $institution->id, 'code' => 'MAIN']);
        $this->assertSame(5, DB::table('roles')->where('institution_id', $institution->id)->where('is_system', true)->count());
        $this->assertSame(3, DB::table('nstp_components')->where('institution_id', $institution->id)->count());
        $this->assertGreaterThan(0, DB::table('workflow_definitions')->where('institution_id', $institution->id)->count());
        $this->assertGreaterThan(0, DB::table('notification_rules')->where('institution_id', $institution->id)->count());
        $this->assertDatabaseHas('academic_terms', ['institution_id' => $institution->id, 'is_current' => true]);

        User::withoutGlobalScope('institution')->create([
            'institution_id' => $defaultInstitution->id,
            'name' => 'Default Institution Staff',
            'email' => 'default.staff@example.test',
            'password' => 'Password!1234',
            'role' => 'nstp_admin',
            'status' => 'active',
        ]);
        User::withoutGlobalScope('institution')->create([
            'institution_id' => $institution->id,
            'name' => 'Northern Institution Staff',
            'email' => 'northern.staff@example.test',
            'password' => 'Password!1234',
            'role' => 'nstp_admin',
            'status' => 'active',
        ]);

        $this->post('/admin/institutions/'.$institution->id.'/switch')->assertRedirect('/admin/dashboard');
        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('Northern Institution Staff')
            ->assertDontSee('Default Institution Staff');
    }

    public function test_institution_admin_cannot_access_another_institutions_bound_record(): void
    {
        $defaultInstitution = Institution::firstOrFail();
        $otherInstitution = Institution::create([
            'name' => 'Other University', 'short_name' => 'OU', 'slug' => 'other-university',
            'code' => 'OU', 'is_active' => true,
        ]);
        $institutionAdmin = User::factory()->create([
            'institution_id' => $defaultInstitution->id,
            'role' => 'super_admin',
            'status' => 'active',
            'is_system_admin' => false,
        ]);
        $otherUser = User::factory()->create([
            'institution_id' => $otherInstitution->id,
            'role' => 'facilitator',
            'status' => 'active',
        ]);

        $this->actingAs($institutionAdmin)
            ->get('/admin/users/'.$otherUser->id.'/edit')
            ->assertNotFound();
        $this->get('/admin/institutions')->assertForbidden();
    }

    public function test_reference_sequences_are_independent_per_institution(): void
    {
        $firstInstitution = Institution::firstOrFail();
        $secondInstitution = Institution::create([
            'name' => 'Second University', 'short_name' => 'SU', 'slug' => 'second-university',
            'code' => 'SU', 'is_active' => true,
        ]);
        $numbers = app(InstitutionNumberService::class);
        $context = app(InstitutionContext::class);

        $first = $context->runFor($firstInstitution->id, fn () => $numbers->next('report:2026', 'RPT-2026'));
        $second = $context->runFor($secondInstitution->id, fn () => $numbers->next('report:2026', 'RPT-2026'));

        $this->assertSame('RPT-2026-00001', $first);
        $this->assertSame('RPT-2026-00001', $second);
    }
}
