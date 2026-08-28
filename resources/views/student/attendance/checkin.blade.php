@extends('layouts.student')
@section('title','QR Check-in') @section('page-title','QR Attendance Check-in')
@section('content')
<section class="card checkin-result"><span class="metric-icon {{ $record?'green':'orange' }}">{{ $record?'✓':'!' }}</span>@if($record)<span class="eyebrow">Check-in successful</span><h2>You are marked {{ ucfirst($record->status) }}.</h2><p>{{ $session->title }} · {{ $session->section->code }} · {{ $record->checked_in_at->format('M d, Y g:i:s A') }}</p>@else<span class="eyebrow">Unable to check in</span><h2>{{ $message }}</h2><p>{{ $session->title }} · {{ $session->section->code }}</p>@endif<a class="primary-button compact" href="{{ route('student.attendance.index') }}">View attendance history</a></section>
@endsection
