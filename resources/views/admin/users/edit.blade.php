@extends('layouts.admin')

@section('title', 'Manage Account')
@section('page-title', 'Manage User Account')

@section('content')
    <div class="back-row"><a href="{{ $user->isStudent() ? route('admin.students.index') : route('admin.users.index') }}">← Back to {{ $user->isStudent() ? 'student' : 'staff' }} accounts</a></div>

    @if ($errors->any())
        <div class="alert danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="editor-grid">
        <section class="card">
            <div class="account-heading">
                <span class="large-avatar small">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <div><span class="eyebrow">Account #{{ $user->id }}</span><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p></div>
            </div>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="account-form">
                @csrf @method('PUT')
                @include('admin.users._form', ['user' => $user])
                <div class="form-actions"><button class="primary-button compact" type="submit">Save changes</button></div>
            </form>
        </section>

        <div class="side-stack">
            <section class="card">
                <div class="card-heading"><div><span class="eyebrow">Access control</span><h3>Account status</h3><p>Inactive users cannot sign in. Their active sessions will be ended.</p></div></div>
                <div class="status-control"><span class="status-badge {{ $user->status }}"><i></i>{{ $user->statusLabel() }}</span>
                    <form method="POST" action="{{ route('admin.users.status', $user) }}">@csrf @method('PATCH')
                        <button class="{{ $user->isActive() ? 'danger-button' : 'success-button' }}" type="submit" @disabled(auth()->user()->is($user))>{{ $user->isActive() ? 'Deactivate account' : 'Activate account' }}</button>
                    </form>
                </div>
                @if (auth()->user()->is($user)) <p class="form-help">You cannot deactivate the account you are currently using.</p> @endif
            </section>

            <section class="card">
                <div class="card-heading"><div><span class="eyebrow">Security</span><h3>Reset password</h3><p>This ends the user's active sessions and requires a password change on next login.</p></div></div>
                <form method="POST" action="{{ route('admin.users.password', $user) }}" class="account-form compact-form" data-password-rules>
                    @csrf @method('PUT')
                    <label class="field-group"><span>New temporary password</span><input type="password" name="password" autocomplete="new-password" minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}" required></label>
                    <label class="field-group"><span>Confirm password</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
                    <x-password-requirements />
                    <div class="form-actions"><button class="secondary-outline-button" type="submit" disabled>Reset password</button></div>
                </form>
            </section>

            <section class="card danger-zone-card">
                <div class="card-heading"><div><span class="eyebrow">Danger zone</span><h3>Delete account</h3><p>Permanently removes this user's login and personal records. Existing institutional content they created will be preserved under your account.</p></div></div>
                @if(auth()->user()->is($user))
                    <button class="danger-button" type="button" disabled>You cannot delete your own account</button>
                @else
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Permanently delete this account? This cannot be undone.')">@csrf @method('DELETE')<button class="danger-button" type="submit">Delete account permanently</button></form>
                @endif
            </section>
        </div>
    </div>
@endsection
