@extends('layouts.admin')

@section('title', 'Create Account')
@section('page-title', 'Create User Account')

@section('content')
    <div class="back-row"><a href="{{ $initialRole === 'student' ? route('admin.students.index') : route('admin.users.index') }}">← Back to {{ $initialRole === 'student' ? 'student' : 'staff' }} accounts</a></div>
    <div class="editor-grid">
        <section class="card">
            <div class="card-heading"><div><span class="eyebrow">New {{ \App\Models\User::ROLE_LABELS[$initialRole] }} account</span><h3>Account information</h3><p>Create the login credentials and assign the correct NSTP responsibility.</p></div></div>
            <form method="POST" action="{{ route('admin.users.store') }}" class="account-form" data-password-rules data-staff-account-form>
                @csrf
                @include('admin.users._form', ['user' => null])
                <div class="form-actions split-actions">
                    <a class="cancel-button" href="{{ $initialRole === 'student' ? route('admin.students.index') : route('admin.users.index') }}">Cancel</a>
                    <button class="primary-button compact" type="submit" disabled>Create account</button>
                </div>
            </form>
        </section>
        <aside class="card role-guide">
            <span class="eyebrow">Access guide</span><h3>Available roles</h3>
            @foreach (collect(\App\Models\User::ROLE_LABELS)->only(['nstp_admin', 'coordinator', 'facilitator']) as $role => $label)
                <div class="role-guide-item"><span class="role-dot role-{{ $role }}"></span><div><strong>{{ $label }}</strong><p>{{ match($role) { 'student' => 'Access learning materials, activities, attendance, and grades.', 'facilitator' => 'Manage assigned sections, lessons, assessments, and grades.', 'coordinator' => 'Monitor components, sections, facilitators, and reports.', 'nstp_admin' => 'Manage institution-wide NSTP operations and records.', default => 'Full platform configuration and account administration.' } }}</p></div></div>
            @endforeach
        </aside>
    </div>
    <script src="{{ asset('js/staff-account-form.js') }}?v={{ filemtime(public_path('js/staff-account-form.js')) }}"></script>
@endsection
