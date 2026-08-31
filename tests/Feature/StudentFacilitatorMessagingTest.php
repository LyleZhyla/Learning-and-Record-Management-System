<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentFacilitatorMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_and_assigned_facilitator_can_exchange_private_messages(): void
    {
        [$student, $facilitator, $otherFacilitator, $section] = $this->records();

        $this->actingAs($student)->get('/student/messages')
            ->assertOk()
            ->assertSee('Student–facilitator chat')
            ->assertSee($facilitator->name)
            ->assertSee($section->code)
            ->assertDontSee($otherFacilitator->name);

        $this->actingAs($student)->post('/student/messages/'.$facilitator->id, [
            'body' => 'Good afternoon, may I ask about our activity?',
        ])->assertRedirect('/student/messages/'.$facilitator->id)
            ->assertSessionHasNoErrors();

        $message = ChatMessage::firstOrFail();
        $this->assertSame($section->id, $message->section_id);
        $this->assertSame($student->id, $message->sender_id);
        $this->assertSame($facilitator->id, $message->recipient_id);
        $this->assertNull($message->read_at);

        $this->actingAs($facilitator)->get('/facilitator/messages/'.$student->id)
            ->assertOk()
            ->assertSee('Good afternoon, may I ask about our activity?');

        $this->assertNotNull($message->fresh()->read_at);

        $this->actingAs($facilitator)->post('/facilitator/messages/'.$student->id, [
            'body' => 'Yes, please submit it before Friday.',
        ])->assertRedirect('/facilitator/messages/'.$student->id);

        $this->actingAs($student)->get('/student/messages/'.$facilitator->id)
            ->assertOk()
            ->assertSee('Yes, please submit it before Friday.');
    }

    public function test_messages_are_restricted_to_students_and_facilitators_in_the_same_section(): void
    {
        [$student, $facilitator, $otherFacilitator] = $this->records();
        $unassignedStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);

        $this->actingAs($student)->post('/student/messages/'.$otherFacilitator->id, [
            'body' => 'This should not be sent.',
        ])->assertNotFound();

        $this->actingAs($facilitator)->post('/facilitator/messages/'.$unassignedStudent->id, [
            'body' => 'This should not be sent.',
        ])->assertNotFound();

        $this->actingAs($coordinator)->get('/student/messages')->assertForbidden();
        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_message_body_is_required_and_limited(): void
    {
        [$student, $facilitator] = $this->records();

        $this->actingAs($student)->post('/student/messages/'.$facilitator->id, ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($student)->post('/student/messages/'.$facilitator->id, ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');
    }

    private function records(): array
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-CHAT-01',
            'name' => 'Chat Section',
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

        return [$student, $facilitator, $otherFacilitator, $section];
    }
}
