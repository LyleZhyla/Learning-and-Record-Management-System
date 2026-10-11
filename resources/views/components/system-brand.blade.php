@props(['subtitle' => 'Smart NSTP Management Platform'])
<span {{ $attributes->class(['system-brand']) }}>
    <span class="system-brand-lockup" role="img" aria-label="SNAPIE">
        <img class="system-brand-logo system-brand-logo-light" src="{{ asset('images/snapie-landscape-light.png') }}" alt="">
        <img class="system-brand-logo system-brand-logo-dark" src="{{ asset('images/snapie-landscape-dark.png') }}" alt="">
    </span>
    <span class="system-brand-copy">
        <small>{{ $subtitle }}</small>
    </span>
</span>
