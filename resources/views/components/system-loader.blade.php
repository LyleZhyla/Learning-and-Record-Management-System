<div class="system-loader" data-system-loader hidden aria-hidden="true">
    <div class="system-loader-card" role="status" aria-live="polite" aria-label="Loading the requested page">
        <div class="system-loader-visual">
            <span class="system-loader-orbit" aria-hidden="true"></span>
            <img src="{{ asset('images/characters/snapie-loading.png') }}" alt="" aria-hidden="true">
        </div>
        <strong>Sandali lang!</strong>
        <span>Inaayos ni SNAPIE ang susunod mong page</span>
        <div class="system-loader-dots" aria-hidden="true"><i></i><i></i><i></i></div>
    </div>
</div>
<script src="{{ asset('js/system-loader.js') }}?v={{ filemtime(public_path('js/system-loader.js')) }}"></script>
