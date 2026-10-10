<?php

namespace Tests\Feature;

use App\Models\CommunityProject;
use App\Models\CommunityProjectActivity;
use App\Models\CommunityProjectDocument;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectDocumentationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilitator_can_upload_activity_linked_accomplishment_documentation(): void
    {
        Storage::fake('local');
        [$project, $student, $facilitator, $activity] = $this->projectWorkspace();

        $this->actingAs($facilitator)->post('/facilitator/community-projects/'.$project->id.'/documents', [
            'category' => 'photo',
            'title' => 'Community literacy session photos',
            'description' => 'Photos showing the completed reading activities.',
            'community_project_activity_id' => $activity->id,
            'file' => UploadedFile::fake()->image('literacy-session.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $document = CommunityProjectDocument::firstOrFail();
        $this->assertSame($project->id, $document->community_project_id);
        $this->assertSame($activity->id, $document->community_project_activity_id);
        $this->assertSame('photo', $document->category);
        $this->assertSame($facilitator->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($student)
            ->get('/student/community-projects/'.$project->id.'/documents/'.$document->id)
            ->assertOk()
            ->assertDownload('literacy-session.jpg');
    }

    public function test_project_student_can_upload_supporting_file_but_outsider_cannot_access_it(): void
    {
        Storage::fake('local');
        [$project, $student] = $this->projectWorkspace();

        $this->actingAs($student)->post('/student/community-projects/'.$project->id.'/documents', [
            'category' => 'supporting',
            'title' => 'Beneficiary attendance list',
            'file' => UploadedFile::fake()->create('attendance.xlsx', 40, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $document = CommunityProjectDocument::firstOrFail();
        $outsider = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($outsider)
            ->get('/student/community-projects/'.$project->id.'/documents/'.$document->id)
            ->assertForbidden();
    }

    public function test_project_manager_can_remove_documentation_and_private_file(): void
    {
        Storage::fake('local');
        [$project, $student, $facilitator] = $this->projectWorkspace();
        $path = UploadedFile::fake()->create('certificate.pdf', 25, 'application/pdf')
            ->store('community-project-documents/'.$project->id, 'local');
        $document = $project->documents()->create([
            'category' => 'certificate',
            'title' => 'Partner recognition certificate',
            'file_path' => $path,
            'original_name' => 'certificate.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 25 * 1024,
            'uploaded_by' => $student->id,
        ]);

        $this->actingAs($facilitator)
            ->delete('/facilitator/community-projects/'.$project->id.'/documents/'.$document->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('community_project_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);
    }

    private function projectWorkspace(): array
    {
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'CWTS', 'is_active' => true]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-DOC-01',
            'name' => 'CWTS Documentation Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
        $project = CommunityProject::create([
            'reference_number' => 'CP-2026-DOC-01',
            'title' => 'Community Literacy Documentation Project',
            'component_id' => $component->id,
            'section_id' => $section->id,
            'proposed_by' => $student->id,
            'description' => 'Community literacy activities.',
            'objectives' => 'Document participation and outcomes.',
            'beneficiaries' => 'Barangay learners',
            'beneficiary_count' => 30,
            'location' => 'Barangay Learning Center',
            'budget' => 5000,
            'approval_status' => 'approved',
            'implementation_status' => 'ongoing',
        ]);
        $activity = CommunityProjectActivity::create([
            'community_project_id' => $project->id,
            'title' => 'Reading session',
            'status' => 'completed',
            'created_by' => $facilitator->id,
            'updated_by' => $facilitator->id,
        ]);

        return [$project, $student, $facilitator, $activity];
    }
}
