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

    <section class="card" id="password">
        <div class="card-heading"><div><span class="eyebrow">Authentication</span><h3>Change password</h3><p>Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</p></div></div>
        <form method="POST" action="{{ route($routePrefix.'.password.update') }}" class="settings-form">
            @csrf
            @method('PUT')
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            @error('current_password')<small class="field-error">{{ $message }}</small>@enderror

            <label for="password">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            @error('password')<small class="field-error">{{ $message }}</small>@enderror

            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <div class="form-actions"><button class="primary-button compact" type="submit">Update password</button></div>
        </form>
    </section>
</div>
@endsection
