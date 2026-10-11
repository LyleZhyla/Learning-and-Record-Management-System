@props(['subtitle' => 'Smart NSTP Management Platform'])
<span {{ $attributes->class(['system-brand']) }}>
    <span class="system-brand-mark" aria-hidden="true">
        <img src="{{ asset('images/snapie-logo-160.png') }}" alt="">
    </span>
    <span class="system-brand-copy">
        <strong class="system-brand-name">SNAPIE</strong>
        <small>{{ $subtitle }}</small>
    </span>
</span>
