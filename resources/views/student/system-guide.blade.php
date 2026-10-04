@extends('layouts.student')

@section('title', 'System Guide')
@section('page-title', 'System Guide')

@section('content')
    <section class="system-guide-hero">
        <div>
            <span class="eyebrow">Student portal guide</span>
            <h2>Know where to go and what to do.</h2>
            <p>Follow the student workflow from choosing your NSTP component to checking attendance, completing assessments, and reviewing your grades.</p>
            <div class="system-guide-hero-actions">
                <button class="primary-button" type="button" data-start-student-tour>Start interactive guided tour</button>
                <a class="secondary-outline-button" href="{{ route('student.dashboard') }}">Return to dashboard</a>
            </div>
        </div>
        <img class="snapie-character system-guide-character" src="{{ asset('images/characters/snapie-ai-guide.webp') }}" alt="SNAPIE mascot presenting the student system guide">
    </section>

    <section class="system-guide-summary" aria-label="Student guide summary">
        <article><span>01</span><div><strong>Enroll</strong><small>Keep your profile complete and confirm your NSTP component and section.</small></div></article>
        <article><span>02</span><div><strong>Learn</strong><small>Attend sessions, open materials, and submit assessments on time.</small></div></article>
        <article><span>03</span><div><strong>Stay informed</strong><small>Check grades, reports, announcements, messages, and official updates.</small></div></article>
    </section>

    <section class="system-guide-section" aria-labelledby="student-workflow-title">
        <div class="system-guide-heading">
            <div><span class="eyebrow">Recommended workflow</span><h3 id="student-workflow-title">Use your Student portal</h3><p>These steps cover the main tasks you will complete during the term.</p></div>
            <span class="pill">8-step workflow</span>
        </div>

        <div class="system-workflow">
            <article class="system-workflow-step">
                <span class="workflow-number">1</span>
                <div class="workflow-content"><span class="workflow-category">Account setup</span><h4>Complete and secure your profile</h4><p>Check your student details and contact information, then protect the account with a strong password that you do not share.</p><div class="workflow-links"><a href="{{ route('student.profile.edit') }}">Profile &amp; Security →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">2</span>
                <div class="workflow-content"><span class="workflow-category">Enrollment</span><h4>Choose and confirm your NSTP component</h4><p>Select CWTS, LTS, or ROTC while selection is open. Return here to check the approval status and your assigned section.</p><div class="workflow-links"><a href="{{ route('student.component.edit') }}">NSTP Selection →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">3</span>
                <div class="workflow-content"><span class="workflow-category">Participation</span><h4>Record and review attendance</h4><p>Follow your facilitator's QR attendance instructions and review each Present, Late, or Absent record after the session.</p><div class="workflow-links"><a href="{{ route('student.attendance.index') }}">Attendance →</a><a href="{{ route('student.id-card') }}">Student ID &amp; QR →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">4</span>
                <div class="workflow-content"><span class="workflow-category">Learning</span><h4>Open and prioritize learning materials</h4><p>Use resources published for your component and section, then ask AI to build a practical learning path from only the materials you are authorized to access.</p><div class="workflow-links"><a href="{{ route('student.materials.index') }}">Learning Materials →</a><a href="{{ route('student.recommendations.index') }}">AI Recommendations →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">5</span>
                <div class="workflow-content"><span class="workflow-category">Requirements</span><h4>Improve proposals and complete assessments</h4><p>Use the advisory proposal guide to strengthen a project idea, then follow official facilitator instructions when completing and submitting assessed work.</p><div class="workflow-links"><a href="{{ route('student.proposal-guide.index') }}">Project Proposal Guide →</a><a href="{{ route('student.assessments.index') }}">Assessments →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">6</span>
                <div class="workflow-content"><span class="workflow-category">Progress</span><h4>Review grades and personal reports</h4><p>Check released scores and your computed performance. Use your report page when you need an available personal summary.</p><div class="workflow-links"><a href="{{ route('student.grades.index') }}">Grades →</a><a href="{{ route('student.reports.index') }}">Reports →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">7</span>
                <div class="workflow-content"><span class="workflow-category">Communication</span><h4>Follow official updates</h4><p>Read announcements regularly and use Messages for authorized conversations with your NSTP team and available groups.</p><div class="workflow-links"><a href="{{ route('student.announcements.index') }}">Announcements →</a><a href="{{ route('student.messages.index') }}">Messages →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">8</span>
                <div class="workflow-content"><span class="workflow-category">Help</span><h4>Ask SNAPIE AI responsibly</h4><p>Use the assistant for NSTP and system questions, then verify important requirements against official announcements or your facilitator's instructions.</p><div class="workflow-links"><a href="{{ route('ai-assistant.index') }}">AI Assistant →</a></div></div>
            </article>
        </div>
    </section>

    <section class="system-guide-section" aria-labelledby="student-safety-title">
        <div class="system-guide-heading"><div><span class="eyebrow">Good habits</span><h3 id="student-safety-title">Student safety checklist</h3><p>Use these checks to protect your account and avoid missed requirements.</p></div></div>
        <div class="system-safety-grid">
            <article><span>✓</span><div><strong>Protect your login and QR code</strong><p>Do not share your password or let another person use your identity for attendance.</p></div></article>
            <article><span>✓</span><div><strong>Check deadlines and submission status</strong><p>Open each assessment early and confirm that the correct response or file was submitted.</p></div></article>
            <article><span>✓</span><div><strong>Verify official information</strong><p>Use announcements, your facilitator's instructions, and released records for final requirements.</p></div></article>
            <article><span>✓</span><div><strong>Keep private data out of AI chats</strong><p>Never enter passwords, API keys, or unnecessary personal and student information.</p></div></article>
        </div>
    </section>

    <section class="card system-guide-help">
        <div><span class="eyebrow">Want to learn by doing?</span><h3>Let the guided tour show you each page.</h3><p>The tutorial highlights the actual Student menu. Opening a highlighted item moves the guide to that page, where you can continue to the next module.</p></div>
        <button class="secondary-button" type="button" data-start-student-tour>Start guided tour →</button>
    </section>
@endsection
