<?php

namespace Tests\Feature;

use App\Models\FacilitatorHonorarium;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilitatorHonorariumWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_create_payment_request_only_for_assigned_component(): void
    {
        [$component, $facilitator] = $this->facilitatorWorkspace('CWTS');
        [$otherComponent, $otherFacilitator] = $this->facilitatorWorkspace('LTS');
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'status' => 'active',
            'nstp_component_id' => $component->id,
        ]);

        $this->actingAs($coordinator)->post('/coordinator/honoraria', $this->requestData($facilitator, $otherComponent))
            ->assertRedirect()->assertSessionHasNoErrors();

        $record = FacilitatorHonorarium::firstOrFail();
        $this->assertSame($component->id, $record->component_id);
        $this->assertSame($facilitator->id, $record->facilitator_id);
        $this->assertSame('for_document_preparation', $record->status);
        $this->assertSame('11500.00', $record->net_amount);
        $this->assertSame('HON-'.now()->format('Y').'-00001', $record->reference_number);

        $this->actingAs($coordinator)->post('/coordinator/honoraria', $this->requestData($otherFacilitator, $otherComponent))
            ->assertStatus(422);
    }

    public function test_nstp_admin_prepares_voucher_and_forwards_documents_without_approving_or_disbursing(): void
    {
        [$component, $facilitator] = $this->facilitatorWorkspace('ROTC');
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $record = $this->honorarium($component, $facilitator, $coordinator);

        $this->actingAs($coordinator)->put('/coordinator/honoraria/'.$record->id.'/documents', [
            'status' => 'documents_prepared',
        ])->assertNotFound();
        $this->actingAs($admin)->put('/nstp-admin/honoraria/'.$record->id.'/review', [
            'decision' => 'approved',
        ])->assertNotFound();
        $this->actingAs($admin)->put('/nstp-admin/honoraria/'.$record->id.'/disburse', [
            'disbursement_reference' => 'NOT-ALLOWED',
        ])->assertNotFound();

        $this->actingAs($admin)->put('/nstp-admin/honoraria/'.$record->id.'/documents', [
            'status' => 'documents_prepared',
            'disbursement_voucher_number' => 'DV-2026-00123',
            'obligation_request_number' => 'ORS-2026-00456',
            'payroll_reference' => 'PAYROLL-2026-08',
            'preparation_notes' => 'Supporting documents completed for cashier review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('documents_prepared', $record->status);
        $this->assertSame('DV-2026-00123', $record->disbursement_voucher_number);
        $this->assertSame($admin->id, $record->prepared_by);

        $this->actingAs($admin)->get('/nstp-admin/honoraria/'.$record->id.'/voucher')
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertNotNull($record->fresh()->voucher_generated_at);

        $this->actingAs($admin)->put('/nstp-admin/honoraria/'.$record->id.'/documents', [
            'status' => 'forwarded_to_cashier',
            'disbursement_voucher_number' => 'DV-2026-00123',
            'obligation_request_number' => 'ORS-2026-00456',
            'payroll_reference' => 'PAYROLL-2026-08',
            'cashier_forwarded_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('forwarded_to_cashier', $record->status);
        $this->assertNotNull($record->cashier_forwarded_at);
    }

    public function test_facilitator_sees_only_own_read_only_status_without_financial_details(): void
    {
        [$component, $facilitator] = $this->facilitatorWorkspace('CWTS');
        [$otherComponent, $otherFacilitator] = $this->facilitatorWorkspace('LTS');
        $requester = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $own = $this->honorarium($component, $facilitator, $requester, 'forwarded_to_cashier');
        $own->update(['disbursement_voucher_number' => 'PRIVATE-VOUCHER-123', 'cashier_forwarded_at' => now()]);
        $other = $this->honorarium($otherComponent, $otherFacilitator, $requester);

        $this->actingAs($facilitator)->get('/facilitator/honoraria')
            ->assertOk()
            ->assertSee($own->reference_number)
            ->assertSee('Forwarded to University Cashier')
            ->assertDontSee($other->reference_number)
            ->assertDontSee('12,000.00')
            ->assertDontSee('PRIVATE-VOUCHER-123')
            ->assertDontSee('Download cashier voucher');
        $this->actingAs($facilitator)->get('/facilitator/honoraria/create')->assertNotFound();
        $this->actingAs($facilitator)->get('/facilitator/honoraria/'.$own->id.'/voucher')->assertNotFound();
    }

    private function facilitatorWorkspace(string $code): array
    {
        $component = NstpComponent::create(['code' => $code, 'name' => $code, 'is_active' => true]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => $code.'-HON-'.uniqid(),
            'name' => $code.' Honorarium Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);

        return [$component, $facilitator];
    }

    private function requestData(User $facilitator, NstpComponent $component): array
    {
        return [
            'facilitator_id' => $facilitator->id,
            'component_id' => $component->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'period_start' => '2026-08-01',
            'period_end' => '2026-10-31',
            'gross_amount' => 12000,
            'deductions' => 500,
            'request_notes' => 'First-term NSTP facilitation honorarium.',
        ];
    }

    private function honorarium(NstpComponent $component, User $facilitator, User $requester, string $status = 'for_document_preparation'): FacilitatorHonorarium
    {
        return FacilitatorHonorarium::create([
            'reference_number' => 'HON-2026-'.str_pad((string) (FacilitatorHonorarium::count() + 1), 5, '0', STR_PAD_LEFT),
            'facilitator_id' => $facilitator->id,
            'component_id' => $component->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'period_start' => '2026-08-01',
            'period_end' => '2026-10-31',
            'gross_amount' => 12000,
            'deductions' => 500,
            'net_amount' => 11500,
            'status' => $status,
            'request_notes' => 'Internal financial details.',
            'requested_by' => $requester->id,
            'requested_at' => now(),
        ]);
    }
}
