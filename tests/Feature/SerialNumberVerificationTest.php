<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\NstpSerialNumberRelease;
use App\Models\NstpStudentSerialNumber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SerialNumberVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_uploads_official_file_and_encodes_serial_for_qualified_graduate(): void
    {
        Storage::fake('local');
        [$coordinator, $facilitator, $section, $graduate] = $this->programData();
        $enrollment = $this->enroll($graduate, $section);
        $this->grade($graduate, $section, $facilitator, 90);

        $this->actingAs($coordinator)->post('/coordinator/serial-numbers', [
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'received_at' => '2026-10-10',
            'source_file' => UploadedFile::fake()->create('ched-serial-list.pdf', 100, 'application/pdf'),
            'notes' => 'Official list received by the NSTP office.',
        ])->assertRedirect();

        $release = NstpSerialNumberRelease::firstOrFail();
        Storage::disk('local')->assertExists($release->source_file_path);
        $this->assertSame($coordinator->nstp_component_id, $release->component_id);

        $this->actingAs($coordinator)->get('/coordinator/serial-numbers/'.$release->id)
            ->assertOk()->assertSee($graduate->name)->assertSee('ched-serial-list.pdf');

        $this->actingAs($coordinator)->put('/coordinator/serial-numbers/'.$release->id.'/students/'.$enrollment->id, [
            'serial_number' => 'nstp-03-2026-00125',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_student_serial_numbers', [
            'release_id' => $release->id,
            'enrollment_id' => $enrollment->id,
            'serial_number' => 'NSTP-03-2026-00125',
            'encoded_by' => $coordinator->id,
        ]);
    }

    public function test_public_verifier_returns_only_an_exact_recorded_serial_number(): void
    {
        Storage::fake('local');
        [$coordinator, $facilitator, $section, $graduate] = $this->programData();
        $enrollment = $this->enroll($graduate, $section);
        $release = $this->release($coordinator, $section);
        NstpStudentSerialNumber::create([
            'release_id' => $release->id,
            'enrollment_id' => $enrollment->id,
            'student_id' => $graduate->id,
            'serial_number' => 'NSTP-03-2026-00999',
            'encoded_by' => $coordinator->id,
        ]);

        $this->get('/verify/serial?serial_number=nstp-03-2026-00999')
            ->assertOk()->assertSee('Serial number is valid')->assertSee($graduate->name)->assertSee('CWTS');
        $this->get('/verify/serial?serial_number=NSTP-03-2026-00000')
            ->assertOk()->assertSee('No matching official record')->assertDontSee($graduate->name);
    }

    public function test_super_admin_and_nstp_admin_can_upload_files_and_encode_serial_numbers(): void
    {
        Storage::fake('local');
        [$coordinator, $facilitator, $section, $graduate] = $this->programData();
        $enrollment = $this->enroll($graduate, $section);
        $this->grade($graduate, $section, $facilitator, 90);

        foreach (['super_admin' => 'admin', 'nstp_admin' => 'nstp-admin'] as $role => $prefix) {
            $manager = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($manager)->get("/{$prefix}/serial-numbers")
                ->assertOk()
                ->assertSee('Upload the official serial-number file')
                ->assertSee('Select component');

            $this->actingAs($manager)->post("/{$prefix}/serial-numbers", [
                'component_id' => $section->component_id,
                'academic_year' => '2026-2027',
                'semester' => 'first',
                'received_at' => '2026-10-10',
                'source_file' => UploadedFile::fake()->create("{$role}-serial-list.pdf", 100, 'application/pdf'),
            ])->assertRedirect();

            $release = NstpSerialNumberRelease::where('uploaded_by', $manager->id)->firstOrFail();
            $this->actingAs($manager)->get("/{$prefix}/serial-numbers/{$release->id}")
                ->assertOk()
                ->assertSee($graduate->name);
            $this->actingAs($manager)->get("/{$prefix}/serial-numbers/{$release->id}/source-file")
                ->assertOk();

            $serialNumber = $role === 'super_admin' ? 'NSTP-ADMIN-2026-001' : 'NSTP-OFFICE-2026-001';
            $this->actingAs($manager)->put("/{$prefix}/serial-numbers/{$release->id}/students/{$enrollment->id}", [
                'serial_number' => $serialNumber,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('nstp_student_serial_numbers', [
                'release_id' => $release->id,
                'enrollment_id' => $enrollment->id,
                'serial_number' => $serialNumber,
                'encoded_by' => $manager->id,
            ]);
        }
    }

    public function test_unqualified_student_and_other_component_coordinator_cannot_encode_serial(): void
    {
        Storage::fake('local');
        [$coordinator, $facilitator, $section, $student] = $this->programData();
        $enrollment = $this->enroll($student, $section);
        $this->grade($student, $section, $facilitator, 50);
        $release = $this->release($coordinator, $section);

        $this->actingAs($coordinator)->put('/coordinator/serial-numbers/'.$release->id.'/students/'.$enrollment->id, [
            'serial_number' => 'NSTP-FAILED-001',
        ])->assertStatus(422);

        $otherComponent = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'is_active' => true]);
        $otherCoordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $otherComponent->id]);
        $this->actingAs($otherCoordinator)->get('/coordinator/serial-numbers/'.$release->id)->assertForbidden();
        $this->actingAs($otherCoordinator)->get('/coordinator/serial-numbers/'.$release->id.'/source-file')->assertForbidden();
    }

    private function programData(): array
    {
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['name' => 'Qualified Graduate', 'role' => 'student', 'status' => 'active']);
        $section = NstpSection::create([
            'component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01',
            'name' => 'CWTS Section 1', 'academic_year' => '2026-2027', 'semester' => 'first',
            'capacity' => 40, 'status' => 'active',
        ]);

        return [$coordinator, $facilitator, $section, $student];
    }

    private function enroll(User $student, NstpSection $section): NstpEnrollment
    {
        return NstpEnrollment::create([
            'student_id' => $student->id, 'component_id' => $section->component_id, 'section_id' => $section->id,
            'academic_year' => $section->academic_year, 'semester' => $section->semester, 'status' => 'enrolled',
        ]);
    }

    private function grade(User $student, NstpSection $section, User $facilitator, int $score): void
    {
        foreach (['activity', 'project', 'exam', 'quiz'] as $type) {
            $assessment = Assessment::create([
                'section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => str($type)->headline().' Requirement',
                'type' => $type, 'max_score' => 100, 'weight' => 25, 'status' => 'published', 'published_at' => now(),
            ]);
            AssessmentSubmission::create([
                'assessment_id' => $assessment->id, 'student_id' => $student->id, 'answer_text' => 'Complete',
                'submitted_at' => now(), 'score' => $score, 'graded_by' => $facilitator->id, 'graded_at' => now(),
            ]);
        }
    }

    private function release(User $coordinator, NstpSection $section): NstpSerialNumberRelease
    {
        Storage::disk('local')->put('nstp-serial-number-releases/source.pdf', 'official-list');

        return NstpSerialNumberRelease::create([
            'component_id' => $section->component_id, 'academic_year' => $section->academic_year,
            'semester' => $section->semester, 'received_at' => '2026-10-10',
            'source_file_path' => 'nstp-serial-number-releases/source.pdf',
            'source_file_original_name' => 'source.pdf', 'source_file_mime_type' => 'application/pdf',
            'uploaded_by' => $coordinator->id,
        ]);
    }
}
