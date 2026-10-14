@extends('layouts.student')

@section('title', 'Saved Learning Path')
@section('page-title', 'Saved Learning Path')

@section('content')
@php
    $studyGoal = match ($savedRecommendation->preferences['study_goal'] ?? null) {
        'catch_up' => 'Catch up',
        'prepare_assessment' => 'Prepare for assessment',
        'improve_performance' => 'Improve performance',
        'deepen_understanding' => 'Deepen understanding',
        default => 'Personalized learning path',
    };
@endphp

<div class="page-actions">
    <div>
        <span class="eyebrow">Generated AI learning recommendation</span>
        <h2>{{ $studyGoal }}</h2>
        <p>{{ $savedRecommendation->enrollment?->component?->code ?? 'NSTP' }} · {{ $savedRecommendation->enrollment?->section?->code ?? 'Previous class' }} · Generated {{ $savedRecommendation->created_at->format('M d, Y · h:i A') }}</p>
    </div>
    <a class="secondary-outline-button" href="{{ route('student.recommendations.index') }}">← Back to recommendations</a>
</div>

<section class="learning-path-results" aria-labelledby="learning-path-title">
    <div class="learning-path-heading">
        <div>
            <span class="eyebrow">Personalized advisory result</span>
            <h2 id="learning-path-title">Your recommended learning path</h2>
            <p>{{ $guidance['overview'] }}</p>
        </div>
        <span>Saved {{ $savedRecommendation->created_at->format('M d, Y · h:i A') }}</span>
    </div>

    <div class="recommended-material-list">
        @foreach($guidance['recommendations'] as $index => $recommendation)
            @php
                $material = $materialsById->get($recommendation['material_id']);
                $isCurrentlyAvailable = $material && $materials->contains('id', $material->id);
                $materialTitle = $material?->title ?? ($recommendation['material_title'] ?? 'Previously recommended material');
                $componentCode = $material?->component?->code ?? ($recommendation['component_code'] ?? 'NSTP');
                $sectionCode = $material?->section?->code ?? ($recommendation['section_code'] ?? null);
            @endphp
            <article class="card recommended-material-card priority-{{ $recommendation['priority'] }}">
                <div class="recommended-material-order"><span>{{ $index + 1 }}</span><small>{{ ucfirst($recommendation['priority']) }} priority</small></div>
                <div class="recommended-material-copy">
                    <small>{{ $sectionCode ?: 'All '.$componentCode.' sections' }}</small>
                    <h3>{{ $materialTitle }}</h3>
                    <p>{{ $recommendation['reason'] }}</p>
                    <div><strong>How to study it</strong><span>{{ $recommendation['study_action'] }}</span></div>
                </div>
                <div class="recommended-material-action">
                    @if($isCurrentlyAvailable && $material->file_path)
                        <a class="secondary-outline-button" href="{{ route('student.materials.download', $material) }}">Download resource</a>
                    @elseif($isCurrentlyAvailable && $material->external_url)
                        <a class="secondary-outline-button" target="_blank" rel="noopener" href="{{ $material->external_url }}">Open resource</a>
                    @else
                        <span>No longer in your current catalog</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div class="learning-support-grid">
        <article class="card learning-focus-card">
            <span class="eyebrow">Focus areas</span><h3>What to pay attention to</h3>
            <ul>@foreach($guidance['focus_areas'] as $item)<li>{{ $item }}</li>@endforeach</ul>
        </article>
        <article class="card learning-plan-card">
            <span class="eyebrow">Suggested sequence</span><h3>Your study plan</h3>
            <ol>@foreach($guidance['study_plan'] as $step)<li><span>{{ $loop->iteration }}</span><p><strong>{{ $step['title'] }}</strong><small>{{ $step['action'] }}</small></p></li>@endforeach</ol>
        </article>
    </div>

    <p class="learning-recommendation-disclaimer">AI learning recommendations are optional learning support. Follow official assessment instructions, deadlines, and guidance from your facilitator.</p>
</section>
@endsection
