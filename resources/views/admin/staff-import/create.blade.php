@extends('layouts.admin')

@section('title', 'Import Staff Accounts')
@section('page-title', 'Bulk Staff Import')

@section('content')
    <div class="back-row"><a href="{{ route('admin.users.index') }}">← Back to staff accounts</a></div>

    <section class="page-actions student-import-heading">
        <div>
            <span class="eyebrow">Bulk staff accounts</span>
            <h2>Upload an Excel or CSV staff list</h2>
            <p>Create Facilitator, Coordinator, and NSTP Admin accounts together. The whole file is validated before any account is created.</p>
        </div>
        <a class="secondary-outline-button" href="{{ route('admin.users.import.template') }}">↓ Excel template</a>
    </section>

    @if ($errors->has('file'))
        <div class="alert danger" role="alert">{{ $errors->first('file') }}</div>
    @endif

    @if ($errors->has('import_rows'))
        <section class="alert danger import-error-summary" role="alert">
            <strong>Nothing was imported. Fix these rows and upload the file again:</strong>
            <ul>@foreach ($errors->get('import_rows') as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    <div class="student-import-grid">
        <section class="card import-upload-card">
            <div class="card-heading"><div><h3>Select the completed file</h3><p>Accepted formats: .xlsx, .xls, and .csv. Maximum size: 5 MB and 1,000 staff rows.</p></div></div>
            <form method="POST" action="{{ route('admin.users.import.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="student-import-dropzone">
                    <span class="import-file-icon" aria-hidden="true">▤</span>
                    <strong>Choose your staff data file</strong>
                    <small>The filename will appear here before you submit.</small>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required data-staff-import-file>
                    <em data-staff-import-name>No file selected</em>
                </label>
                <div class="import-delivery-actions" aria-label="Choose how to receive generated credentials">
                    <button class="primary-button compact" type="submit" name="credential_delivery" value="download">Import &amp; download credentials</button>
                    <button class="secondary-outline-button" type="submit" name="credential_delivery" value="view">Import &amp; view credentials</button>
                </div>
            </form>
        </section>

        <aside class="card import-guide-card">
            <div class="card-heading"><div><h3>Required columns</h3><p>Keep every template column name unchanged.</p></div></div>
            <ol class="import-column-list">
                <li><strong>name</strong><span>Full account holder name.</span></li>
                <li><strong>email</strong><span>Unique login email address.</span></li>
                <li><strong>role</strong><span>facilitator, coordinator, or nstp_admin.</span></li>
                <li><strong>component_code</strong><span>Active component code for Facilitators and Coordinators; blank for NSTP Admin.</span></li>
                <li><strong>contact_number</strong><span>Required for Facilitators; 11 digits beginning with 09.</span></li>
            </ol>
            <div class="import-security-note"><strong>No automatic email</strong><p>Temporary passwords are generated after validation and shown only in the selected result. Share them securely when ready.</p></div>
            <div class="import-security-note"><strong>Active components</strong><p>Available codes: {{ $components->pluck('code')->implode(', ') ?: 'No active components configured' }}.</p></div>
        </aside>
    </div>

    <script>
        document.querySelector('[data-staff-import-file]')?.addEventListener('change', function () {
            document.querySelector('[data-staff-import-name]').textContent = this.files[0]?.name || 'No file selected';
        });
    </script>
@endsection
