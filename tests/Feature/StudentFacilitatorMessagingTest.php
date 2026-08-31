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

        $this->actingAs($facilitator)->get('/facilitator/dashboard')
            ->assertOk()
            ->assertSee('New message from '.$student->name)
            ->assertSee('Good afternoon, may I ask about our activity?')
            ->assertSee('/facilitator/messages/'.$student->id, false)
            ->assertSee('data-unread-message-count="1"', false)
            ->assertSee('1 unread');

        $this->actingAs($facilitator)->get('/facilitator/messages/'.$student->id)
            ->assertOk()
            ->assertSee('Good afternoon, may I ask about our activity?');

        $this->assertNotNull($message->fresh()->read_at);

        $this->actingAs($facilitator)->get('/facilitator/dashboard')
            ->assertOk()
            ->assertDontSee('New message from '.$student->name)
            ->assertDontSee('data-unread-message-count', false)
            ->assertSee('0 unread');

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

    public function test_facilitator_contact_list_only_shows_conversations_with_latest_first(): void
    {
        [$student, $facilitator, , $section] = $this->records();
        $quietStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $latestStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);

        foreach ([$quietStudent, $latestStudent] as $enrolledStudent) {
            NstpEnrollment::create([
                'student_id' => $enrolledStudent->id,
                'component_id' => $section->component_id,
                'section_id' => $section->id,
                'academic_year' => $section->academic_year,
                'semester' => $section->semester,
                'status' => 'enrolled',
            ]);
        }

        $this->actingAs($facilitator)->get('/facilitator/messages')
            ->assertOk()
            ->assertSee('A student will appear here after starting a conversation with you.')
            ->assertDontSee($student->name)
            ->assertDontSee($quietStudent->name)
            ->assertDontSee($latestStudent->name);

        ChatMessage::create([
            'section_id' => $section->id,
            'sender_id' => $student->id,
            'recipient_id' => $facilitator->id,
            'body' => 'This is the older conversation.',
        ]);
        ChatMessage::create([
            'section_id' => $section->id,
            'sender_id' => $latestStudent->id,
            'recipient_id' => $facilitator->id,
            'body' => 'This is the latest conversation.',
        ]);

        $this->actingAs($facilitator)->get('/facilitator/messages')
            ->assertOk()
            ->assertSeeTextInOrder([$latestStudent->name, $student->name])
            ->assertSee('This is the latest conversation.')
            ->assertDontSee($quietStudent->name);
    }

    public function test_message_body_is_required_and_limited(): void
    {
        [$student, $facilitator] = $this->records();

        $this->actingAs($student)->post('/student/messages/'.$facilitator->id, ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($student)->post('/student/messages/'.$facilitator->id, ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');
    }

    public function test_mark_all_notifications_as_read_also_clears_unread_messages(): void
    {
        [$student, $facilitator, , $section] = $this->records();
        $message = ChatMessage::create([
            'section_id' => $section->id,
            'sender_id' => $student->id,
            'recipient_id' => $facilitator->id,
            'body' => 'Please check this message.',
        ]);

        $this->actingAs($facilitator)->post('/notifications/read-all')->assertRedirect();

        $this->assertNotNull($message->fresh()->read_at);
        $this->actingAs($facilitator)->get('/facilitator/dashboard')
            ->assertOk()
            ->assertSee('0 unread')
            ->assertDontSee('data-unread-message-count', false);
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
