@extends($layout)
@section('title','Create Assessment') @section('page-title','Create Assessment')
@section('content')
@php($canCreateAnswerSheet = auth()->user()->isFacilitator() || auth()->user()->isCoordinator())
<div class="back-row"><a href="{{ auth()->user()->isCoordinator() ? route('coordinator.omr.index') : route($routePrefix.'.assessments.index') }}">← Back</a></div>
<section class="card section-form-card assessment-builder-card">
    <div class="card-heading"><div><h3>Assessment details</h3><p>Create the assessment and, for a quiz or exam, optionally prepare its answer sheet in the same step.</p></div></div>
    <form method="POST" action="{{ route($routePrefix.'.assessments.store') }}" data-assessment-builder @if($canCreateAnswerSheet) data-answer-key-builder @endif>
        @csrf
        <div class="form-grid">
            <label class="field-group full"><span>Section</span><select name="section_id" data-assessment-section required><option value="">Select section</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected(old('section_id')==$section->id)>{{ $section->code }} · {{ $section->component->code }}</option>@endforeach</select></label>
            <label class="field-group full"><span>Title</span><input name="title" value="{{ old('title') }}" required></label>
            <label class="field-group"><span>Type</span><select name="type" data-assessment-type><option value="activity" @selected(old('type')==='activity')>Activity</option><option value="quiz" @selected(old('type')==='quiz')>Quiz</option><option value="project" @selected(old('type')==='project')>Project</option><option value="exam" @selected(old('type')==='exam')>Exam</option></select></label>
            <label class="field-group"><span>Grading sheet category</span><select name="grading_category_id" data-assessment-category required><option value="">Select where scores will appear</option>@foreach($sections as $section)@foreach($section->gradingCategories as $category)<option value="{{ $category->id }}" data-section="{{ $section->id }}" @selected(old('grading_category_id')==$category->id)>{{ $category->name }} ({{ number_format($category->weight,2) }}%)</option>@endforeach @endforeach</select></label>
            <p class="form-help full">Every score recorded for this assessment will automatically appear under the selected category in the grading sheet.</p>
            <label class="field-group"><span>Status</span><select name="status"><option value="published" @selected(old('status','published')==='published')>Published</option><option value="draft" @selected(old('status')==='draft')>Draft</option></select></label>
            <label class="field-group"><span>Maximum score</span><input type="number" step="0.01" min="1" name="max_score" value="{{ old('max_score',100) }}" required></label>
            <label class="field-group"><span>Due date (optional)</span><input type="datetime-local" name="due_at" value="{{ old('due_at') }}"></label>
            <label class="field-group full"><span>Instructions</span><textarea name="instructions" rows="5">{{ old('instructions') }}</textarea></label>
        </div>

        @if($canCreateAnswerSheet)
        <section class="embedded-answer-sheet" data-answer-sheet-option hidden>
            <div class="answer-sheet-choice-heading"><div><span class="eyebrow">Optional scanner setup</span><h3>Create an answer sheet?</h3><p>If Yes, the assessment and printable answer sheet will be saved together.</p></div></div>
            <div class="answer-sheet-toggle" role="radiogroup" aria-label="Create an answer sheet">
                <label><input type="radio" name="create_answer_sheet" value="1" @checked(old('create_answer_sheet')==='1')><span><strong>Yes</strong><small>Create answer key now</small></span></label>
                <label><input type="radio" name="create_answer_sheet" value="0" @checked(old('create_answer_sheet','0')==='0')><span><strong>No</strong><small>Assessment only</small></span></label>
            </div>

            <div class="answer-sheet-setup" data-answer-sheet-setup hidden>
                <div class="form-grid">
                    <label class="field-group"><span>Number of items</span><input type="number" name="item_count" min="1" max="30" value="{{ old('item_count',20) }}" data-item-count></label>
                    <label class="field-group"><span>Choices per item</span><select name="choice_count" data-choice-count><option value="2" @selected(old('choice_count')==2)>A–B</option><option value="3" @selected(old('choice_count')==3)>A–C</option><option value="4" @selected(old('choice_count',4)==4)>A–D</option><option value="5" @selected(old('choice_count')==5)>A–E</option></select></label>
                </div>
                <div class="answer-key-heading"><strong>Correct answers</strong><small>Click a letter for every question.</small></div>
                <div class="answer-key-grid" data-answer-key-grid></div>
            </div>
        </section>

        <script type="application/json" data-old-answer-key>@json(array_values(old('answers', [])))</script>
        @else
        <input type="hidden" name="create_answer_sheet" value="0">
        @endif
        <div class="form-actions"><button class="primary-button compact">Create assessment</button></div>
    </form>
</section>
@if($canCreateAnswerSheet)<script src="{{ asset('js/answer-key-builder.js') }}?v={{ filemtime(public_path('js/answer-key-builder.js')) }}"></script>@endif
<script src="{{ asset('js/assessment-grading-category.js') }}?v={{ filemtime(public_path('js/assessment-grading-category.js')) }}"></script>
@endsection
