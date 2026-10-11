@props(['subtitle' => 'Smart NSTP Management Platform'])
@php($branding = \App\Models\SystemSetting::branding())

<span {{ $attributes->class(['system-brand']) }}>
    <span class="system-brand-logos" aria-hidden="true">
        <img src="{{ \App\Models\SystemSetting::brandingLogoUrl('organization') }}" alt="">
        <img src="{{ \App\Models\SystemSetting::brandingLogoUrl('program') }}" alt="">
    </span>
    <span class="system-brand-copy">
        <strong>{{ $branding['organization_short_name'] }} {{ $branding['program_short_name'] }}</strong>
        <small>{{ $subtitle }}</small>
    </span>
</span>
