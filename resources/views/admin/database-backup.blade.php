@extends('layouts.admin')
@section('title', 'Database Backup')
@section('page-title', 'Database Backup')

@section('content')
<div class="page-actions">
    <div><span class="eyebrow">Administration</span><h2>Database backup</h2><p>Download a restorable SQL copy of the current SNAPIE database.</p></div>
</div>

<div class="settings-security-grid">
    <section class="card inactivity-setting-card">
        <div class="card-heading"><div><h3>Create a fresh backup</h3><p>The download contains the database table structures and their current records.</p></div><span class="settings-clock">⇩</span></div>

        <div class="settings-impact-note"><span>!</span><p><strong>Store backups securely</strong>The file can contain student profiles, attendance, grades, and account information. Do not upload it to a public drive or repository.</p></div>

        <form method="POST" action="{{ route('admin.database-backup.download') }}">
            @csrf
            <div class="form-actions"><button class="primary-button compact">Download SQL backup</button></div>
        </form>
    </section>

    <aside class="card settings-summary-card">
        <span class="eyebrow">Active database</span>
        <strong>{{ $database['tables'] }}</strong>
        <h3>tables included</h3>
        <p>A new backup is generated from the live database each time the download button is used.</p>
        <dl>
            <div><dt>Database</dt><dd>{{ $database['name'] }}</dd></div>
            <div><dt>Driver</dt><dd>{{ strtoupper($database['driver']) }}</dd></div>
            <div><dt>Format</dt><dd>SQL</dd></div>
            <div><dt>Generated</dt><dd>On demand</dd></div>
        </dl>
    </aside>
</div>
@endsection
