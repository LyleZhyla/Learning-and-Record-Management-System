@extends('layouts.student')

@section('title', 'Project Proposal Guide')
@section('page-title', 'Project Proposal Guide')

@section('content')
<section class="proposal-guide-hero">
    <div>
        <span class="eyebrow">AI-assisted planning</span>
        <h2>Turn your project idea into a clearer proposal.</h2>
        <p>Enter the project you want to do. SNAPIE AI will build starter objectives, activities, risks to verify, and next steps.</p>
    </div>
    <div class="proposal-guide-boundary"><span aria-hidden="true">i</span><p><strong>Guidance only</strong> This tool does not approve proposals or make official NSTP decisions. Your facilitator and authorized school officials provide final review.</p></div>
</section>

@unless($isConfigured)
    <div class="alert warning"><strong>AI proposal guidance is not configured.</strong> Please contact the system administrator.</div>
@endunless

@error('proposal_guidance')
    <div class="alert danger" role="alert">{{ $message }}</div>
@enderror

<section class="proposal-guide-layout">
    <form class="card proposal-guide-form" method="POST" action="{{ route('student.proposal-guide.generate') }}">
        @csrf
        <div class="card-heading"><div><h3>What project do you want to do?</h3><p>A short project idea is enough. You can add the place or intended beneficiaries if you already know them.</p></div><span class="pill">Quick start</span></div>

        <div class="form-grid">
            <label class="field-group full">
                <span>Project idea *</span>
                <textarea name="project_idea" rows="6" maxlength="3000" placeholder="Example: Gusto kong gumawa ng weekend reading program para sa mga batang hirap magbasa sa aming barangay." required>{{ old('project_idea', $draft['project_idea'] ?? '') }}</textarea>
                @error('project_idea')<small class="field-error">{{ $message }}</small>@enderror
            </label>
        </div>

        <div class="proposal-guide-submit">
            <p>Your {{ $defaultComponent }} component is selected automatically. Do not enter names or other private beneficiary information. Verify AI suggestions with your facilitator.</p>
            <button class="primary-button" type="submit" @disabled(!$isConfigured)>Generate proposal guidance <span aria-hidden="true">✦</span></button>
        </div>
    </form>

    <aside class="card proposal-guide-checklist">
        <span class="eyebrow">Before generating</span>
        <h3>Strong proposals begin with verified needs.</h3>
        <ol>
            <li><span>1</span><p><strong>Listen first</strong> Consult intended beneficiaries and local partners.</p></li>
            <li><span>2</span><p><strong>Use evidence</strong> Separate verified observations from assumptions.</p></li>
            <li><span>3</span><p><strong>Stay realistic</strong> Match activities to the available time, people, and resources.</p></li>
            <li><span>4</span><p><strong>Protect people</strong> Consider consent, accessibility, safety, and privacy.</p></li>
        </ol>
    </aside>
</section>

@if($guidance)
<section class="proposal-guidance-results" aria-labelledby="proposal-guidance-title">
    <div class="proposal-results-heading">
        <div><span class="eyebrow">Advisory output</span><h2 id="proposal-guidance-title">Your proposal guidance</h2><p>{{ $guidance['summary'] }}</p></div>
        <span class="proposal-advisory-badge">Requires facilitator review</span>
    </div>

    <div class="proposal-result-grid">
        <article class="card proposal-result-card strengths"><span class="proposal-result-icon">✓</span><h3>Promising elements</h3><ul>@forelse($guidance['strengths'] as $item)<li>{{ $item }}</li>@empty<li>Add more project details so strengths can be identified.</li>@endforelse</ul></article>
        <article class="card proposal-result-card recommendations"><span class="proposal-result-icon">↗</span><h3>Recommended improvements</h3><ul>@foreach($guidance['recommendations'] as $item)<li>{{ $item }}</li>@endforeach</ul></article>
        <article class="card proposal-result-card objectives"><span class="proposal-result-icon">◎</span><h3>Suggested objectives</h3><ul>@foreach($guidance['suggested_objectives'] as $item)<li>{{ $item }}</li>@endforeach</ul></article>
        <article class="card proposal-result-card risks"><span class="proposal-result-icon">!</span><h3>Risks and facts to verify</h3><ul>@foreach($guidance['risks'] as $item)<li>{{ $item }}</li>@endforeach</ul></article>
    </div>

    <article class="card proposal-activities-card">
        <div class="card-heading"><div><h3>Suggested activity plan</h3><p>Adapt these ideas with your facilitator and intended community partners.</p></div></div>
        <div class="proposal-activity-list">
            @foreach($guidance['suggested_activities'] as $index => $activity)
                <div><span>{{ $index + 1 }}</span><p><strong>{{ $activity['activity'] }}</strong><small>{{ $activity['purpose'] }}</small></p></div>
            @endforeach
        </div>
    </article>

    <article class="card proposal-next-steps">
        <div><span class="eyebrow">Human review required</span><h3>Recommended next steps</h3></div>
        <ol>@foreach($guidance['next_steps'] as $item)<li>{{ $item }}</li>@endforeach</ol>
        <p>This guidance is not an approval, official evaluation, or guarantee of feasibility. Submit the revised proposal through the process required by your facilitator.</p>
    </article>
</section>
@endif
@endsection
