@extends('layouts.nstp-admin')

@section('title', 'System Guide')
@section('page-title', 'System Guide')

@section('content')
    <section class="system-guide-hero">
        <div>
            <span class="eyebrow">NSTP Administrator portal guide</span>
            <h2>Coordinate every NSTP operation in the right order.</h2>
            <p>Use this guide to prepare the academic term, organize accounts and sections, supervise learning records, and release reliable reports.</p>
            <div class="system-guide-hero-actions">
                <a class="primary-button" href="#nstp-admin-workflow">View recommended workflow</a>
                <a class="secondary-outline-button" href="{{ route('nstp_admin.dashboard') }}">Return to dashboard</a>
            </div>
        </div>
        <img class="snapie-character system-guide-character" src="{{ asset('images/characters/snapie-ai-guide.webp') }}" alt="SNAPIE mascot presenting the NSTP Administrator system guide">
    </section>

    <section class="system-guide-summary" aria-label="NSTP Administrator guide summary">
        <article><span>01</span><div><strong>Prepare</strong><small>Review registrations, accounts, components, and section capacity.</small></div></article>
        <article><span>02</span><div><strong>Coordinate</strong><small>Arrange schedules and oversee attendance, learning, and assessment records.</small></div></article>
        <article><span>03</span><div><strong>Report</strong><small>Communicate updates and export accurate institutional summaries.</small></div></article>
    </section>

    <section class="system-guide-section" id="nstp-admin-workflow" aria-labelledby="nstp-admin-workflow-title">
        <div class="system-guide-heading">
            <div><span class="eyebrow">Recommended workflow</span><h3 id="nstp-admin-workflow-title">Run NSTP administration</h3><p>Follow these steps when preparing and operating an academic term.</p></div>
            <span class="pill">8-step workflow</span>
        </div>

        <div class="system-workflow">
            <article class="system-workflow-step"><span class="workflow-number">1</span><div class="workflow-content"><span class="workflow-category">Account security</span><h4>Secure your administrator account</h4><p>Confirm your profile details and replace any temporary password before handling institutional records.</p><div class="workflow-links"><a href="{{ route('nstp_admin.profile.edit') }}">Profile &amp; Security →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">2</span><div class="workflow-content"><span class="workflow-category">Registration</span><h4>Review applications and student access</h4><p>Validate registration details and documents, then add or import approved students and distribute their account access securely.</p><div class="workflow-links"><a href="{{ route('nstp_admin.registrations.index') }}">Registration Reviews →</a><a href="{{ route('nstp_admin.students.index') }}">Student Accounts →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">3</span><div class="workflow-content"><span class="workflow-category">People</span><h4>Assign staff and student components</h4><p>Review facilitators, coordinators, and students. Confirm that every person has the correct component assignment before sectioning.</p><div class="workflow-links"><a href="{{ route('nstp_admin.accounts.index') }}">Staff Accounts →</a><a href="{{ route('nstp_admin.components.index') }}">NSTP Components →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">4</span><div class="workflow-content"><span class="workflow-category">Class organization</span><h4>Create sections and schedules</h4><p>Check capacity, assign facilitators, place students into sections, and resolve schedule conflicts before classes begin.</p><div class="workflow-links"><a href="{{ route('nstp_admin.sections.index') }}">Sectioning →</a><a href="{{ route('nstp_admin.schedules.index') }}">Scheduling →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">5</span><div class="workflow-content"><span class="workflow-category">Operations</span><h4>Oversee attendance and learning resources</h4><p>Monitor attendance sessions and ensure that materials are published only to the intended component or section.</p><div class="workflow-links"><a href="{{ route('nstp_admin.attendance.index') }}">Attendance →</a><a href="{{ route('nstp_admin.materials.index') }}">Learning Materials →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">6</span><div class="workflow-content"><span class="workflow-category">Assessment</span><h4>Review assessments and grade records</h4><p>Check assessment setup, grading structures, recorded scores, and exceptions that require an authorized manual review.</p><div class="workflow-links"><a href="{{ route('nstp_admin.assessments.index') }}">Assessments →</a><a href="{{ route('nstp_admin.grades.index') }}">Grades →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">7</span><div class="workflow-content"><span class="workflow-category">Communication</span><h4>Publish and coordinate official updates</h4><p>Use announcements for broad notices and Messages for authorized operational conversations. Verify important AI guidance before acting on it.</p><div class="workflow-links"><a href="{{ route('nstp_admin.announcements.index') }}">Announcements →</a><a href="{{ route('nstp_admin.messages.index') }}">Messages →</a><a href="{{ route('ai-assistant.index') }}">AI Assistant →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">8</span><div class="workflow-content"><span class="workflow-category">Accountability</span><h4>Validate and export reports</h4><p>Confirm the academic term, component, and record status before downloading or sharing any official summary.</p><div class="workflow-links"><a href="{{ route('nstp_admin.reports.index') }}">Reports Center →</a></div></div></article>
        </div>
    </section>

    <section class="system-guide-section" aria-labelledby="nstp-admin-safety-title">
        <div class="system-guide-heading"><div><span class="eyebrow">Before saving changes</span><h3 id="nstp-admin-safety-title">NSTP Administrator checklist</h3><p>Use these checks to keep term records complete and consistent.</p></div></div>
        <div class="system-safety-grid">
            <article><span>✓</span><div><strong>Verify the active term</strong><p>Confirm the academic year and semester before assigning, sectioning, scheduling, or reporting.</p></div></article>
            <article><span>✓</span><div><strong>Check role and component scope</strong><p>Make sure staff and students are assigned only to the component they are authorized to access.</p></div></article>
            <article><span>✓</span><div><strong>Review bulk actions first</strong><p>Check counts, filters, and affected records before importing students or applying assignments.</p></div></article>
            <article><span>✓</span><div><strong>Protect student information</strong><p>Share exports only with authorized personnel and never place passwords or private records in AI chats.</p></div></article>
        </div>
    </section>

    <section class="card system-guide-help">
        <div><span class="eyebrow">Need help while working?</span><h3>Ask SNAPIE AI for workflow guidance.</h3><p>Use the assistant to understand system features, but verify official decisions against institutional policy and authorized records.</p></div>
        <a class="secondary-button" href="{{ route('ai-assistant.index') }}">Open AI Assistant →</a>
    </section>
@endsection
