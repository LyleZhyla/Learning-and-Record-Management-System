@extends($layout)
@section('title', 'Answer Sheet Scanner')
@section('page-title', 'Answer Sheet Scanner')

@section('content')
<section class="omr-hero">
    <div>
        <span class="eyebrow">Camera-based paper checking</span>
        <h2>Check bubble answer sheets in seconds.</h2>
        <p>Create an answer key for an existing assessment, print the standardized SNAPIE sheet, then scan completed papers with a phone or laptop camera.</p>
    </div>
    <div class="omr-hero-steps"><span>1</span> Set key <i>→</i><span>2</span> Print <i>→</i><span>3</span> Scan</div>
</section>

<div class="omr-index-layout">
    <section class="card omr-key-card">
        <div class="card-heading"><div><span class="eyebrow">New scanner setup</span><h3>Create answer key</h3><p>Supports 1–30 questions and 2–5 choices.</p></div><a class="secondary-outline-button" href="{{ route($routePrefix.'.assessments.create') }}">Create assessment + sheet</a></div>
        @if($assessments->isEmpty())
            <div class="empty-state"><strong>No available assessments</strong><span>Create an assessment first, or open an existing scanner below.</span></div>
        @else
            <form method="POST" action="{{ route($routePrefix.'.omr.store') }}" data-answer-key-builder>
                @csrf
                <div class="form-grid">
                    <label class="field-group full"><span>Assessment</span><select name="assessment_id" required><option value="">Select assessment</option>@foreach($assessments as $assessment)<option value="{{ $assessment->id }}" @selected(old('assessment_id')==$assessment->id)>{{ $assessment->title }} · {{ $assessment->section->code }} · {{ number_format($assessment->max_score, 2) }} pts</option>@endforeach</select></label>
                    <label class="field-group"><span>Number of items</span><input type="number" name="item_count" min="1" max="30" value="{{ old('item_count', 20) }}" required data-item-count></label>
                    <label class="field-group"><span>Choices per item</span><select name="choice_count" data-choice-count><option value="2">A–B</option><option value="3">A–C</option><option value="4" @selected(old('choice_count',4)==4)>A–D</option><option value="5" @selected(old('choice_count')==5)>A–E</option></select></label>
                </div>
                <div class="answer-key-heading"><strong>Correct answers</strong><small>Click a letter for every question.</small></div>
                <div class="answer-key-grid" data-answer-key-grid></div>
                <script type="application/json" data-old-answer-key>@json(array_values(old('answers', [])))</script>
                <div class="form-actions"><button class="primary-button compact">Create scanner & answer sheet</button></div>
            </form>
        @endif
    </section>

    <section class="card user-table-card omr-existing-card">
        <div class="sectioning-toolbar"><div><span class="eyebrow">Saved scanner setups</span><h3>Answer sheets</h3><p class="muted-cell">Open a setup to print sheets or scan student answers.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Assessment</th><th>Section</th><th>Format</th><th>Scanned</th><th></th></tr></thead><tbody>
            @forelse($sheets as $sheet)
                <tr><td><strong>{{ $sheet->assessment->title }}</strong><br><small class="muted-cell">{{ number_format($sheet->assessment->max_score,2) }} points</small></td><td>{{ $sheet->assessment->section->code }} · {{ $sheet->assessment->section->component->code }}</td><td>{{ $sheet->item_count }} items · A–{{ chr(64 + $sheet->choice_count) }}</td><td>{{ $sheet->results_count }}</td><td><a class="table-action" href="{{ route($routePrefix.'.omr.show',$sheet) }}">Open scanner</a></td></tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><strong>No answer sheet scanner yet</strong><span>Create your first answer key using the form.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
        @if($sheets->hasPages())<div class="pagination-row"><span>{{ $sheets->total() }} scanner setups</span>{{ $sheets->links() }}</div>@endif
    </section>
</div>

<script src="{{ asset('js/answer-key-builder.js') }}?v={{ filemtime(public_path('js/answer-key-builder.js')) }}"></script>
@endsection
