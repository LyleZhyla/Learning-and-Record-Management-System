<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Community Feedback · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/tau-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="auth-body"><main class="auth-shell" style="max-width:850px;margin:2rem auto"><section class="auth-panel" style="display:block"><div class="auth-form-wrap" style="max-width:none">
    <a class="brand auth-brand" href="{{ url('/') }}"><x-system-brand subtitle="Community Feedback Survey" /></a>
    <span class="eyebrow">{{ $project->reference_number }} · {{ $project->component->code }}</span><h1>{{ $project->title }}</h1><p>Please rate the NSTP community project from 1 (lowest) to 5 (highest). Your name is optional.</p>
    @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('community-feedback.store', [$project, $token]) }}" class="stack-form">@csrf
        <label class="field-group"><span>Name (optional)</span><input name="respondent_name" value="{{ old('respondent_name') }}" maxlength="255"></label>
        <label class="field-group"><span>Relationship to the project</span><input name="respondent_relationship" value="{{ old('respondent_relationship') }}" required maxlength="255" placeholder="e.g. beneficiary, parent, barangay official"></label>
        @foreach($criteria as $key => $label)<label class="field-group"><span>{{ $label }}</span><select name="ratings[{{ $key }}]" required><option value="">Choose 1–5</option>@for($score = 5; $score >= 1; $score--)<option value="{{ $score }}" @selected((string)old('ratings.'.$key) === (string)$score)>{{ $score }}</option>@endfor</select></label>@endforeach
        <label class="field-group"><span>Comments and recommendations</span><textarea name="comments" rows="5" maxlength="3000">{{ old('comments') }}</textarea></label>
        <button class="primary-button" type="submit">Submit community feedback</button>
    </form>
</div></section></main></body></html>
