@extends('layouts.student')
@section('title', 'Student ID')
@section('page-title', 'Student ID')

@section('content')
<div class="page-actions student-id-page-actions">
    <div><span class="eyebrow">Auto-generated identification</span><h2>Your SNAPIE Student ID</h2><p>The information and permanent QR below come directly from your student profile and current NSTP enrollment.</p></div>
    <button class="primary-button compact" type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

<section class="student-id-stage">
    <article class="student-id-card" aria-label="Auto-generated student identification card">
        <div class="student-id-accent"></div>
        <header class="student-id-header">
            <img src="{{ asset('images/snapie-logo-160.png') }}" alt="SNAPIE logo">
            <div><strong>SMART NSTP</strong><span>Management and AI-Integrated Platform</span></div>
            <b>STUDENT ID</b>
        </header>

        <div class="student-id-body">
            <div class="student-id-photo">
                @if($student->profile_photo_path)
                    <img src="{{ route('profile.photo', ['v' => $student->updated_at?->timestamp]) }}" alt="Profile photo of {{ $displayName }}">
                @else
                    <span>{{ strtoupper(substr($displayName, 0, 1)) }}</span>
                @endif
            </div>

            <div class="student-id-information">
                <span class="student-id-label">Full name</span>
                <h3>{{ $displayName }}</h3>
                <div class="student-id-fields">
                    <div><span>Student number</span><strong>{{ $details?->student_number ?: 'Not provided' }}</strong></div>
                    <div><span>NSTP component</span><strong>{{ $enrollment?->component?->code ?: 'Not assigned' }}</strong></div>
                    <div class="wide"><span>Course</span><strong>{{ $details?->course ?: 'Not provided' }}</strong></div>
                    <div class="wide"><span>Emergency contact</span><strong>{{ $details?->emergency_contact_name ?: 'Not provided' }}@if($details?->emergency_relationship) · {{ $details->emergency_relationship }}@endif</strong><small>{{ $details?->emergency_contact_number ?: 'Contact number not provided' }}</small></div>
                </div>
            </div>

            <div class="student-id-qr">
                {!! $qrSvg !!}
                <strong>SCAN FOR ATTENDANCE</strong>
                <small>Permanent student QR</small>
            </div>
        </div>

        <footer class="student-id-footer"><span>Valid while enrolled in SNAPIE</span><strong>{{ $enrollment?->academic_year ?: now()->format('Y') }}</strong></footer>
    </article>
</section>

@if(! $details || ! $enrollment)
    <div class="alert warning student-id-completion-note"><strong>Some ID information is incomplete.</strong> Update your profile and NSTP selection so all fields appear on your generated ID.</div>
@endif
@endsection
