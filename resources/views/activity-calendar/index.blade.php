@extends($layout)

@section('title', 'Activity Calendar')
@section('page-title', 'Activity Calendar')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/activity-calendar.css') }}?v={{ filemtime(public_path('css/activity-calendar.css')) }}">
@endpush

@section('content')
<section class="welcome-banner activity-calendar-hero">
    <div>
        <span class="eyebrow">{{ $eyebrow }}</span>
        <h2>Calendar of activities</h2>
        <p>{{ $description }}</p>
    </div>
    <img class="snapie-character snapie-banner-character" src="{{ asset('images/characters/snapie-wave.webp') }}" alt="SNAPIE mascot waving">
    <a class="secondary-button" href="{{ route($routePrefix.'.dashboard') }}">Back to dashboard</a>
</section>

<x-activity-calendar
    :calendar="$activityCalendar"
    :calendar-route="$routePrefix.'.activity-calendar'"
    :description="$description"
/>
@endsection
