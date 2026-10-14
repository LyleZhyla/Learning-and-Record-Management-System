@extends('layouts.student')

@section('title', 'Project Proposal Guide')
@section('page-title', 'Project Proposal Guide')

@section('page-styles')
    <link rel="stylesheet" href="{{ asset('css/student-proposal-guide.css') }}?v={{ filemtime(public_path('css/student-proposal-guide.css')) }}">
@endsection

@section('content')
<div class="proposal-workspace" data-proposal-workspace>
    <section class="proposal-workspace-hero">
        <div class="proposal-hero-copy">
            <span class="proposal-kicker"><span aria-hidden="true">✦</span> AI-assisted project planning</span>
            <h2>Shape your community idea into a proposal you can discuss.</h2>
            <p>Describe what you want to do. SNAPIE AI will help organize your objectives, activities, risks, and next steps before facilitator review.</p>
            <div class="proposal-hero-actions">
                <a class="proposal-project-link" href="{{ route('student.community-projects.index') }}">Open Community Projects <span aria-hidden="true">→</span></a>
                <span class="proposal-component-pill"><small>Current component</small><strong>{{ $defaultComponent }}</strong></span>
            </div>
        </div>

        <div class="proposal-process" aria-label="Proposal guidance process">
            <div class="proposal-process-step active"><span>1</span><p><strong>Describe</strong><small>Share your project idea</small></p></div>
            <i aria-hidden="true"></i>
            <div class="proposal-process-step"><span>2</span><p><strong>Generate</strong><small>Receive starter guidance</small></p></div>
            <i aria-hidden="true"></i>
            <div class="proposal-process-step"><span>3</span><p><strong>Review</strong><small>Refine with your facilitator</small></p></div>
        </div>
    </section>

    @unless($isConfigured)
        <div class="alert warning"><strong>AI proposal guidance is not configured.</strong> Please contact the system administrator.</div>
    @endunless

    @error('proposal_guidance')
        <div class="alert danger" role="alert">{{ $message }}</div>
    @enderror

    <section class="proposal-editor-layout">
        <form class="proposal-editor" method="POST" action="{{ route('student.proposal-guide.generate') }}">
            @csrf
            <div class="proposal-editor-heading">
                <span class="proposal-editor-number">01</span>
                <div>
                    <span class="proposal-section-label">Start with your idea</span>
                    <h3>What project do you want to do?</h3>
                    <p>A simple description is enough. Include the community need, location, or intended beneficiaries if already known.</p>
                </div>
                <span class="proposal-quick-badge">Quick start</span>
            </div>

            <label class="proposal-idea-field">
                <span>Project idea <b>*</b></span>
                <textarea name="project_idea" rows="8" maxlength="3000" placeholder="Example: Gusto kong gumawa ng weekend reading program para sa mga batang hirap magbasa sa aming barangay." required>{{ old('project_idea', $draft['project_idea'] ?? '') }}</textarea>
                <small>Explain the idea in your own words · Up to 3,000 characters</small>
                @error('project_idea')<span class="field-error">{{ $message }}</span>@enderror
            </label>

            <div class="proposal-privacy-note">
                <span aria-hidden="true">⌾</span>
                <p><strong>Protect beneficiary privacy</strong>Do not include names, contact details, medical information, or other private personal data.</p>
            </div>

            <div class="proposal-editor-submit">
                <p><strong>{{ $defaultComponent }} guidance</strong><span>Your component is selected automatically. Always verify AI suggestions with your facilitator.</span></p>
                <button class="primary-button" type="submit" @disabled(!$isConfigured)>Generate proposal guidance <span aria-hidden="true">✦</span></button>
            </div>
        </form>

        <aside class="proposal-readiness-card">
            <div class="proposal-readiness-heading">
                <span aria-hidden="true">✓</span>
                <div><small>Before generating</small><h3>Proposal readiness guide</h3></div>
            </div>
            <p>Strong community projects begin with evidence, listening, and realistic planning.</p>
            <ol>
                <li><span>1</span><div><strong>Listen first</strong><p>Consult intended beneficiaries and community partners.</p></div></li>
                <li><span>2</span><div><strong>Use evidence</strong><p>Separate verified observations from assumptions.</p></div></li>
                <li><span>3</span><div><strong>Stay realistic</strong><p>Match activities to available time, people, and resources.</p></div></li>
                <li><span>4</span><div><strong>Protect people</strong><p>Consider consent, accessibility, safety, and privacy.</p></div></li>
            </ol>
            <div class="proposal-guidance-boundary"><span aria-hidden="true">i</span><p><strong>Guidance, not approval</strong>Authorized school officials and your facilitator make all final decisions.</p></div>
        </aside>
    </section>

    @if($guidance)
    <section class="proposal-output" id="proposal-guidance-results" data-proposal-results aria-labelledby="proposal-guidance-title">
        <div class="proposal-output-heading">
            <div>
                <span class="proposal-section-label">AI-generated advisory</span>
                <h2 id="proposal-guidance-title">Your proposal guidance</h2>
                <p>{{ $guidance['summary'] }}</p>
            </div>
            <div class="proposal-output-status"><span aria-hidden="true">!</span><p><strong>Facilitator review required</strong><small>Use this as a working draft, not an approval.</small></p></div>
        </div>

        <div class="proposal-insight-grid">
            <article class="proposal-insight-card strengths">
                <div class="proposal-insight-heading"><span aria-hidden="true">✓</span><div><small>What already works</small><h3>Promising elements</h3></div></div>
                <ul>@forelse($guidance['strengths'] as $item)<li>{{ $item }}</li>@empty<li>Add more project details so strengths can be identified.</li>@endforelse</ul>
            </article>
            <article class="proposal-insight-card improvements">
                <div class="proposal-insight-heading"><span aria-hidden="true">↗</span><div><small>What to strengthen</small><h3>Recommended improvements</h3></div></div>
                <ul>@forelse($guidance['recommendations'] as $item)<li>{{ $item }}</li>@empty<li>Discuss the idea with your facilitator to identify possible improvements.</li>@endforelse</ul>
            </article>
            <article class="proposal-insight-card objectives">
                <div class="proposal-insight-heading"><span aria-hidden="true">◎</span><div><small>What to accomplish</small><h3>Suggested objectives</h3></div></div>
                <ul>@forelse($guidance['suggested_objectives'] as $item)<li>{{ $item }}</li>@empty<li>Define a measurable objective with your facilitator and community partner.</li>@endforelse</ul>
            </article>
            <article class="proposal-insight-card risks">
                <div class="proposal-insight-heading"><span aria-hidden="true">!</span><div><small>What to confirm</small><h3>Risks and facts to verify</h3></div></div>
                <ul>@forelse($guidance['risks'] as $item)<li>{{ $item }}</li>@empty<li>Verify permissions, safety requirements, resources, and community needs before implementation.</li>@endforelse</ul>
            </article>
        </div>

        <article class="proposal-activity-card">
            <div class="proposal-activity-heading">
                <span class="proposal-editor-number">02</span>
                <div><span class="proposal-section-label">Starter implementation plan</span><h3>Suggested activities</h3><p>Adapt the sequence with your facilitator and intended community partners.</p></div>
            </div>
            <div class="proposal-activity-timeline">
                @forelse($guidance['suggested_activities'] as $index => $activity)
                    <div class="proposal-activity-item"><span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><div><strong>{{ $activity['activity'] }}</strong><p>{{ $activity['purpose'] }}</p></div></div>
                @empty
                    <div class="proposal-activity-empty">Build the activity sequence with your facilitator after verifying the community need.</div>
                @endforelse
            </div>
        </article>

        <article class="proposal-next-step-card">
            <div class="proposal-next-step-intro"><span aria-hidden="true">→</span><div><small>Human review required</small><h3>Recommended next steps</h3><p>Turn the AI guidance into a proposal grounded in verified community information.</p></div></div>
            <ol>@forelse($guidance['next_steps'] as $item)<li><span>{{ $loop->iteration }}</span><p>{{ $item }}</p></li>@empty<li><span>1</span><p>Discuss the project idea and missing details with your NSTP facilitator.</p></li>@endforelse</ol>
            <div class="proposal-final-note"><span aria-hidden="true">i</span><p>This guidance is not an approval, official evaluation, or guarantee of feasibility. Follow the proposal process required by your facilitator.</p></div>
        </article>
    </section>
    @endif
</div>
@endsection
