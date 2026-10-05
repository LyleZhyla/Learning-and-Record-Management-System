<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_nstp_admin_coordinator_and_facilitator_can_message_each_other(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        foreach ([
            [$superAdmin, 'admin', $nstpAdmin, [$coordinator, $facilitator]],
            [$nstpAdmin, 'nstp-admin', $superAdmin, [$coordinator, $facilitator]],
            [$coordinator, 'coordinator', $superAdmin, [$nstpAdmin, $facilitator]],
            [$facilitator, 'facilitator', $superAdmin, [$nstpAdmin, $coordinator]],
        ] as [$sender, $prefix, $firstContact, $otherContacts]) {
            $this->actingAs($sender)->get('/'.$prefix.'/messages')
                ->assertOk()
                ->assertSee($sender->isFacilitator() ? 'Team and student messaging' : 'Administrative staff chat')
                ->assertSee($firstContact->name)
                ->assertSee($otherContacts[0]->name)
                ->assertSee($otherContacts[1]->name);

            $this->actingAs($sender)->post('/'.$prefix.'/messages/'.$firstContact->id, [
                'body' => 'Private staff message from '.$sender->role,
            ])->assertRedirect('/'.$prefix.'/messages/'.$firstContact->id);
        }

        $this->assertDatabaseCount('chat_messages', 4);
        $this->assertSame(4, ChatMessage::whereNull('section_id')->count());
    }

    public function test_staff_messages_are_private_and_exclude_students_but_include_facilitators(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($superAdmin)->post('/admin/messages/'.$student->id, ['body' => 'Not allowed'])
            ->assertNotFound();
        $this->actingAs($nstpAdmin)->post('/nstp-admin/messages/'.$facilitator->id, ['body' => 'Allowed staff message'])
            ->assertRedirect('/nstp-admin/messages/'.$facilitator->id);

        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertDatabaseHas('chat_messages', [
            'sender_id' => $nstpAdmin->id,
            'recipient_id' => $facilitator->id,
            'section_id' => null,
        ]);
    }

    public function test_staff_message_appears_in_recipient_notifications_and_can_be_read(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);

        $this->actingAs($superAdmin)->post('/admin/messages/'.$coordinator->id, [
            'body' => 'Please review the NSTP schedule.',
        ])->assertRedirect('/admin/messages/'.$coordinator->id);

        $message = ChatMessage::firstOrFail();
        $this->assertNull($message->section_id);
        $this->assertNull($message->read_at);

        $this->actingAs($coordinator)->get('/coordinator/dashboard')
            ->assertOk()
            ->assertSee('New message from '.$superAdmin->name)
            ->assertSee('Please review the NSTP schedule.')
            ->assertSee('/coordinator/messages/'.$superAdmin->id, false);

        $this->actingAs($coordinator)->get('/coordinator/messages/'.$superAdmin->id)
            ->assertOk()
            ->assertSee('Please review the NSTP schedule.');

        $this->assertNotNull($message->fresh()->read_at);
    }
}
