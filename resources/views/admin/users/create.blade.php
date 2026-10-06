@extends('layouts.admin')

@section('title', 'Create Account')
@section('page-title', 'Create User Account')

@section('content')
    <div class="back-row"><a href="{{ $initialRole === 'student' ? route('admin.students.index') : route('admin.users.index') }}">← Back to {{ $initialRole === 'student' ? 'student' : 'staff' }} accounts</a></div>
    <div class="compact-account-editor">
        <section class="card compact-account-card">
            <div class="card-heading"><div><span class="eyebrow">New {{ $initialRole === 'student' ? 'student' : 'staff' }} account</span><h3>Account information</h3><p>{{ $initialRole === 'student' ? 'Create the student login account.' : 'Select the account role.' }} A secure temporary password will be generated automatically.</p></div></div>
            <form method="POST" action="{{ route('admin.users.store') }}" class="account-form" data-staff-account-form>
                @csrf
                @include('admin.users._form', ['user' => null])
                <div class="form-actions split-actions">
                    <a class="cancel-button" href="{{ $initialRole === 'student' ? route('admin.students.index') : route('admin.users.index') }}">Cancel</a>
                    <button class="primary-button compact create-account-button" type="submit">Create {{ $initialRole === 'student' ? 'student' : 'staff' }} account</button>
                </div>
            </form>
        </section>
    </div>
    <script src="{{ asset('js/staff-account-form.js') }}?v={{ filemtime(public_path('js/staff-account-form.js')) }}"></script>
@endsection
