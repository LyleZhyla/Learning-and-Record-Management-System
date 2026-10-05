@extends('layouts.coordinator')

@section('title', 'System Guide')
@section('page-title', 'System Guide')

@section('content')
    <section class="system-guide-hero">
        <div>
            <span class="eyebrow">Coordinator portal guide</span>
            <h2>Keep your assigned NSTP component on track.</h2>
            <p>Follow this guide to monitor people and sections, coordinate schedules and attendance, review learning performance, and report verified results.</p>
            <div class="system-guide-hero-actions">
                <button class="primary-button" type="button" data-start-coordinator-tour>Start interactive guided tour</button>
                <a class="secondary-outline-button" href="{{ route('coordinator.dashboard') }}">Return to dashboard</a>
            </div>
        </div>
        <img class="snapie-character system-guide-character" src="{{ asset('images/characters/snapie-ai-guide.webp') }}" alt="SNAPIE mascot presenting the Coordinator system guide">
    </section>

    <section class="system-guide-summary" aria-label="Coordinator guide summary">
        <article><span>01</span><div><strong>Monitor</strong><small>Review the people, sections, and schedules within your component.</small></div></article>
        <article><span>02</span><div><strong>Support</strong><small>Coordinate attendance, materials, assessment, and grading work.</small></div></article>
        <article><span>03</span><div><strong>Verify</strong><small>Investigate exceptions and release accurate component reports.</small></div></article>
    </section>

    <section class="system-guide-section" id="coordinator-workflow" aria-labelledby="coordinator-workflow-title">
        <div class="system-guide-heading">
            <div><span class="eyebrow">Recommended workflow</span><h3 id="coordinator-workflow-title">Use your Coordinator portal</h3><p>These steps cover the main oversight tasks for your assigned component.</p></div>
            <span class="pill">7-step workflow</span>
        </div>
        <div class="system-workflow">
            <article class="system-workflow-step"><span class="workflow-number">1</span><div class="workflow-content"><span class="workflow-category">Account setup</span><h4>Secure your account and confirm your scope</h4><p>Update your password and confirm that the component shown in your portal matches your official assignment.</p><div class="workflow-links"><a href="{{ route('coordinator.profile.edit') }}">Profile &amp; Security →</a><a href="{{ route('coordinator.components.index') }}">Component Overview →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">2</span><div class="workflow-content"><span class="workflow-category">People</span><h4>Review facilitators and students</h4><p>Use the account directory to check component placement, enrollment details, and records that need administrative correction.</p><div class="workflow-links"><a href="{{ route('coordinator.accounts.index') }}">Facilitators &amp; Students →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">3</span><div class="workflow-content"><span class="workflow-category">Organization</span><h4>Check sections and schedules</h4><p>Verify facilitator assignments, capacity, required class hours, and schedule conflicts before the term proceeds.</p><div class="workflow-links"><a href="{{ route('coordinator.sections.index') }}">Sections &amp; Facilitators →</a><a href="{{ route('coordinator.schedules.index') }}">Scheduling →</a></div></div></article>
            @if(auth()->user()->nstpComponent?->code === 'ROTC')
                <article class="system-workflow-step"><span class="workflow-number">4</span><div class="workflow-content"><span class="workflow-category">ROTC verification</span><h4>Review ROTC approval requirements</h4><p>Inspect the submitted proof, confirm its validity, and approve only records that meet the authorized requirements.</p><div class="workflow-links"><a href="{{ route('coordinator.rotc-approvals.index') }}">ROTC Approvals →</a></div></div></article>
            @else
                <article class="system-workflow-step"><span class="workflow-number">4</span><div class="workflow-content"><span class="workflow-category">Participation</span><h4>Monitor attendance records</h4><p>Review attendance sessions for your component and investigate unexpected absences, late records, or scanning issues.</p><div class="workflow-links"><a href="{{ route('coordinator.attendance.index') }}">Attendance →</a></div></div></article>
            @endif
            <article class="system-workflow-step"><span class="workflow-number">5</span><div class="workflow-content"><span class="workflow-category">Learning quality</span><h4>Review materials and assessments</h4><p>Confirm that resources and assessments are appropriate for the intended sections and that grading structures are complete.</p><div class="workflow-links"><a href="{{ route('coordinator.materials.index') }}">Learning Materials →</a><a href="{{ route('coordinator.assessments.index') }}">Assessment Review →</a><a href="{{ route('coordinator.grades.index') }}">Grading Setup →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">6</span><div class="workflow-content"><span class="workflow-category">Performance</span><h4>Track results and scan answer sheets carefully</h4><p>Review grade trends and use the OMR scanner only with the correct assessment and properly aligned answer sheet.</p><div class="workflow-links"><a href="{{ route('coordinator.performance.index') }}">Performance &amp; Grades →</a><a href="{{ route('coordinator.omr.index') }}">Answer Sheet Scanner →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">7</span><div class="workflow-content"><span class="workflow-category">Coordination</span><h4>Communicate and report verified information</h4><p>Publish scoped announcements, contact authorized users, and verify filters before exporting component reports.</p><div class="workflow-links"><a href="{{ route('coordinator.announcements.index') }}">Announcements →</a><a href="{{ route('coordinator.messages.index') }}">Messages →</a><a href="{{ route('coordinator.reports.index') }}">Reports Center →</a></div></div></article>
        </div>
    </section>

    <section class="system-guide-section" aria-labelledby="coordinator-safety-title">
        <div class="system-guide-heading"><div><span class="eyebrow">Oversight checks</span><h3 id="coordinator-safety-title">Coordinator checklist</h3><p>Use these checks before approving, changing, or exporting records.</p></div></div>
        <div class="system-safety-grid">
            <article><span>✓</span><div><strong>Stay within your component</strong><p>Confirm that every record belongs to your assigned CWTS, LTS, or ROTC scope.</p></div></article>
            <article><span>✓</span><div><strong>Verify before approval</strong><p>Inspect supporting records and never approve a request based only on an informal message.</p></div></article>
            <article><span>✓</span><div><strong>Resolve schedule conflicts</strong><p>Check facilitator availability and required hours before saving manual schedule changes.</p></div></article>
            <article><span>✓</span><div><strong>Protect exported records</strong><p>Share reports only with authorized recipients and keep unnecessary private data out of AI chats.</p></div></article>
        </div>
    </section>

    <section class="card system-guide-help">
        <div><span class="eyebrow">Want to learn by doing?</span><h3>Let the guided tour show you each module.</h3><p>The tutorial highlights the actual Coordinator menu and follows you when you open a module.</p></div>
        <button class="secondary-button" type="button" data-start-coordinator-tour>Start guided tour →</button>
    </section>
@endsection
