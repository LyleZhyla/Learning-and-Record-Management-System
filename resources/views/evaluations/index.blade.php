@extends($layout)

@section('title', 'Evaluations')
@section('page-title', 'Student, Instructor & Community Evaluations')

@section('content')
<section class="welcome-banner"><div><span class="eyebrow">Assessment and evaluation</span><h2>Evaluation workspace</h2><p>Collect structured feedback from students, instructors, and community beneficiaries using a consistent 1–5 rating scale.</p></div></section>

@if(auth()->user()->isStudent())
    <section class="dashboard-grid two-column">
        <form class="card" style="padding:1.25rem" method="POST" action="{{ route($routePrefix.'.evaluations.student-instructor.store') }}">
            @csrf
            <span class="eyebrow">Confidential student response</span><h3>Evaluate your instructor</h3>
            @if($enrollment?->section?->facilitator)
                <p>{{ $enrollment->section->code }} · {{ $enrollment->section->facilitator->name }}</p>
                @foreach($criteria['student_instructor'] as $key => $label)
                    <label class="field-group"><span>{{ $label }}</span><select name="ratings[{{ $key }}]" required><option value="">Choose 1–5</option>@for($score = 5; $score >= 1; $score--)<option value="{{ $score }}" @selected((string)old('ratings.'.$key, $studentInstructorEvaluation?->answers[$key] ?? '') === (string)$score)>{{ $score }} — {{ $score === 5 ? 'Excellent' : ($score === 1 ? 'Needs significant improvement' : '') }}</option>@endfor</select></label>
                @endforeach
                <label class="field-group"><span>Comments or suggestions</span><textarea name="comments" rows="4" maxlength="3000">{{ old('comments', $studentInstructorEvaluation?->comments) }}</textarea></label>
                <button class="primary-button" type="submit">{{ $studentInstructorEvaluation ? 'Update evaluation' : 'Submit evaluation' }}</button>
                <p><small>Your name is not displayed in instructor reports; only aggregate scores and anonymous comments are shown.</small></p>
            @else
                <div class="empty-state"><strong>No assigned instructor</strong><span>Complete section enrollment before submitting an instructor evaluation.</span></div>
            @endif
        </form>
        <article class="card" style="padding:1.25rem"><span class="eyebrow">Instructor feedback</span><h3>Your participation evaluations</h3>@forelse($receivedEvaluations as $evaluation)<div style="padding:1rem 0;border-bottom:1px solid var(--border-color)"><strong>{{ $evaluation->section?->code }} · {{ number_format($evaluation->averageRating(), 2) }}/5</strong><p>{{ $evaluation->comments ?: 'No written feedback provided.' }}</p><small>{{ $evaluation->submitted_at->format('M d, Y') }}</small></div>@empty<div class="empty-state"><strong>No instructor evaluation yet</strong><span>Your facilitator's participation feedback will appear here.</span></div>@endforelse</article>
    </section>
@endif

@if(auth()->user()->isFacilitator())
    <section class="card user-table-card">
        <div class="section-heading"><div><span class="eyebrow">Instructor evaluation form</span><h3>Evaluate student participation</h3><p>Record section participation, teamwork, responsibility, communication, and community engagement.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Section</th><th>Existing score</th><th>Evaluation</th></tr></thead><tbody>
        @forelse($sections as $section) @foreach($section->enrollments as $enrollment)
            @php($saved = $instructorStudentEvaluations->get($section->id.'-'.$enrollment->student_id))
            <tr><td><strong>{{ $enrollment->student->name }}</strong><small class="table-secondary-line">{{ $enrollment->student->email }}</small></td><td>{{ $section->code }}</td><td>{{ $saved ? number_format($saved->averageRating(), 2).'/5' : 'Not evaluated' }}</td><td><details><summary>{{ $saved ? 'Update evaluation' : 'Open form' }}</summary><form method="POST" action="{{ route($routePrefix.'.evaluations.instructor-student.store', [$section, $enrollment->student]) }}" class="stack-form" style="min-width:22rem">@csrf @method('PUT')@foreach($criteria['instructor_student'] as $key => $label)<label class="field-group"><span>{{ $label }}</span><select name="ratings[{{ $key }}]" required><option value="">Choose 1–5</option>@for($score = 5; $score >= 1; $score--)<option value="{{ $score }}" @selected((string)($saved?->answers[$key] ?? '') === (string)$score)>{{ $score }}</option>@endfor</select></label>@endforeach<label class="field-group"><span>Feedback for student</span><textarea name="comments" rows="3" maxlength="3000">{{ $saved?->comments }}</textarea></label><button class="primary-button compact" type="submit">Save evaluation</button></form></details></td></tr>
        @endforeach @empty<tr><td colspan="4"><div class="empty-state"><strong>No assigned sections</strong><span>Student evaluation forms appear after section assignment.</span></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
@endif

@unless(auth()->user()->isStudent())
    <section class="card user-table-card" style="margin-top:1.25rem">
        <div class="section-heading"><div><span class="eyebrow">Anonymous aggregate</span><h3>Student evaluation of instructors</h3><p>Individual student identities are not shown.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Section</th><th>Instructor</th><th>Responses</th><th>Average</th></tr></thead><tbody>@forelse($sectionSummaries as $summary)<tr><td>{{ $summary['section']->code }}<small class="table-secondary-line">{{ $summary['section']->component->code }}</small></td><td>{{ $summary['section']->facilitator?->name ?? 'Unassigned' }}</td><td>{{ $summary['responses'] }}</td><td>{{ $summary['average'] === null ? 'No responses' : number_format($summary['average'], 2).'/5' }}</td></tr>@empty<tr><td colspan="4"><div class="empty-state"><strong>No visible sections</strong></div></td></tr>@endforelse</tbody></table></div>
    </section>

    <section class="card user-table-card" style="margin-top:1.25rem">
        <div class="section-heading"><div><span class="eyebrow">Instructor records</span><h3>Student participation evaluations</h3><p>Recorded instructor feedback for students in the sections within your scope.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Section</th><th>Instructor</th><th>Average</th><th>Feedback</th></tr></thead><tbody>@forelse($instructorStudentResponses as $evaluation)<tr><td>{{ $evaluation->subject?->name ?? 'Former student' }}</td><td>{{ $evaluation->section?->code ?? '—' }}</td><td>{{ $evaluation->evaluator?->name ?? 'Former instructor' }}</td><td>{{ number_format($evaluation->averageRating(), 2) }}/5</td><td>{{ $evaluation->comments ?: 'No written feedback.' }}</td></tr>@empty<tr><td colspan="5"><div class="empty-state"><strong>No instructor evaluations submitted</strong></div></td></tr>@endforelse</tbody></table></div>
    </section>

    <section class="card user-table-card" style="margin-top:1.25rem">
        <div class="section-heading"><div><span class="eyebrow">Community voice</span><h3>Beneficiary feedback surveys</h3><p>Open a public survey and share its protected link with project beneficiaries.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>Responses</th><th>Average</th><th>Survey link</th><th>Status</th></tr></thead><tbody>
        @forelse($communityProjects as $project)
            @php($communityResponses = $project->evaluations)
            <tr><td><strong>{{ $project->title }}</strong><small class="table-secondary-line">{{ $project->reference_number }} · {{ $project->component->code }}</small></td><td>{{ $communityResponses->count() }}@if($communityResponses->isNotEmpty())<details><summary>View feedback</summary><div style="min-width:20rem">@foreach($communityResponses as $response)<p><strong>{{ $response->respondent_name ?: 'Anonymous respondent' }}</strong> · {{ $response->respondent_relationship }} · {{ number_format($response->averageRating(), 2) }}/5<br>{{ $response->comments ?: 'No written comments.' }}</p>@endforeach</div></details>@endif</td><td>{{ $communityResponses->isEmpty() ? 'No responses' : number_format($communityResponses->avg(fn($response) => $response->averageRating()), 2).'/5' }}</td><td><a class="table-action" href="{{ route('community-feedback.create', [$project, $project->feedback_token]) }}" target="_blank" rel="noopener">Open public survey</a></td><td><form method="POST" action="{{ route($routePrefix.'.evaluations.community.toggle', $project) }}">@csrf @method('PUT')<input type="hidden" name="feedback_is_open" value="{{ $project->feedback_is_open ? 0 : 1 }}"><button class="secondary-button compact" type="submit" @disabled($project->approval_status !== 'approved')>{{ $project->feedback_is_open ? 'Close survey' : 'Open survey' }}</button></form></td></tr>
        @empty<tr><td colspan="5"><div class="empty-state"><strong>No community projects available</strong><span>Approved projects will have a beneficiary survey link.</span></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
@endunless
@endsection
