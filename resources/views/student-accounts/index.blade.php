@extends($layout)

@section('title', 'Student Accounts')
@section('page-title', 'Student Account Management')

@section('content')
<section class="page-actions">
    <div>
        <span class="eyebrow">Student directory</span>
        <h2>Manage student accounts and QR codes</h2>
        <p>Imported students appear here automatically with their permanent attendance QR code.</p>
    </div>
    <div class="page-action-buttons">
        <a class="import-students-button" href="{{ route($routePrefix.'.students.import.create') }}" aria-label="Import students from Excel"><span aria-hidden="true">⇧</span> Import Students</a>
        @if($routePrefix === 'admin')<a class="primary-button compact" href="{{ route('admin.users.create', ['role' => 'student']) }}">+ Create student</a>@endif
    </div>
</section>

@if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert danger" role="alert">{{ $errors->first() }}</div>@endif

<section class="student-account-summary" aria-label="Student account summary">
    <a class="role-summary {{ empty($filters['status']) ? 'selected' : '' }}" href="{{ route($routePrefix.'.students.index') }}"><span class="role-dot role-student"></span><div><strong>{{ $activeCount + $inactiveCount }}</strong><small>All students</small></div></a>
    <a class="role-summary {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}" href="{{ route($routePrefix.'.students.index', ['status' => 'active']) }}"><span class="status-badge active"><i></i></span><div><strong>{{ $activeCount }}</strong><small>Active</small></div></a>
    <a class="role-summary {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}" href="{{ route($routePrefix.'.students.index', ['status' => 'inactive']) }}"><span class="status-badge inactive"><i></i></span><div><strong>{{ $inactiveCount }}</strong><small>Inactive</small></div></a>
</section>

<section class="card user-table-card">
    <form class="filter-bar" method="GET" action="{{ route($routePrefix.'.students.index') }}">
        <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search student name or email"></label>
        <select name="status" aria-label="Filter by account status"><option value="">All statuses</option>@foreach(\App\Models\User::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <button class="filter-button" type="submit">Apply filters</button>
        @if(request()->hasAny(['search', 'status']))<a class="clear-filter" href="{{ route($routePrefix.'.students.index') }}">Clear</a>@endif
    </form>

    @if($routePrefix === 'nstp_admin')
    <form method="POST" action="{{ route('nstp_admin.accounts.students.component.bulk') }}" id="bulk-student-component-form">
        @csrf
        <div class="enrollment-action-bar bulk-account-action-bar">
            <div class="bulk-account-action-copy"><strong>Bulk student component assignment</strong><span>Current term: {{ $semesterLabel }} {{ $academicYear }}</span></div>
            <label class="field-group"><span>Assign selected students to</span><select name="nstp_component_id" data-bulk-component-select required><option value="">Choose a component</option>@foreach($availableComponents as $component)<option value="{{ $component->id }}" data-component-code="{{ $component->code }}" @selected((int) old('nstp_component_id') === $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
            <label class="field-group" data-bulk-rotc-level hidden><span>ROTC MS level</span><select name="rotc_category" data-bulk-rotc-level-select disabled><option value="">Choose an MS level</option>@foreach($rotcCategories as $value => $label)<option value="{{ $value }}" @selected(old('rotc_category') === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="filter-button" type="submit">Assign selected students</button>
        </div>
    @endif

    <div class="table-wrap"><table class="data-table student-account-table">
        <thead><tr>@if($routePrefix === 'nstp_admin')<th class="check-column"><input type="checkbox" data-select-all-students aria-label="Select all active students on this page"></th>@endif<th>Student</th><th>Attendance QR</th><th>Component</th><th>Status</th><th>Last sign in</th><th class="align-right">Action</th></tr></thead>
        <tbody>@forelse($students as $student)
            @php($enrollment = $student->latestNstpEnrollment)
            <tr>
                @if($routePrefix === 'nstp_admin')<td class="check-column">@if($student->isActive())<input class="student-check" type="checkbox" name="student_ids[]" value="{{ $student->id }}" @checked(in_array($student->id, old('student_ids', []))) aria-label="Select {{ $student->name }}">@else<span class="muted-cell">—</span>@endif</td>@endif
                <td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($student->name, 0, 1)) }}</span><div><strong>{{ $student->name }}</strong><small>{{ $student->email }}</small></div></div></td>
                <td><div class="student-qr-cell"><button type="button" data-student-qr-preview data-qr-url="{{ route($routePrefix.'.students.qr', $student) }}" data-student-name="{{ $student->name }}" aria-label="Preview attendance QR for {{ $student->name }}"><span aria-hidden="true">▦</span> View QR</button><a href="{{ route($routePrefix.'.students.qr.download', $student) }}">Download QR</a></div></td>
                <td>@if($enrollment?->component)<span class="component-mini-badge">{{ $enrollment->component->code }}</span><small class="student-term-label">{{ $enrollment->academic_year }}</small>@else<span class="muted-cell">Not assigned</span>@endif</td>
                <td><span class="status-badge {{ $student->status }}"><i></i>{{ $student->statusLabel() }}</span></td>
                <td class="muted-cell">{{ $student->last_login_at?->format('M d, Y · h:i A') ?? 'Never' }}</td>
                <td class="align-right"><a class="table-action" href="{{ $routePrefix === 'admin' ? route('admin.users.edit', $student) : route('nstp_admin.accounts.show', $student) }}">{{ $routePrefix === 'admin' ? 'Manage' : 'View records' }} →</a></td>
            </tr>
        @empty<tr><td colspan="{{ $routePrefix === 'nstp_admin' ? 7 : 6 }}"><div class="empty-state"><strong>No student accounts found</strong><span>Import a student list or change the filters.</span></div></td></tr>@endforelse</tbody>
    </table></div>

    @if($routePrefix === 'nstp_admin')</form>@endif
    @if($students->hasPages())<div class="pagination-row"><span>Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }}</span>{{ $students->links() }}</div>@endif
</section>

<dialog class="student-qr-dialog" data-student-qr-dialog>
    <div class="student-qr-dialog-heading"><div><span class="eyebrow">Permanent attendance code</span><strong data-student-qr-name>Student QR</strong></div><button type="button" data-student-qr-close aria-label="Close QR preview">×</button></div>
    <div class="student-qr-dialog-image"><span data-student-qr-loading>Generating QR…</span><img data-student-qr-image alt="" hidden></div>
</dialog>

<script src="{{ asset('js/student-qr-preview.js') }}"></script>

@if($routePrefix === 'nstp_admin')
<script>
    const bulkStudentForm = document.querySelector('#bulk-student-component-form');
    const bulkActionBar = bulkStudentForm?.querySelector('.bulk-account-action-bar');
    const selectAllStudents = bulkStudentForm?.querySelector('[data-select-all-students]');
    const studentChecks = [...(bulkStudentForm?.querySelectorAll('.student-check') || [])];
    const bulkComponentSelect = bulkStudentForm?.querySelector('[data-bulk-component-select]');
    const bulkRotcLevel = bulkStudentForm?.querySelector('[data-bulk-rotc-level]');
    const bulkRotcLevelSelect = bulkStudentForm?.querySelector('[data-bulk-rotc-level-select]');
    const syncBulkRotcLevel = () => {
        const isRotc = bulkComponentSelect?.selectedOptions[0]?.dataset.componentCode === 'ROTC';
        bulkActionBar?.classList.toggle('rotc-selected', isRotc);
        bulkRotcLevel.hidden = !isRotc;
        bulkRotcLevelSelect.disabled = !isRotc;
        bulkRotcLevelSelect.required = isRotc;
        if (!isRotc) bulkRotcLevelSelect.value = '';
    };
    bulkComponentSelect?.addEventListener('change', syncBulkRotcLevel);
    if (bulkComponentSelect) syncBulkRotcLevel();
    selectAllStudents?.addEventListener('change', function () { studentChecks.forEach((checkbox) => checkbox.checked = this.checked); });
    studentChecks.forEach((checkbox) => checkbox.addEventListener('change', () => {
        selectAllStudents.checked = studentChecks.length > 0 && studentChecks.every((item) => item.checked);
        selectAllStudents.indeterminate = studentChecks.some((item) => item.checked) && !selectAllStudents.checked;
    }));
</script>
@endif
@endsection
