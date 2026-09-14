<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\ScheduleSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_hours_and_automatically_separate_two_sections_of_one_facilitator(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $first = $this->section($component, $facilitator, 'CWTS-01');
        $second = $this->section($component, $facilitator, 'CWTS-02');

        $this->actingAs($admin)->put('/nstp-admin/schedules/settings', $this->settings($component))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/nstp-admin/schedules/generate', $this->term($component))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('section_schedules', ['section_id' => $first->id, 'day_of_week' => 6, 'starts_at' => '08:00:00', 'ends_at' => '12:00:00', 'is_automatic' => true]);
        $this->assertDatabaseHas('section_schedules', ['section_id' => $second->id, 'day_of_week' => 6, 'starts_at' => '13:00:00', 'ends_at' => '17:00:00', 'is_automatic' => true]);

        $this->actingAs($admin)->get('/nstp-admin/schedules?component_id='.$component->id.'&academic_year=2026-2027&semester=first')
            ->assertOk()->assertSee('Automatic section scheduling')->assertSee('8:00 AM')->assertSee('5:00 PM')->assertSee($facilitator->name);
        $this->actingAs($facilitator)->get('/facilitator/dashboard')
            ->assertOk()->assertSee('Official schedule')->assertSee('Saturday')->assertSee('8:00 AM')->assertSee('1:00 PM');
    }

    public function test_coordinator_can_manage_only_their_component_schedule(): void
    {
        $own = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'is_active' => true]);
        $other = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $own->id]);

        $this->actingAs($coordinator)->put('/coordinator/schedules/settings', $this->settings($own))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('schedule_settings', ['component_id' => $own->id, 'session_minutes' => 240]);

        $this->actingAs($coordinator)->put('/coordinator/schedules/settings', $this->settings($other))->assertForbidden();
        $this->assertDatabaseMissing('schedule_settings', ['component_id' => $other->id]);
    }

    public function test_manual_schedule_edit_enforces_hours_and_facilitator_conflicts(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $first = $this->section($component, $facilitator, 'CWTS-01');
        $second = $this->section($component, $facilitator, 'CWTS-02');
        ScheduleSetting::create([...$this->term($component), 'day_of_week' => 6, 'day_start' => '08:00', 'day_end' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00', 'session_minutes' => 240]);

        $this->actingAs($admin)->put('/admin/schedules/sections/'.$first->id, [...$this->term($component), 'day_of_week' => 6, 'starts_at' => '08:00', 'ends_at' => '12:00'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->put('/admin/schedules/sections/'.$second->id, [...$this->term($component), 'day_of_week' => 6, 'starts_at' => '09:00', 'ends_at' => '14:00'])
            ->assertSessionHasErrors('starts_at');
        $this->actingAs($admin)->put('/admin/schedules/sections/'.$second->id, [...$this->term($component), 'day_of_week' => 6, 'starts_at' => '13:00', 'ends_at' => '16:00'])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_full_day_session_excludes_the_configured_noon_break_from_required_hours(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'is_active' => true]);
        $section = $this->section($component, $facilitator, 'LTS-01');
        $settings = [...$this->settings($component), 'session_hours' => 8];

        $this->actingAs($admin)->put('/nstp-admin/schedules/settings', $settings)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/nstp-admin/schedules/generate', $this->term($component))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('section_schedules', ['section_id' => $section->id, 'starts_at' => '08:00:00', 'ends_at' => '17:00:00']);
    }

    private function section(NstpComponent $component, User $facilitator, string $code): NstpSection
    {
        return NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => $code, 'name' => $code, 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
    }

    private function term(NstpComponent $component): array
    {
        return ['component_id' => $component->id, 'academic_year' => '2026-2027', 'semester' => 'first'];
    }

    private function settings(NstpComponent $component): array
    {
        return [...$this->term($component), 'day_of_week' => 6, 'day_start' => '08:00', 'day_end' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00', 'session_hours' => 4];
    }
}
