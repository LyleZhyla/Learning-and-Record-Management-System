<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Required Documents · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <x-theme-init />
</head>
<body class="registration-body">
    <header class="registration-header">
        <img class="brand-landscape" src="{{ asset('images/snapie-landscape.png') }}" alt="SNAPIE — Smart NSTP Management and AI-Integrated Platform">
        <div class="registration-header-actions">
            <span>Signed in as {{ $user->email }}</span>
            <x-theme-toggle />
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary-outline-button" type="submit">Sign out</button></form>
        </div>
    </header>

    <main class="registration-shell">
        <aside class="registration-intro">
            <span class="eyebrow">Required account setup</span>
            <h1>Upload your student documents.</h1>
            <p>Your imported student information is ready. Submit both required files before opening the student portal.</p>
            <div class="registration-requirements">
                <div><span>✓</span><p><strong>Certificate of Registration</strong><small>PDF, JPG, JPEG, or PNG; maximum 5 MB.</small></p></div>
                <div><span>✓</span><p><strong>Formal photo</strong><small>JPG, JPEG, or PNG with a white background; maximum 3 MB.</small></p></div>
                <div><span>✓</span><p><strong>Private storage</strong><small>Your documents are stored privately and are not publicly accessible.</small></p></div>
            </div>
        </aside>

        <section class="registration-workspace">
            <div class="registration-heading"><div><span class="eyebrow">Student verification</span><h2>Complete your document requirements</h2></div></div>

            @if (session('warning'))
                <div class="alert danger" role="alert">{{ session('warning') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert danger registration-errors" role="alert"><strong>Please check your files.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form class="registration-panel" method="POST" action="{{ route('student.required-documents.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="section-title"><span>01</span><div><h3>Certificate of Registration</h3><p>Upload a clear and readable copy of your current COR.</p></div></div>
                <label class="upload-zone" data-required-upload-zone>
                    <input name="cor" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-required-upload>
                    <span class="upload-icon">▤</span>
                    <strong data-required-upload-name>Choose your COR file</strong>
                    <small>PDF, JPG, JPEG, or PNG · Maximum 5 MB</small>
                </label>

                <div class="section-title" style="margin-top:30px"><span>02</span><div><h3>Formal photo</h3><p>Use a clear formal picture with a white background.</p></div></div>
                <label class="upload-zone" data-required-upload-zone>
                    <input name="formal_photo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required data-required-upload>
                    <span class="upload-icon">◉</span>
                    <strong data-required-upload-name>Choose your formal photo</strong>
                    <small>JPG, JPEG, or PNG · Maximum 3 MB</small>
                </label>

                <div class="registration-actions"><button class="primary-button compact" type="submit">Upload files &amp; open portal <span>→</span></button></div>
            </form>
        </section>
    </main>

    <script>
        document.querySelectorAll('[data-required-upload]').forEach(function (input) {
            input.addEventListener('change', function () {
                const zone = input.closest('[data-required-upload-zone]');
                zone?.classList.toggle('ready', Boolean(input.files[0]));
                const name = zone?.querySelector('[data-required-upload-name]');
                if (name && input.files[0]) name.textContent = input.files[0].name;
            });
        });
    </script>
    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
