@extends($layout)
@section('title', $sheet->assessment->title.' Scanner')
@section('page-title', 'Answer Sheet Scanner')

@section('content')
<div class="back-row"><a href="{{ route($routePrefix.'.omr.index') }}">← Back to answer sheets</a></div>
<section class="card omr-session-header">
    <div><span class="eyebrow">{{ $sheet->assessment->section->component->code }} · {{ $sheet->assessment->section->code }}</span><h2>{{ $sheet->assessment->title }}</h2><p>{{ $sheet->item_count }} items · Choices A–{{ chr(64 + $sheet->choice_count) }} · {{ number_format($sheet->assessment->max_score,2) }} maximum points</p></div>
    <a class="secondary-outline-button" href="{{ route($routePrefix.'.omr.print',$sheet) }}" target="_blank">Print blank answer sheet</a>
</section>

<div class="omr-scanner-layout">
    <section class="card omr-camera-card" data-omr-scanner data-endpoint="{{ route($routePrefix.'.omr.grade',$sheet) }}" data-items="{{ $sheet->item_count }}" data-choices="{{ $sheet->choice_count }}">
        <div class="card-heading"><div><span class="eyebrow">Live paper scanner</span><h3>Align and capture</h3><p>Keep all four black corner markers inside the guide.</p></div></div>
        <label class="field-group"><span>Student</span><select data-omr-student required><option value="">Select the student before scanning</option>@foreach($students as $enrollment)<option value="{{ $enrollment->student_id }}">{{ $enrollment->student->name }}</option>@endforeach</select></label>
        <div class="omr-camera-viewport">
            <video data-omr-video playsinline muted></video>
            <canvas data-omr-canvas></canvas>
            <div class="omr-paper-guide"><i></i><i></i><i></i><i></i><span>ALIGN ANSWER SHEET HERE</span></div>
            <div class="scanner-placeholder" data-omr-placeholder><span>▦</span><strong>Camera is off</strong><small>Open the camera or upload a clear answer-sheet photo.</small></div>
        </div>
        <div class="omr-camera-actions"><button class="primary-button" type="button" data-omr-camera>Open camera</button><button class="filter-button" type="button" data-omr-capture disabled>Capture & read</button><button class="secondary-outline-button" type="button" data-omr-manual>Enter manually</button></div>
        <label class="omr-upload-button">Upload answer-sheet photo<input type="file" accept="image/*" capture="environment" data-omr-upload></label>
        <p class="scanner-message" data-omr-message role="status">Select a student, then open the camera.</p>

        <form class="omr-review" data-omr-review hidden>
            <div class="answer-key-heading"><strong>Review detected answers</strong><small>Correct unclear or blank answers before saving.</small></div>
            <div class="omr-detected-grid" data-omr-answers></div>
            <button class="primary-button" type="submit">Save score to grades <span>→</span></button>
        </form>
    </section>

    <section class="card user-table-card omr-results-card">
        <div class="sectioning-toolbar"><div><h3>Scan results</h3><p class="muted-cell">The latest scan per student is used as the assessment score.</p></div><span class="pill">{{ $sheet->results->count() }} checked</span></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Student</th><th>Correct</th><th>Blank</th><th>Score</th><th>Confidence</th><th>Scanned by</th></tr></thead><tbody>
            @forelse($sheet->results->sortBy(fn($result) => $result->student->name) as $result)
                <tr><td><strong>{{ $result->student->name }}</strong><br><small class="muted-cell">{{ $result->student->email }}</small></td><td>{{ $result->correct_count }} / {{ $sheet->item_count }}</td><td>{{ $result->blank_count }}</td><td><strong class="grade-number">{{ number_format($result->score,2) }}</strong> / {{ number_format($sheet->assessment->max_score,2) }}</td><td>{{ $result->confidence === null ? 'Manual review' : number_format($result->confidence,1).'%' }}</td><td>{{ $result->scanner->name }}<br><small class="muted-cell">{{ $result->updated_at->format('M d, g:i A') }}</small></td></tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>No papers scanned yet</strong><span>Scanned scores will appear here.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>

<script src="{{ asset('js/omr-scanner.js') }}?v={{ filemtime(public_path('js/omr-scanner.js')) }}"></script>
@endsection
