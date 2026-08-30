@extends($layout)
@section('title', 'Profile & Security')
@section('page-title', 'Profile & Security')

@section('content')
<div class="profile-grid">
    <section class="card">
        <div class="card-heading"><div><span class="eyebrow">Account information</span><h3>{{ $user->roleLabel() }} profile</h3><p>Keep your account details and profile picture current.</p></div></div>
        <form method="POST" action="{{ route($routePrefix.'.profile.update') }}" class="settings-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <x-profile-photo-field :user="$user" />

            <label for="name">Full name</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100">
            @error('name')<small class="field-error">{{ $message }}</small>@enderror

            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            @error('email')<small class="field-error">{{ $message }}</small>@enderror

            <div class="readonly-field"><span>Account role</span><strong>{{ $user->roleLabel() }}</strong></div>
            <div class="form-actions"><button class="primary-button compact" type="submit">Save profile</button></div>
        </form>
    </section>

    @if($user->isStudent())
    <section class="card student-component-card">
        <div class="card-heading"><div><span class="eyebrow">NSTP enrollment</span><h3>Choose your component</h3><p>Select your NSTP component for {{ $semesterLabel }} {{ $academicYear }}.</p></div></div>
        <form method="POST" action="{{ route('student.component.update') }}" class="settings-form">
            @csrf
            @method('PUT')
            <label for="nstp_component_id">NSTP component</label>
            <select id="nstp_component_id" name="nstp_component_id" required>
                <option value="">Choose CWTS, LTS, or ROTC</option>
                @foreach($availableComponents as $component)
                    <option value="{{ $component->id }}" @selected((int) old('nstp_component_id', $currentEnrollment?->component_id) === $component->id)>{{ $component->code }} — {{ $component->name }}</option>
                @endforeach
            </select>
            @error('nstp_component_id')<small class="field-error">{{ $message }}</small>@enderror

            <div class="component-selection-status">
                <span>Current assignment</span>
                <strong>{{ $currentEnrollment?->component?->code ?? 'Not selected' }}</strong>
                <small>{{ $currentEnrollment?->section ? 'Section '.$currentEnrollment->section->code : 'Section will be assigned by the NSTP Admin.' }}</small>
            </div>
            <p class="form-help">Changing your component removes your current section assignment. The NSTP Admin will place you in an appropriate section.</p>
            <div class="form-actions"><button class="primary-button compact" type="submit">Save component selection</button></div>
        </form>
    </section>
    @endif

    <section class="card" id="password">
        <div class="card-heading"><div><span class="eyebrow">Authentication</span><h3>Change password</h3><p>Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</p></div></div>
        <form method="POST" action="{{ route($routePrefix.'.password.update') }}" class="settings-form" data-password-rules>
            @csrf
            @method('PUT')
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            @error('current_password')<small class="field-error">{{ $message }}</small>@enderror

            <label for="new_password">New password</label>
            <input id="new_password" name="password" type="password" autocomplete="new-password" minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}" required>
            @error('password')<small class="field-error">{{ $message }}</small>@enderror

            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <x-password-requirements />
            <div class="form-actions"><button class="primary-button compact" type="submit" disabled>Update password</button></div>
        </form>
    </section>
</div>
@endsection
