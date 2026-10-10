<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NSTP Serial Number Verifier · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/tau-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}"><x-theme-init />
</head>
<body class="auth-body">
<x-theme-toggle />
<main style="min-height:100vh;display:grid;place-items:center;padding:2rem">
    <div style="width:min(760px,100%)">
        <a class="brand" href="{{ route('login') }}" style="margin-bottom:1.5rem"><x-system-brand subtitle="Official NSTP Record Verification" /></a>
        <section class="card" style="padding:2rem">
            <div class="auth-heading" style="margin-bottom:1.5rem"><span class="eyebrow">Public verification service</span><h1 style="font-size:2rem">Verify an NSTP serial number</h1><p>Enter the complete serial number issued by CHED and recorded by the authorized NSTP office.</p></div>
            <form method="GET" action="{{ route('serial-numbers.verify') }}" class="auth-form">
                <label for="serial_number">NSTP serial number</label>
                <input id="serial_number" name="serial_number" value="{{ request('serial_number') }}" maxlength="100" autocomplete="off" required placeholder="Enter the complete serial number">
                <button class="primary-button" type="submit" style="margin-top:1rem">Verify official record <span>→</span></button>
            </form>
        </section>

        @if($searched)
            @if($serialRecord)
                <section class="card" style="margin-top:1rem;border-color:#9ad8c4">
                    <div class="card-heading"><div><span class="eyebrow">Verified official record</span><h3>Serial number is valid</h3><p>This record matches a serial number encoded from an official file received by the NSTP office.</p></div><span class="status-badge active"><i></i>Verified</span></div>
                    <dl class="visible-data-grid"><div><dt>Serial number</dt><dd>{{ $serialRecord->serial_number }}</dd></div><div><dt>Graduate</dt><dd>{{ $serialRecord->student->name }}</dd></div><div><dt>NSTP component</dt><dd>{{ $serialRecord->release->component->code }} — {{ $serialRecord->release->component->name }}</dd></div><div><dt>Academic year</dt><dd>{{ $serialRecord->release->academic_year }}</dd></div><div><dt>Semester</dt><dd>{{ \App\Models\NstpSection::SEMESTERS[$serialRecord->release->semester] ?? str($serialRecord->release->semester)->headline() }}</dd></div><div><dt>Office received record</dt><dd>{{ $serialRecord->release->received_at->format('M d, Y') }}</dd></div></dl>
                </section>
            @else
                <div class="alert danger" role="alert" style="margin-top:1rem"><strong>No matching official record.</strong> Check the complete serial number and try again, or contact the NSTP office for verification.</div>
            @endif
        @endif
        <p style="margin-top:1.25rem;text-align:center"><a class="text-link" href="{{ route('login') }}">Return to account sign in</a></p>
    </div>
</main>
<script src="{{ asset('js/theme.js') }}"></script>
</body></html>
