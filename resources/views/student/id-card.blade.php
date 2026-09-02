@extends('layouts.student')
@section('title', 'Student ID')
@section('page-title', 'Student ID')

@section('content')
<div class="page-actions student-id-page-actions">
    <div><span class="eyebrow">Auto-generated identification</span><h2>Your SNAPIE Student ID</h2><p>The information and permanent QR below come directly from your student profile and current NSTP enrollment.</p></div>
    <button class="primary-button compact" type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

<section class="student-id-stage">
    @include('student._id-card')
</section>

@if(! $details || ! $enrollment)
    <div class="alert warning student-id-completion-note"><strong>Some ID information is incomplete.</strong> Update your profile and NSTP selection so all fields appear on your generated ID.</div>
@endif
@endsection
