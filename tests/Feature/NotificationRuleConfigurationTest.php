<?php

namespace Tests\Feature;

use App\Models\LearningMaterial;
use App\Models\NotificationRule;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\StudentNotification;
use App\Models\User;
use App\Services\StudentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationRuleConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_rules_are_seeded_and_available_to_authorized_administrators(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->assertEqualsCanonicalizing([
            'announcement',
            'message',
            'learning_material',
            'assessment',
            'late_attendance',
            'absent_attendance',
        ], NotificationRule::pluck('event_key')->all());

        $this->actingAs($superAdmin)->get(route('admin.notification-rules.index'))
            ->assertOk()
            ->assertSee('Notifications Configuration')
            ->assertSee('Portal channels')
            ->assertSee('Available placeholders');
        $this->actingAs($nstpAdmin)->get(route('nstp_admin.notification-rules.index'))
            ->assertOk()
            ->assertSee('New Learning Materials');
        $this->actingAs($facilitator)->get('/admin/notification-rules')->assertForbidden();
        $this->actingAs($facilitator)->get('/nstp-admin/notification-rules')->assertForbidden();
    }

    public function test_admin_can_configure_channels_templates_and_delayed_delivery(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $rule = NotificationRule::where('event_key', 'learning_material')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.notification-rules.update', $rule), [
            'name' => 'Institution Material Alert',
            'description' => 'Customized material notification.',
            'is_enabled' => '1',
            'channels' => ['bell'],
            'title_template' => 'Material: {material_title}',
            'body_template' => 'Open {material_title} for {section_code}.',
            'schedule_mode' => 'delayed',
            'delay_minutes' => 30,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rule->refresh();
        $this->assertSame(['bell'], $rule->channels);
        $this->assertSame('delayed', $rule->schedule_mode);
        $this->assertSame(30, $rule->delay_minutes);
        $this->assertSame($admin->id, $rule->updated_by);

        [$student, $material] = $this->materialContext($admin);
        app(StudentNotificationService::class)->learningMaterialPublished($material);

        $notification = StudentNotification::where('user_id', $student->id)
            ->where('type', StudentNotification::MATERIAL)
            ->firstOrFail();
        $this->assertSame('Material: Field Guide', $notification->title);
        $this->assertSame('Open Field Guide for CWTS-01.', $notification->body);
        $this->assertTrue($notification->available_at->isFuture());

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('data-notification-count="0"', false)
            ->assertDontSee('Material: Field Guide');

        $notification->update(['available_at' => now()->subMinute()]);
        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Material: Field Guide')
            ->assertSee('data-notification-count="1"', false)
            ->assertDontSee('data-material-notification-count', false);
    }

    public function test_disabled_rule_stops_new_event_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        NotificationRule::where('event_key', 'learning_material')->update(['is_enabled' => false]);
        [, $material] = $this->materialContext($admin);

        app(StudentNotificationService::class)->learningMaterialPublished($material);

        $this->assertDatabaseCount('student_notifications', 0);
    }

    /** @return array{User, LearningMaterial} */
    private function materialContext(User $author): array
    {
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-01',
            'name' => 'CWTS Section 1',
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
        $material = LearningMaterial::create([
            'component_id' => $component->id,
            'section_id' => $section->id,
            'created_by' => $author->id,
            'title' => 'Field Guide',
            'external_url' => 'https://example.test/field-guide',
            'published_at' => now(),
            'status' => 'published',
        ]);

        return [$student, $material];
    }
}
