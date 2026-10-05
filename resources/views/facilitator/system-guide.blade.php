@extends('layouts.facilitator')

@section('title', 'System Guide')
@section('page-title', 'System Guide')

@section('content')
    <section class="system-guide-hero">
        <div>
            <span class="eyebrow">Facilitator portal guide</span>
            <h2>Manage your classes from attendance to final grades.</h2>
            <p>Use this guide to check assigned students, run attendance, publish learning resources, assess work, record grades, and communicate clearly.</p>
            <div class="system-guide-hero-actions">
                <button class="primary-button" type="button" data-start-facilitator-tour>Start interactive guided tour</button>
                <a class="secondary-outline-button" href="{{ route('facilitator.dashboard') }}">Return to dashboard</a>
            </div>
        </div>
        <img class="snapie-character system-guide-character" src="{{ asset('images/characters/snapie-ai-guide.webp') }}" alt="SNAPIE mascot presenting the Facilitator system guide">
    </section>

    <section class="system-guide-summary" aria-label="Facilitator guide summary">
        <article><span>01</span><div><strong>Prepare</strong><small>Confirm your assigned classes, students, and learning requirements.</small></div></article>
        <article><span>02</span><div><strong>Teach</strong><small>Manage attendance, materials, assessments, and submissions.</small></div></article>
        <article><span>03</span><div><strong>Evaluate</strong><small>Review evidence, record grades, communicate, and report progress.</small></div></article>
    </section>

    <section class="system-guide-section" id="facilitator-workflow" aria-labelledby="facilitator-workflow-title">
        <div class="system-guide-heading">
            <div><span class="eyebrow">Recommended workflow</span><h3 id="facilitator-workflow-title">Use your Facilitator portal</h3><p>Follow these steps during regular class and assessment work.</p></div>
            <span class="pill">7-step workflow</span>
        </div>
        <div class="system-workflow">
            <article class="system-workflow-step"><span class="workflow-number">1</span><div class="workflow-content"><span class="workflow-category">Account setup</span><h4>Secure your account</h4><p>Confirm your profile and change any temporary password before opening student records or recording results.</p><div class="workflow-links"><a href="{{ route('facilitator.profile.edit') }}">Profile &amp; Security →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">2</span><div class="workflow-content"><span class="workflow-category">Class preparation</span><h4>Review your assigned students</h4><p>Check the students in each assigned section and report missing or incorrect assignments to the authorized administrator.</p><div class="workflow-links"><a href="{{ route('facilitator.students.index') }}">My Students →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">3</span><div class="workflow-content"><span class="workflow-category">Participation</span><h4>Create and manage attendance sessions</h4><p>Choose the correct section and schedule, display the authorized QR session, and review time-in, time-out, late, and absent records.</p><div class="workflow-links"><a href="{{ route('facilitator.attendance.index') }}">Attendance →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">4</span><div class="workflow-content"><span class="workflow-category">Learning</span><h4>Publish materials to the correct class</h4><p>Use clear titles and instructions, verify attachments and links, and limit each resource to the intended component or section.</p><div class="workflow-links"><a href="{{ route('facilitator.materials.index') }}">Learning Materials →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">5</span><div class="workflow-content"><span class="workflow-category">Assessment</span><h4>Create assessments and review submissions</h4><p>Set clear instructions, deadlines, maximum scores, and rubrics. Treat AI scoring as a suggestion and make the final academic judgment yourself.</p><div class="workflow-links"><a href="{{ route('facilitator.assessments.index') }}">Assessments →</a><a href="{{ route('facilitator.omr.index') }}">Answer Sheet Scanner →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">6</span><div class="workflow-content"><span class="workflow-category">Grading</span><h4>Validate and release grade records</h4><p>Check every score against the official rubric, resolve missing work, and verify weighted totals before treating grades as final.</p><div class="workflow-links"><a href="{{ route('facilitator.grades.index') }}">Grades →</a><a href="{{ route('facilitator.reports.index') }}">Reports Center →</a></div></div></article>
            <article class="system-workflow-step"><span class="workflow-number">7</span><div class="workflow-content"><span class="workflow-category">Communication</span><h4>Keep students informed</h4><p>Read official announcements, use Messages for authorized conversations, and provide timely instructions without exposing private student information.</p><div class="workflow-links"><a href="{{ route('facilitator.announcements.index') }}">Announcements →</a><a href="{{ route('facilitator.messages.index') }}">Messages →</a><a href="{{ route('ai-assistant.index') }}">AI Assistant →</a></div></div></article>
        </div>
    </section>

    <section class="system-guide-section" aria-labelledby="facilitator-safety-title">
        <div class="system-guide-heading"><div><span class="eyebrow">Before publishing or grading</span><h3 id="facilitator-safety-title">Facilitator checklist</h3><p>Use these checks to protect students and maintain fair, accurate records.</p></div></div>
        <div class="system-safety-grid">
            <article><span>✓</span><div><strong>Check the section first</strong><p>Confirm the selected class before publishing materials, attendance sessions, or assessments.</p></div></article>
            <article><span>✓</span><div><strong>Use observable grading evidence</strong><p>Apply the approved rubric consistently and manually review unclear or low-confidence AI suggestions.</p></div></article>
            <article><span>✓</span><div><strong>Confirm saved records</strong><p>Review attendance, submission, and score status after every important update.</p></div></article>
            <article><span>✓</span><div><strong>Protect student privacy</strong><p>Do not share credentials, unnecessary personal data, private submissions, or grades with unauthorized people or AI tools.</p></div></article>
        </div>
    </section>

    <section class="card system-guide-help">
        <div><span class="eyebrow">Want to learn by doing?</span><h3>Let the guided tour show you each module.</h3><p>The tutorial highlights the actual Facilitator menu and follows you when you open a module.</p></div>
        <button class="secondary-button" type="button" data-start-facilitator-tour>Start guided tour →</button>
    </section>
@endsection
