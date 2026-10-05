@extends('layouts.student')

@section('title', 'AI Learning Recommendations')
@section('page-title', 'AI Learning Recommendations')

@section('content')
<section class="learning-recommendation-hero">
    <div><span class="eyebrow">Personalized learning support</span><h2>Study the right material at the right time.</h2><p>SNAPIE AI prioritizes only the resources published for your NSTP component and section, using your study goal, available time, assessment status, and released performance.</p></div>
    <div class="learning-context-summary">
        <article><strong>{{ $materials->count() }}</strong><small>available materials</small></article>
        <article><strong>{{ $assessments->count() }}</strong><small>published assessments</small></article>
        <article><strong>{{ $enrollment?->component?->code ?? '—' }}</strong><small>current component</small></article>
    </div>
</section>

@unless($isConfigured)
    <div class="alert warning"><strong>AI learning recommendations are not configured.</strong> Please contact the system administrator.</div>
@endunless
@if(!$enrollment)
    <div class="alert warning"><strong>No active NSTP enrollment.</strong> Complete your component enrollment before requesting personalized recommendations.</div>
@elseif($materials->isEmpty())
    <div class="alert warning"><strong>No materials to recommend yet.</strong> Published resources for your component and section will appear here when available.</div>
@endif
@error('recommendations')<div class="alert danger" role="alert">{{ $message }}</div>@enderror

<section class="learning-recommendation-layout">
    <form class="card learning-preference-form" method="POST" action="{{ route('student.recommendations.generate') }}">
        @csrf
        <div class="card-heading"><div><h3>Set your learning goal</h3><p>The recommender uses these choices for this request only. It cannot alter grades, assessments, or official records.</p></div><span class="pill">Advisory</span></div>

        <div class="learning-choice-group">
            <fieldset>
                <legend>What do you want to accomplish?</legend>
                <div class="learning-choice-grid">
                    @foreach([
                        'catch_up' => ['Catch up', 'Start with essential materials you may have missed.'],
                        'prepare_assessment' => ['Prepare for an assessment', 'Prioritize resources related to pending classwork.'],
                        'improve_performance' => ['Improve performance', 'Use released results to focus your review.'],
                        'deepen_understanding' => ['Deepen understanding', 'Explore the subject beyond immediate requirements.'],
                    ] as $value => [$label, $description])
                        <label><input type="radio" name="study_goal" value="{{ $value }}" @checked(old('study_goal', $preferences['study_goal'] ?? 'catch_up') === $value)><span><strong>{{ $label }}</strong><small>{{ $description }}</small></span></label>
                    @endforeach
                </div>
                @error('study_goal')<small class="field-error">{{ $message }}</small>@enderror
            </fieldset>

            <div class="form-grid">
                <label class="field-group"><span>Weekly study time *</span><select name="weekly_time" required><option value="under_2" @selected(old('weekly_time', $preferences['weekly_time'] ?? '') === 'under_2')>Less than 2 hours</option><option value="2_to_4" @selected(old('weekly_time', $preferences['weekly_time'] ?? '2_to_4') === '2_to_4')>2–4 hours</option><option value="5_plus" @selected(old('weekly_time', $preferences['weekly_time'] ?? '') === '5_plus')>5 hours or more</option></select>@error('weekly_time')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="field-group"><span>Preferred learning approach *</span><select name="learning_style" required><option value="mixed" @selected(old('learning_style', $preferences['learning_style'] ?? 'mixed') === 'mixed')>Mixed methods</option><option value="reading" @selected(old('learning_style', $preferences['learning_style'] ?? '') === 'reading')>Reading and note-taking</option><option value="practice" @selected(old('learning_style', $preferences['learning_style'] ?? '') === 'practice')>Practice and application</option><option value="visual" @selected(old('learning_style', $preferences['learning_style'] ?? '') === 'visual')>Visual examples</option></select>@error('learning_style')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="field-group full"><span>Specific goal or topic</span><textarea name="specific_goal" rows="3" maxlength="1000" placeholder="Example: I want to understand community needs assessment before our project activity.">{{ old('specific_goal', $preferences['specific_goal'] ?? '') }}</textarea>@error('specific_goal')<small class="field-error">{{ $message }}</small>@enderror</label>
            </div>
        </div>

        <div class="learning-recommendation-submit"><p>Only material titles/descriptions and limited assessment progress are used. Your submitted answers and private files are not sent for this recommendation.</p><button class="primary-button" type="submit" @disabled(!$isConfigured || !$enrollment || $materials->isEmpty())>Build my learning path <span aria-hidden="true">✦</span></button></div>
    </form>

    <aside class="card available-materials-panel">
        <div class="card-heading"><div><h3>Recommendation catalog</h3><p>Only these authorized resources may be selected.</p></div></div>
        <div class="available-materials-list">
            @forelse($materials as $material)
                <div><span>{{ $loop->iteration }}</span><p><strong>{{ $material->title }}</strong><small>{{ $material->section?->code ?? 'All '.$material->component->code.' sections' }}</small></p></div>
            @empty
                <p class="available-materials-empty">No published resources available.</p>
            @endforelse
        </div>
    </aside>
</section>

@if($guidance)
<section class="learning-path-results" aria-labelledby="learning-path-title">
    <div class="learning-path-heading"><div><span class="eyebrow">Personalized advisory result</span><h2 id="learning-path-title">Your recommended learning path</h2><p>{{ $guidance['overview'] }}</p></div><span>Based on current available materials</span></div>

    <div class="recommended-material-list">
        @foreach($guidance['recommendations'] as $index => $recommendation)
            @php($material = $materialsById->get($recommendation['material_id']))
            @if($material)
                <article class="card recommended-material-card priority-{{ $recommendation['priority'] }}">
                    <div class="recommended-material-order"><span>{{ $index + 1 }}</span><small>{{ ucfirst($recommendation['priority']) }} priority</small></div>
                    <div class="recommended-material-copy"><small>{{ $material->section?->code ?? 'All '.$material->component->code.' sections' }}</small><h3>{{ $material->title }}</h3><p>{{ $recommendation['reason'] }}</p><div><strong>How to study it</strong><span>{{ $recommendation['study_action'] }}</span></div></div>
                    <div class="recommended-material-action">@if($material->file_path)<a class="secondary-outline-button" href="{{ route('student.materials.download', $material) }}">Download resource</a>@elseif($material->external_url)<a class="secondary-outline-button" target="_blank" rel="noopener" href="{{ $material->external_url }}">Open resource</a>@else<span>Review in Materials</span>@endif</div>
                </article>
            @endif
        @endforeach
    </div>

    <div class="learning-support-grid">
        <article class="card learning-focus-card"><span class="eyebrow">Focus areas</span><h3>What to pay attention to</h3><ul>@foreach($guidance['focus_areas'] as $item)<li>{{ $item }}</li>@endforeach</ul></article>
        <article class="card learning-plan-card"><span class="eyebrow">Suggested sequence</span><h3>Your study plan</h3><ol>@foreach($guidance['study_plan'] as $step)<li><span>{{ $loop->iteration }}</span><p><strong>{{ $step['title'] }}</strong><small>{{ $step['action'] }}</small></p></li>@endforeach</ol></article>
    </div>

    <p class="learning-recommendation-disclaimer">AI learning recommendations are optional learning support. Follow official assessment instructions, deadlines, and guidance from your facilitator.</p>
</section>
@endif
@endsection
