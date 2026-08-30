@extends($layout)

@section('title', 'Import Students')
@section('page-title', 'Import Students from Excel')

@section('content')
    <div class="back-row"><a href="{{ route($backRoute) }}">← Back to account directory</a></div>

    <section class="page-actions student-import-heading">
        <div>
            <span class="eyebrow">Bulk student accounts</span>
            <h2>Upload an Excel student list</h2>
            <p>The entire file is checked first. No account is saved until every row passes validation.</p>
        </div>
        <a class="secondary-outline-button" href="{{ route($routePrefix.'.students.import.template') }}">↓ Download Excel template</a>
    </section>

    @if (session('status'))
        <div class="alert success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->has('file'))
        <div class="alert danger" role="alert">{{ $errors->first('file') }}</div>
    @endif

    @if ($errors->has('import_rows'))
        <section class="alert danger import-error-summary" role="alert">
            <strong>Nothing was imported. Fix these rows and upload the file again:</strong>
            <ul>
                @foreach ($errors->get('import_rows') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="student-import-grid">
        <section class="card import-upload-card">
            <div class="card-heading"><div><h3>Select the completed file</h3><p>Accepted formats: .xlsx, .xls, and .csv. Maximum size: 5 MB and 1,000 student rows.</p></div></div>

            <form method="POST" action="{{ route($routePrefix.'.students.import.store') }}" enctype="multipart/form-data">
                @csrf
                <label class="student-import-dropzone">
                    <span class="import-file-icon" aria-hidden="true">▤</span>
                    <strong>Choose your student spreadsheet</strong>
                    <small>The filename will appear here before you submit.</small>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required data-student-import-file>
                    <em data-student-import-name>No file selected</em>
                </label>
                <div class="form-actions"><button class="primary-button compact" type="submit">Validate and import students</button></div>
            </form>
        </section>

        <aside class="card import-guide-card">
            <div class="card-heading"><div><h3>Required columns</h3><p>Keep the column names from the template unchanged.</p></div></div>
            <ol class="import-column-list">
                <li><strong>full_name</strong><span>Student's complete name</span></li>
                <li><strong>email</strong><span>Unique login email</span></li>
                <li><strong>temporary_password</strong><span>12+ characters, mixed case, number, symbol</span></li>
                <li><strong>status</strong><span>active or inactive</span></li>
                <li><strong>component_code</strong><span>Optional CWTS, ROTC, or LTS enrollment</span></li>
                <li><strong>academic_year / semester</strong><span>Required when a component is supplied</span></li>
                <li><strong>section_code</strong><span>Optional existing section assignment</span></li>
            </ol>
            <div class="import-security-note"><strong>Temporary password protection</strong><p>Every imported student must change their temporary password on first sign-in.</p></div>
        </aside>
    </div>

    <script>
        document.querySelector('[data-student-import-file]')?.addEventListener('change', function () {
            document.querySelector('[data-student-import-name]').textContent = this.files[0]?.name || 'No file selected';
        });
    </script>
@endsection
