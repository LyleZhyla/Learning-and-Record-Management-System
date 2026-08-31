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
            <label class="field-group"><span>Assign selected students to</span><select name="nstp_component_id" required><option value="">Choose a component</option>@foreach($availableComponents as $component)<option value="{{ $component->id }}" @selected((int) old('nstp_component_id') === $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
            <button class="filter-button" type="submit">Assign selected students</button>
        </div>
    @endif

    <div class="table-wrap"><table class="data-table student-account-table">
        <thead><tr>@if($routePrefix === 'nstp_admin')<th class="check-column"><input type="checkbox" data-select-all-students aria-label="Select all active students on this page"></th>@endif<th>Student</th><th>Attendance QR</th><th>Component</th><th>Status</th><th>Last sign in</th><th class="align-right">Action</th></tr></thead>
        <tbody>@forelse($students as $student)
            @php($enrollment = $student->nstpEnrollments->first())
            <tr>
                @if($routePrefix === 'nstp_admin')<td class="check-column">@if($student->isActive())<input class="student-check" type="checkbox" name="student_ids[]" value="{{ $student->id }}" @checked(in_array($student->id, old('student_ids', []))) aria-label="Select {{ $student->name }}">@else<span class="muted-cell">—</span>@endif</td>@endif
                <td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($student->name, 0, 1)) }}</span><div><strong>{{ $student->name }}</strong><small>{{ $student->email }}</small></div></div></td>
                <td><div class="student-qr-cell"><a href="{{ route($routePrefix.'.students.qr', $student) }}" target="_blank" title="Open {{ $student->name }} QR"><img src="{{ route($routePrefix.'.students.qr', $student) }}" alt="Attendance QR for {{ $student->name }}"></a><a href="{{ route($routePrefix.'.students.qr.download', $student) }}">Download QR</a></div></td>
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

@if($routePrefix === 'nstp_admin')
<script>
    const bulkStudentForm = document.querySelector('#bulk-student-component-form');
    const selectAllStudents = bulkStudentForm?.querySelector('[data-select-all-students]');
    const studentChecks = [...(bulkStudentForm?.querySelectorAll('.student-check') || [])];
    selectAllStudents?.addEventListener('change', function () { studentChecks.forEach((checkbox) => checkbox.checked = this.checked); });
    studentChecks.forEach((checkbox) => checkbox.addEventListener('change', () => {
        selectAllStudents.checked = studentChecks.length > 0 && studentChecks.every((item) => item.checked);
        selectAllStudents.indeterminate = studentChecks.some((item) => item.checked) && !selectAllStudents.checked;
    }));
</script>
@endif
@endsection
