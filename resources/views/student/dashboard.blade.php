@extends('layouts.student')
@section('title','Student Dashboard') @section('page-title','Dashboard')
@section('content')
<section class="welcome-banner student-dashboard-welcome"><div><span class="eyebrow">Student learning portal</span><h2>Welcome, {{ auth()->user()->name }}.</h2><p>
    @if($enrollment?->section)
        You are enrolled in {{ $enrollment->component->code }}, section {{ $enrollment->section->code }} for {{ $enrollment->section->semesterLabel() }} {{ $enrollment->academic_year }}.
    @elseif($enrollment)
        Your {{ $enrollment->component->code }} enrollment is approved and awaiting section assignment.
    @elseif($pendingEnrollment)
        Your {{ $pendingEnrollment->rotc_category }} request is pending ROTC coordinator approval.
    @else
        Your NSTP enrollment and section have not been assigned yet.
    @endif
</p></div><a class="secondary-button" href="{{ route('student.assessments.index') }}">View assessments</a></section><div class="metric-grid student-dashboard-metrics"><article class="metric-card"><span class="metric-icon blue">▣</span><div><small>ATTENDED SESSIONS</small><strong>{{ $stats['attendance'] }}</strong></div></article><article class="metric-card"><span class="metric-icon green">▤</span><div><small>LEARNING MATERIALS</small><strong>{{ $stats['materials'] }}</strong></div></article><article class="metric-card"><span class="metric-icon orange">✓</span><div><small>PENDING ASSESSMENTS</small><strong>{{ $stats['pending'] }}</strong></div></article><article class="metric-card"><span class="metric-icon violet">◎</span><div><small>COMPUTED GRADE</small><strong>{{ $stats['grade']===null?'—':number_format($stats['grade'],2) }}</strong></div></article></div><section class="card student-dashboard-guide"><div class="card-heading"><div><h3>How QR attendance works</h3><p>Sign in to your Student account, scan the QR displayed by your facilitator, and the system records the correct status automatically.</p></div></div><div class="quick-action-grid student-dashboard-actions"><a href="{{ route('student.attendance.index') }}"><strong>Attendance history</strong><small>Review Present, Late, and Absent records.</small></a><a href="{{ route('student.materials.index') }}"><strong>Learning materials</strong><small>Open resources for your component and section.</small></a><a href="{{ route('student.grades.index') }}"><strong>Grade summary</strong><small>Track scores and weighted performance.</small></a></div></section>
@endsection
