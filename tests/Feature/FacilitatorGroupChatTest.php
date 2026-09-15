<?php

namespace Tests\Feature;

use App\Models\ChatGroup;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilitatorGroupChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilitator_can_create_a_group_from_students_in_an_assigned_section(): void
    {
        [$facilitator, $student, , $section] = $this->records();

        $this->actingAs($facilitator)->get('/facilitator/messages')
            ->assertOk()
            ->assertSee('New group chat')
            ->assertSee($section->code)
            ->assertSee($student->name);

        $response = $this->actingAs($facilitator)->post('/facilitator/messages/groups', [
            'name' => 'Community Project Team',
            'section_id' => $section->id,
            'student_ids' => [$student->id],
        ]);

        $group = ChatGroup::firstOrFail();
        $response->assertRedirect('/facilitator/messages/groups/'.$group->id)
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('chat_groups', [
            'id' => $group->id,
            'facilitator_id' => $facilitator->id,
            'section_id' => $section->id,
            'name' => 'Community Project Team',
        ]);
        $this->assertDatabaseHas('chat_group_members', ['chat_group_id' => $group->id, 'user_id' => $facilitator->id]);
        $this->assertDatabaseHas('chat_group_members', ['chat_group_id' => $group->id, 'user_id' => $student->id]);
    }

    public function test_group_members_can_exchange_messages_and_non_members_cannot_open_the_group(): void
    {
        [$facilitator, $student, $outsider, $section] = $this->records();
        $group = ChatGroup::create([
            'section_id' => $section->id,
            'facilitator_id' => $facilitator->id,
            'name' => 'Section Leaders',
        ]);
        $group->members()->attach([$facilitator->id, $student->id]);

        $this->actingAs($facilitator)->post('/facilitator/messages/groups/'.$group->id, [
            'body' => 'Please coordinate your teams for Saturday.',
        ])->assertRedirect('/facilitator/messages/groups/'.$group->id);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('Section Leaders')
            ->assertSee('Please coordinate your teams for Saturday.')
            ->assertSee('/student/messages/groups/'.$group->id, false)
            ->assertSee('data-unread-message-count="1"', false);

        $this->actingAs($student)->get('/student/messages/groups/'.$group->id)
            ->assertOk()
            ->assertSee('Section Leaders')
            ->assertSee('Please coordinate your teams for Saturday.')
            ->assertSee($facilitator->name);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()
            ->assertDontSee('data-unread-message-count', false);

        $this->actingAs($student)->post('/student/messages/groups/'.$group->id, [
            'body' => 'Noted, we will prepare the assignments.',
        ])->assertRedirect('/student/messages/groups/'.$group->id);

        $this->assertDatabaseHas('chat_group_messages', [
            'chat_group_id' => $group->id,
            'sender_id' => $student->id,
            'body' => 'Noted, we will prepare the assignments.',
        ]);

        $this->actingAs($outsider)->get('/student/messages/groups/'.$group->id)->assertNotFound();
        $this->actingAs($outsider)->post('/student/messages/groups/'.$group->id, ['body' => 'Unauthorized'])->assertNotFound();
    }

    public function test_facilitator_cannot_add_a_student_outside_the_selected_assigned_section(): void
    {
        [$facilitator, , $outsider, $section] = $this->records();

        $this->actingAs($facilitator)->post('/facilitator/messages/groups', [
            'name' => 'Invalid Group',
            'section_id' => $section->id,
            'student_ids' => [$outsider->id],
        ])->assertSessionHasErrors('student_ids');

        $this->assertDatabaseCount('chat_groups', 0);
        $this->assertDatabaseCount('chat_group_members', 0);
    }

    private function records(): array
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-GROUP-01',
            'name' => 'Group Chat Section',
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

        return [$facilitator, $student, $outsider, $section];
    }
}
