<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AcademicTermSnapshot;
use App\Models\AcademicTermTransition;
use App\Models\CompletionClearance;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\TermGradeApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicTermLifecycleService
{
    public function __construct(private GradeService $grades) {}

    public function forSection(NstpSection $section): ?AcademicTerm
    {
        return AcademicTerm::where('academic_year', $section->academic_year)->where('semester', $section->semester)->first();
    }

    public function assertRecordsWritable(NstpSection $section): void
    {
        $term = $this->forSection($section);
        if ($term && in_array($term->status, ['finalized', 'archived'], true)) {
            throw ValidationException::withMessages(['term' => 'This academic term is '.$term->status.'. Attendance and assessment records are locked.']);
        }
    }

    public function assertGradeSubmissionOpen(NstpSection $section): void
    {
        $this->assertRecordsWritable($section);
        $term = $this->forSection($section);
        if ($term?->status === 'grading' && $term->grade_submission_deadline?->isPast()) {
            throw ValidationException::withMessages(['term' => 'The grade submission deadline has passed. An administrator must reopen or extend the term with an audited reason.']);
        }
    }

    public function approveSection(AcademicTerm $term, NstpSection $section, User $actor, ?string $remarks): TermGradeApproval
    {
        abort_unless($actor->isCoordinator(), 403, 'Final grades require approval by the assigned component coordinator.');
        abort_unless($section->academic_year === $term->academic_year && $section->semester === $term->semester, 422);
        abort_unless($term->status === 'grading', 422, 'Final grades can only be approved during the grading stage.');
        abort_unless((int) $actor->nstp_component_id === (int) $section->component_id, 403);
        foreach ($section->enrollments()->with('student')->where('status', 'enrolled')->get() as $enrollment) {
            $summary = $this->grades->summary($enrollment->student, $section->id);
            if ($summary['total_count'] === 0 || $summary['graded_count'] !== $summary['total_count']) {
                throw ValidationException::withMessages(['grades' => $enrollment->student->name.' still has incomplete grade entries.']);
            }
        }

        return TermGradeApproval::updateOrCreate(
            ['academic_term_id' => $term->id, 'section_id' => $section->id],
            ['approved_by' => $actor->id, 'remarks' => $remarks, 'approved_at' => now()]
        );
    }

    public function transition(AcademicTerm $term, string $next, User $actor, ?string $reason = null): AcademicTerm
    {
        $allowed = ['open' => ['active'], 'active' => ['grading'], 'grading' => ['finalized'], 'finalized' => ['archived'], 'archived' => []];
        abort_unless(in_array($next, $allowed[$term->status] ?? [], true), 422, 'Invalid academic-term transition.');

        return DB::transaction(function () use ($term, $next, $actor, $reason): AcademicTerm {
            $from = $term->status;
            $updates = ['status' => $next];
            if ($next === 'finalized') {
                $this->finalize($term, $actor);
                $updates += ['finalized_by' => $actor->id, 'finalized_at' => now()];
            }
            if ($next === 'archived') {
                $updates += ['archived_by' => $actor->id, 'archived_at' => now(), 'is_current' => false];
            }
            $term->update($updates);
            AcademicTermTransition::create(['academic_term_id' => $term->id, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $next, 'reason' => $reason]);

            return $term->refresh();
        });
    }

    public function reopen(AcademicTerm $term, User $actor, string $reason, $deadline): AcademicTerm
    {
        abort_unless(in_array($term->status, ['finalized', 'archived'], true), 422, 'Only finalized or archived terms can be reopened.');

        return DB::transaction(function () use ($term, $actor, $reason, $deadline): AcademicTerm {
            $from = $term->status;
            $term->update(['status' => 'grading', 'grade_submission_deadline' => $deadline, 'finalized_by' => null, 'finalized_at' => null, 'archived_by' => null, 'archived_at' => null, 'is_current' => true]);
            TermGradeApproval::where('academic_term_id', $term->id)->delete();
            AcademicTermTransition::create(['academic_term_id' => $term->id, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => 'grading', 'reason' => $reason]);

            return $term->refresh();
        });
    }

    private function finalize(AcademicTerm $term, User $actor): void
    {
        abort_unless($term->grade_submission_deadline, 422, 'Set a grade submission deadline before finalization.');
        $sections = NstpSection::with(['component', 'enrollments.student.studentProfile'])
            ->where('academic_year', $term->academic_year)->where('semester', $term->semester)->get();
        abort_if($sections->isEmpty(), 422, 'The term has no sections to finalize.');
        $approvedIds = TermGradeApproval::where('academic_term_id', $term->id)->pluck('section_id');
        abort_if($sections->pluck('id')->diff($approvedIds)->isNotEmpty(), 422, 'Every section requires coordinator approval before finalization.');

        $rows = [];
        foreach ($sections as $section) {
            foreach ($section->enrollments->where('status', 'enrolled') as $enrollment) {
                $summary = $this->grades->summary($enrollment->student, $section->id);
                $cleared = $summary['total_count'] > 0 && $summary['graded_count'] === $summary['total_count']
                    && $summary['percentage'] !== null && $summary['percentage'] >= (float) $summary['settings']->passing_percentage;
                CompletionClearance::updateOrCreate(
                    ['academic_term_id' => $term->id, 'enrollment_id' => $enrollment->id],
                    ['student_id' => $enrollment->student_id, 'section_id' => $section->id, 'status' => $cleared ? 'cleared' : 'incomplete', 'final_percentage' => $summary['percentage'], 'remarks' => $cleared ? 'Final grades approved; completion requirements satisfied.' : 'Incomplete or below the passing requirement.', 'cleared_by' => $cleared ? $actor->id : null, 'cleared_at' => $cleared ? now() : null]
                );
                $rows[] = $this->snapshotRow($enrollment, $section, $summary, $cleared);
            }
        }
        $version = $term->snapshot_version + 1;
        $payload = ['academic_year' => $term->academic_year, 'semester' => $term->semester, 'finalized_at' => now()->toIso8601String(), 'rows' => $rows];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        AcademicTermSnapshot::create(['academic_term_id' => $term->id, 'version' => $version, 'payload' => $payload, 'checksum' => hash('sha256', $json), 'created_by' => $actor->id]);
        $term->update(['snapshot_version' => $version]);
    }

    private function snapshotRow(NstpEnrollment $enrollment, NstpSection $section, array $summary, bool $cleared): array
    {
        $student = $enrollment->student;
        $profile = $student->studentProfile;

        return [
            'enrollment_id' => $enrollment->id, 'student_id' => $student->id, 'component_id' => $section->component_id,
            'academic_year' => $enrollment->academic_year, 'semester' => $enrollment->semester,
            'component' => $section->component->code, 'section_id' => $section->id, 'section' => $section->name,
            'final_percentage' => $summary['percentage'], 'clearance_status' => $cleared ? 'cleared' : 'incomplete',
            'student' => ['name' => $student->name, 'email' => $student->email, 'last_name' => $profile?->last_name, 'first_name' => $profile?->first_name, 'middle_name' => $profile?->middle_name, 'extension_name' => $profile?->extension_name, 'date_of_birth' => $profile?->date_of_birth?->format('Y-m-d'), 'sex' => $profile?->sex, 'barangay' => $profile?->barangay, 'city_municipality' => $profile?->city_municipality, 'province' => $profile?->province, 'course' => $profile?->course, 'year_section' => $profile?->year_section, 'contact_number' => $profile?->contact_number],
        ];
    }
}
