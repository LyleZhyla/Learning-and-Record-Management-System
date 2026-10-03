@extends('layouts.admin')

@section('title', 'System Guide')
@section('page-title', 'System Guide')

@section('content')
    <section class="system-guide-hero">
        <div>
            <span class="eyebrow">Super Administrator overview</span>
            <h2>Run Smart NSTP with confidence.</h2>
            <p>This guide follows the recommended order for preparing a term, managing daily operations, and protecting institutional records.</p>
            <div class="system-guide-hero-actions">
                <a class="primary-button" href="#setup-workflow">Start with the setup workflow</a>
                <a class="secondary-outline-button" href="{{ route('admin.dashboard') }}">Return to dashboard</a>
            </div>
        </div>
        <img class="snapie-character system-guide-character" src="{{ asset('images/characters/snapie-ai-guide.webp') }}" alt="SNAPIE mascot presenting the system guide">
    </section>

    <section class="system-guide-summary" aria-label="Super Admin guide summary">
        <article><span>01</span><div><strong>Prepare</strong><small>Configure the term, roles, components, sections, and schedules.</small></div></article>
        <article><span>02</span><div><strong>Operate</strong><small>Oversee enrollment, attendance, learning, assessments, and communication.</small></div></article>
        <article><span>03</span><div><strong>Protect</strong><small>Review reports and logs, then back up or archive completed records.</small></div></article>
    </section>

    <section class="system-guide-section" id="setup-workflow" aria-labelledby="setup-workflow-title">
        <div class="system-guide-heading">
            <div><span class="eyebrow">Recommended order</span><h3 id="setup-workflow-title">Set up and use the system</h3><p>Complete these steps from top to bottom when opening a new academic term.</p></div>
            <span class="pill">8-step workflow</span>
        </div>

        <div class="system-workflow">
            <article class="system-workflow-step">
                <span class="workflow-number">1</span>
                <div class="workflow-content"><span class="workflow-category">Security first</span><h4>Secure your account and system settings</h4><p>Update your profile and password, then set the inactivity timeout used by every account.</p><div class="workflow-links"><a href="{{ route('admin.profile.edit') }}">Profile & Security →</a><a href="{{ route('admin.settings.edit') }}">System Settings →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">2</span>
                <div class="workflow-content"><span class="workflow-category">People and permissions</span><h4>Create the operational team</h4><p>Add NSTP Admins, coordinators, and facilitators. Assign the correct role and component so each user sees only the tools needed for their work.</p><div class="workflow-links"><a href="{{ route('admin.users.create') }}">Create staff account →</a><a href="{{ route('admin.users.index') }}">Manage staff →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">3</span>
                <div class="workflow-content"><span class="workflow-category">Academic structure</span><h4>Configure NSTP components</h4><p>Review CWTS, LTS, and ROTC settings, default capacities, and whether students may submit their component selection.</p><div class="workflow-links"><a href="{{ route('admin.components.index') }}">NSTP Components →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">4</span>
                <div class="workflow-content"><span class="workflow-category">Student access</span><h4>Add or import student accounts</h4><p>Review registrations, import a class list when needed, distribute account access, and download student QR codes.</p><div class="workflow-links"><a href="{{ route('admin.students.index') }}">Student Accounts →</a><a href="{{ route('admin.students.import.create') }}">Import students →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">5</span>
                <div class="workflow-content"><span class="workflow-category">Class organization</span><h4>Create sections and generate schedules</h4><p>Assign students and facilitators to sections, check capacity, then generate or adjust conflict-aware schedules.</p><div class="workflow-links"><a href="{{ route('admin.sections.index') }}">Sectioning →</a><a href="{{ route('admin.schedules.index') }}">Scheduling →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">6</span>
                <div class="workflow-content"><span class="workflow-category">Daily operations</span><h4>Oversee attendance and learning</h4><p>Create or monitor QR attendance sessions, publish learning materials, prepare assessments, and review the gradebook.</p><div class="workflow-links"><a href="{{ route('admin.attendance.index') }}">Attendance →</a><a href="{{ route('admin.materials.index') }}">Learning Materials →</a><a href="{{ route('admin.assessments.index') }}">Assessments →</a><a href="{{ route('admin.grades.index') }}">Grades →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">7</span>
                <div class="workflow-content"><span class="workflow-category">Coordination</span><h4>Communicate and use AI assistance</h4><p>Publish announcements, message authorized staff, and use SNAPIE AI for NSTP-focused guidance. Treat AI scoring as advisory and keep the authorized reviewer’s decision final.</p><div class="workflow-links"><a href="{{ route('admin.announcements.index') }}">Announcements →</a><a href="{{ route('admin.messages.index') }}">Messages →</a><a href="{{ route('ai-assistant.index') }}">AI Assistant →</a></div></div>
            </article>
            <article class="system-workflow-step">
                <span class="workflow-number">8</span>
                <div class="workflow-content"><span class="workflow-category">Accountability</span><h4>Report, audit, back up, and archive</h4><p>Export official summaries, inspect activity logs, create a restorable database backup, and archive records only after the term is complete.</p><div class="workflow-links"><a href="{{ route('admin.reports.index') }}">Reports →</a><a href="{{ route('admin.system-logs.index') }}">System Logs →</a><a href="{{ route('admin.database-backup.index') }}">Database Management →</a><a href="{{ route('admin.archives.index') }}">Records Archive →</a></div></div>
            </article>
        </div>
    </section>

    <section class="system-guide-section" aria-labelledby="role-guide-title">
        <div class="system-guide-heading"><div><span class="eyebrow">Access boundaries</span><h3 id="role-guide-title">Know what each role does</h3><p>Assign the least powerful role that still allows each person to complete their responsibilities.</p></div></div>
        <div class="system-role-grid">
            <article class="system-role-card super-admin"><span>SA</span><h4>Super Admin</h4><p>Full platform oversight, account recovery, system settings, reports, logs, backups, and archives.</p></article>
            <article class="system-role-card nstp-admin"><span>NA</span><h4>NSTP Admin</h4><p>Runs institution-wide NSTP operations and manages assignments, without Super Admin security controls.</p></article>
            <article class="system-role-card coordinator"><span>CO</span><h4>Coordinator</h4><p>Monitors an assigned component and scans student QR codes during authorized attendance sessions.</p></article>
            <article class="system-role-card facilitator"><span>FA</span><h4>Facilitator</h4><p>Manages assigned classes, materials, assessments, attendance, submissions, and grades.</p></article>
            <article class="system-role-card student"><span>ST</span><h4>Student</h4><p>Selects an NSTP component, joins assigned classes, views materials, submits work, and checks progress.</p></article>
        </div>
    </section>

    <section class="system-guide-section" aria-labelledby="safety-checklist-title">
        <div class="system-guide-heading"><div><span class="eyebrow">Before major changes</span><h3 id="safety-checklist-title">Super Admin safety checklist</h3><p>Use these checks to keep access and institutional data safe.</p></div></div>
        <div class="system-safety-grid">
            <article><span>✓</span><div><strong>Verify the academic term</strong><p>Check the active year and semester before sectioning, scheduling, or exporting reports.</p></div></article>
            <article><span>✓</span><div><strong>Confirm role and component</strong><p>Review staff permissions and assignments before sending their login credentials.</p></div></article>
            <article><span>✓</span><div><strong>Back up before bulk changes</strong><p>Create a database snapshot before restoring records or making major end-of-term changes.</p></div></article>
            <article><span>✓</span><div><strong>Review before permanent deletion</strong><p>Archive first, confirm the record group, and use the system log to preserve accountability.</p></div></article>
        </div>
    </section>

    <section class="card system-guide-help">
        <div><span class="eyebrow">Need help while working?</span><h3>Ask SNAPIE AI from any portal page.</h3><p>Use the floating AI button for guidance about NSTP workflows and system features. Do not include passwords, API keys, or unnecessary private student information.</p></div>
        <a class="secondary-button" href="{{ route('ai-assistant.index') }}">Open AI Assistant →</a>
    </section>
@endsection
