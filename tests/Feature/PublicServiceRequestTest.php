<?php

namespace Tests\Feature;

use App\Models\NstpServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_request_page_is_available_from_the_landing_page(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Records &amp; assistance requests', false)
            ->assertSee(route('service-requests.create'), false);

        $this->get(route('service-requests.create'))
            ->assertOk()
            ->assertSee('How can the')
            ->assertSee('Honor Guard')
            ->assertSee('Certificate of Completion');
    }

    public function test_visitor_can_submit_a_record_request_and_receive_a_reference_number(): void
    {
        $response = $this->post(route('service-requests.store'), [
            'request_type' => 'certificate_of_completion',
            'requester_name' => 'Juan Dela Cruz',
            'email' => 'juan@example.test',
            'contact_number' => '09171234567',
            'student_number' => '2020-00123',
            'program' => 'BS Agriculture',
            'graduation_year' => 2024,
            'purpose' => 'Employment requirement.',
            'privacy_consent' => '1',
        ]);

        $serviceRequest = NstpServiceRequest::sole();

        $response->assertRedirect(route('service-requests.show', $serviceRequest));
        $this->assertStringStartsWith('NSTP-', $serviceRequest->reference_code);
        $this->assertSame('submitted', $serviceRequest->status);

        $this->get(route('service-requests.show', $serviceRequest))
            ->assertOk()
            ->assertSee($serviceRequest->reference_code)
            ->assertSee('Certificate of Completion');
    }

    public function test_assistance_request_requires_event_details(): void
    {
        $this->from(route('service-requests.create'))
            ->post(route('service-requests.store'), [
                'request_type' => 'assistance',
                'requester_name' => 'Requesting Officer',
                'email' => 'officer@example.test',
                'contact_number' => '09170000000',
                'purpose' => 'University ceremony support.',
                'privacy_consent' => '1',
            ])
            ->assertRedirect(route('service-requests.create'))
            ->assertSessionHasErrors(['assistance_type', 'event_name', 'event_date', 'event_location']);

        $this->assertDatabaseCount('nstp_service_requests', 0);
    }

    public function test_visitor_can_attach_a_supporting_document_to_an_assistance_request(): void
    {
        Storage::fake('local');

        $this->post(route('service-requests.store'), [
            'request_type' => 'assistance',
            'assistance_type' => 'honor_guard',
            'requester_name' => 'TAU Events Office',
            'email' => 'events@example.test',
            'contact_number' => '045-000-0000',
            'program' => 'Events Office',
            'purpose' => 'Honor Guard support for the recognition ceremony.',
            'event_name' => 'Recognition Ceremony',
            'event_date' => now()->addWeek()->toDateString(),
            'event_location' => 'TAU Gilberto O. Teodoro Multipurpose Center',
            'expected_participants' => 500,
            'attachment' => UploadedFile::fake()->create('invitation.pdf', 300, 'application/pdf'),
            'privacy_consent' => '1',
        ])->assertRedirect();

        $serviceRequest = NstpServiceRequest::sole();
        $this->assertSame('honor_guard', $serviceRequest->assistance_type);
        $this->assertSame('invitation.pdf', $serviceRequest->attachment_original_name);
        Storage::disk('local')->assertExists($serviceRequest->attachment_path);
    }
}
