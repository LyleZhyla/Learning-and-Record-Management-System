@props(['subtitle' => 'Smart NSTP Management Platform'])

<span {{ $attributes->class(['system-brand']) }}>
    <span class="system-brand-logos" aria-hidden="true">
        <img src="{{ asset('images/branding/tau-logo.png') }}" alt="">
        <img src="{{ asset('images/branding/nstp-logo.png') }}" alt="">
    </span>
    <span class="system-brand-copy">
        <strong>TAU NSTP</strong>
        <small>{{ $subtitle }}</small>
    </span>
</span>
