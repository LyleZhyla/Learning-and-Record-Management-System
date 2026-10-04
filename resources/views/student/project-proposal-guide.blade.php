@extends('layouts.student')

@section('title', 'Project Proposal Guide')
@section('page-title', 'Project Proposal Guide')

@section('content')
<section class="proposal-guide-hero">
    <div>
        <span class="eyebrow">AI-assisted planning</span>
        <h2>Turn a community need into a clearer project proposal.</h2>
        <p>Describe your idea and SNAPIE AI will suggest improvements, measurable objectives, practical activities, risks to verify, and next steps.</p>
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
        <div class="card-heading"><div><h3>Describe your project idea</h3><p>Required details help the AI give useful guidance without inventing facts about your community.</p></div><span class="pill">Draft review</span></div>

        <div class="form-grid">
            <label class="field-group">
                <span>NSTP component *</span>
                <select name="component" required>
                    @foreach(['CWTS', 'LTS', 'ROTC'] as $component)
                        <option value="{{ $component }}" @selected(old('component', $draft['component'] ?? $defaultComponent) === $component)>{{ $component }}</option>
                    @endforeach
                </select>
                @error('component')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="field-group">
                <span>Working project title *</span>
                <input name="project_title" value="{{ old('project_title', $draft['project_title'] ?? '') }}" maxlength="180" placeholder="Example: Barangay Reading Buddies" required>
                @error('project_title')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="field-group full">
                <span>Community need or problem *</span>
                <textarea name="community_need" rows="5" maxlength="3000" placeholder="What problem did you observe? What evidence or community input still needs verification?" required>{{ old('community_need', $draft['community_need'] ?? '') }}</textarea>
                @error('community_need')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="field-group full">
                <span>Target beneficiaries *</span>
                <textarea name="target_beneficiaries" rows="3" maxlength="1200" placeholder="Who may benefit, where are they located, and approximately how many people are involved?" required>{{ old('target_beneficiaries', $draft['target_beneficiaries'] ?? '') }}</textarea>
                @error('target_beneficiaries')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="field-group full">
                <span>Draft objectives</span>
                <textarea name="proposed_objectives" rows="4" maxlength="3000" placeholder="List what the project should achieve. You may leave this blank for suggestions.">{{ old('proposed_objectives', $draft['proposed_objectives'] ?? '') }}</textarea>
                @error('proposed_objectives')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="field-group full">
                <span>Draft activities</span>
                <textarea name="proposed_activities" rows="4" maxlength="3000" placeholder="Describe the activities you are considering.">{{ old('proposed_activities', $draft['proposed_activities'] ?? '') }}</textarea>
                @error('proposed_activities')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="field-group">
                <span>Proposed timeline</span>
                <textarea name="timeline" rows="4" maxlength="1200" placeholder="Dates, number of sessions, or project phases">{{ old('timeline', $draft['timeline'] ?? '') }}</textarea>
                @error('timeline')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="field-group">
                <span>Available resources or constraints</span>
                <textarea name="available_resources" rows="4" maxlength="2000" placeholder="People, materials, budget limits, permissions, or safety concerns">{{ old('available_resources', $draft['available_resources'] ?? '') }}</textarea>
                @error('available_resources')<small class="field-error">{{ $message }}</small>@enderror
            </label>
        </div>

        <div class="proposal-guide-submit">
            <p>Do not enter private beneficiary information, passwords, or confidential records. Verify all AI suggestions with your facilitator and community partners.</p>
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
