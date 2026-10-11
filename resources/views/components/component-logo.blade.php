@props(['componentCode', 'logoPath' => null])

@php
    $normalizedCode = strtoupper((string) $componentCode);
    $logoFiles = [
        'CWTS' => 'cwts-logo.png',
        'ROTC' => 'rotc-logo.png',
        'LTS' => 'lts-logo.png',
    ];
@endphp

<span {{ $attributes->class(['nstp-component-logo']) }}>
    @if ($logoPath || isset($logoFiles[$normalizedCode]))
        <img src="{{ $logoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) : asset('images/branding/'.$logoFiles[$normalizedCode]) }}" alt="{{ $normalizedCode }} logo">
    @else
        <span aria-hidden="true">{{ substr($normalizedCode, 0, 1) }}</span>
    @endif
</span>
