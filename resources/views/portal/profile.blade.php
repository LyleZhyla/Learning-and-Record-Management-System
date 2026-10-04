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

            @if($user->isFacilitator())
                @php($facilitatorProfile = $user->facilitatorProfile)
                <section class="facilitator-official-record">
                    <div class="facilitator-record-heading full"><div><span class="eyebrow">Official facilitator record</span><h3>Employment information</h3></div><p>Contact the Super Admin if an official field needs correction.</p></div>
                    <div><small>Employee number</small><strong>{{ $facilitatorProfile?->employee_number ?? 'Not provided' }}</strong></div>
                    <div><small>Department / unit</small><strong>{{ $facilitatorProfile?->department ?? 'Not provided' }}</strong></div>
                    <div><small>Designation</small><strong>{{ $facilitatorProfile?->designation ?? 'Not provided' }}</strong></div>
                    <div><small>Employment status</small><strong>{{ $facilitatorProfile?->employmentStatusLabel() ?? 'Not provided' }}</strong></div>
                </section>

                <label for="contact_number">Contact number</label>
                <input id="contact_number" name="contact_number" type="tel" value="{{ old('contact_number', $facilitatorProfile?->contact_number) }}" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX">
                @error('contact_number')<small class="field-error">{{ $message }}</small>@enderror

                <label for="specialization">Specialization</label>
                <input id="specialization" name="specialization" value="{{ old('specialization', $facilitatorProfile?->specialization) }}" maxlength="255" placeholder="e.g. Community development">
                @error('specialization')<small class="field-error">{{ $message }}</small>@enderror

                <label for="professional_summary">Professional summary</label>
                <textarea id="professional_summary" name="professional_summary" rows="4" maxlength="2000">{{ old('professional_summary', $facilitatorProfile?->professional_summary) }}</textarea>
                @error('professional_summary')<small class="field-error">{{ $message }}</small>@enderror
            @endif

            <div class="readonly-field"><span>Account role</span><strong>{{ $user->roleLabel() }}</strong></div>
            <div class="form-actions"><button class="primary-button compact" type="submit">Save profile</button></div>
        </form>
    </section>

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
