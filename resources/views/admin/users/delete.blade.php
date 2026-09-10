@extends('layouts.admin')

@section('title', 'Delete Account')
@section('page-title', 'Permanently Delete Account')

@section('content')
    <div class="back-row"><a href="{{ $user->isStudent() ? route('admin.students.index') : route('admin.users.index') }}">← Back to {{ $user->isStudent() ? 'student' : 'staff' }} accounts</a></div>

    <section class="card danger-zone-card">
        <div class="card-heading">
            <div>
                <span class="eyebrow">Danger zone</span>
                <h2>Delete {{ $user->name }} permanently?</h2>
                <p>This action cannot be undone. The {{ strtolower($user->roleLabel()) }} login, active sessions, and account-linked personal records will be removed.</p>
            </div>
        </div>

        <div class="account-heading">
            <span class="large-avatar small">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            <div><span class="role-badge role-{{ $user->role }}">{{ $user->roleLabel() }}</span><h3>{{ $user->name }}</h3><p>{{ $user->email }}</p></div>
        </div>

        @if($user->isStudent())
            <div class="alert danger" role="alert">The student's enrollment links, attendance entries, submissions, QR access, profile, and imported COR/formal photo will also be deleted.</div>
        @else
            <div class="alert danger" role="alert">Sections assigned to this account will become unassigned. Learning materials, assessments, attendance sessions, and OMR records they created will be preserved and reassigned to you.</div>
        @endif

        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="account-form compact-form">
            @csrf
            @method('DELETE')
            <label class="field-group">
                <span>Type <strong>{{ $user->email }}</strong> to confirm</span>
                <input type="email" name="confirmation" value="{{ old('confirmation') }}" autocomplete="off" required aria-describedby="confirmation-help">
                @error('confirmation')<small class="field-error">{{ $message }}</small>@enderror
                <small id="confirmation-help" class="form-help">The email must match exactly before the account can be deleted.</small>
            </label>
            <div class="form-actions">
                <a class="secondary-outline-button" href="{{ $user->isStudent() ? route('admin.students.index') : route('admin.users.index') }}">Cancel</a>
                <button class="danger-button" type="submit">Permanently delete account</button>
            </div>
        </form>
    </section>
@endsection
