<div class="password-requirements full" aria-live="polite">
    <strong>Password requirements</strong>
    <ul>
        <li data-password-check="length"><span>○</span> At least 12 characters</li>
        <li data-password-check="uppercase"><span>○</span> At least one uppercase letter</li>
        <li data-password-check="lowercase"><span>○</span> At least one lowercase letter</li>
        <li data-password-check="number"><span>○</span> At least one number</li>
        <li data-password-check="symbol"><span>○</span> At least one symbol</li>
        <li data-password-check="match"><span>○</span> Passwords match</li>
    </ul>
</div>
<script src="{{ asset('js/password-requirements.js') }}?v={{ filemtime(public_path('js/password-requirements.js')) }}" defer></script>
