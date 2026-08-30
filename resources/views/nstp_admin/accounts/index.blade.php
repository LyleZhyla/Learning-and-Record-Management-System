@extends('layouts.nstp-admin')
@section('title', 'Account Directory')
@section('page-title', 'Account Directory')

@section('content')
<section class="page-actions">
    <div>
        <span class="eyebrow">NSTP account assignments</span>
        <h2>Coordinators, facilitators, and students</h2>
        <p>Assign staff components, bulk-assign students, import student accounts, and view student records. Password concerns must still be handled by the Super Admin.</p>
    </div>
    <a class="import-students-button" href="{{ route('nstp_admin.students.import.create') }}" aria-label="Import students from Excel"><span aria-hidden="true">⇧</span> Import Students</a>
</section>

@if (session('status'))
    <div class="alert success" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert danger" role="alert">{{ $errors->first() }}</div>
@endif

<section class="role-summary-grid nstp-account-role-grid" aria-label="Visible accounts by role">
    @foreach(['coordinator' => 'Coordinators', 'facilitator' => 'Facilitators', 'student' => 'Students'] as $role => $label)
        <a class="role-summary {{ request('role') === $role ? 'selected' : '' }}" href="{{ route('nstp_admin.accounts.index', ['role' => $role]) }}">
            <span class="role-dot role-{{ $role }}"></span>
            <div><strong>{{ $roleCounts[$role] ?? 0 }}</strong><small>{{ $label }}</small></div>
        </a>
    @endforeach
</section>

<section class="card password-boundary-note"><span>🔒</span><div><strong>Password access is restricted</strong><p>NSTP Admin cannot view, change, or reset passwords. Direct the user to the Super Admin when account recovery is needed.</p></div></section>

<section class="card user-table-card">
    <form class="filter-bar" method="GET">
        <label class="search-field"><span>⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email"></label>
        <select name="role">
            <option value="">All visible roles</option>
            <option value="coordinator" @selected(($filters['role'] ?? '') === 'coordinator')>Coordinator</option>
            <option value="facilitator" @selected(($filters['role'] ?? '') === 'facilitator')>Facilitator</option>
            <option value="student" @selected(($filters['role'] ?? '') === 'student')>Student</option>
        </select>
        <button class="filter-button">Apply filters</button>
        @if(request()->hasAny(['search', 'role']))<a class="clear-filter" href="{{ route('nstp_admin.accounts.index') }}">Clear</a>@endif
    </form>

    <form method="POST" action="{{ route('nstp_admin.accounts.students.component.bulk') }}" id="bulk-student-component-form">
        @csrf
        <div class="enrollment-action-bar bulk-account-action-bar">
            <div class="bulk-account-action-copy">
                <strong>Bulk student component assignment</strong>
                <span>Current term: {{ $semesterLabel }} {{ $academicYear }}</span>
            </div>
            <label class="field-group">
                <span>Assign selected students to</span>
                <select name="nstp_component_id" required>
                    <option value="">Choose a component</option>
                    @foreach($availableComponents as $component)
                        <option value="{{ $component->id }}" @selected((int) old('nstp_component_id') === $component->id)>{{ $component->code }} — {{ $component->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="filter-button" type="submit">Assign selected students</button>
        </div>

        <div class="table-wrap"><table class="data-table">
            <thead><tr><th class="check-column"><input type="checkbox" data-select-all-students aria-label="Select all active students on this page"></th><th>Name and email</th><th>Account type</th><th>Component</th><th class="align-right">Details</th></tr></thead>
            <tbody>
            @forelse($accounts as $account)
                @php
                    $accountComponents = ($account->isCoordinator() || $account->isFacilitator())
                        ? collect([$account->nstpComponent])->filter()
                        : $account->nstpEnrollments->pluck('component')->filter()->unique('id');
                    if ($account->isFacilitator() && $accountComponents->isEmpty()) {
                        $accountComponents = $account->facilitatedSections->pluck('component')->filter()->unique('id');
                    }
                @endphp
                <tr>
                    <td class="check-column">
                        @if($account->isStudent() && $account->status === 'active')
                            <input class="student-check" type="checkbox" name="student_ids[]" value="{{ $account->id }}" @checked(in_array($account->id, old('student_ids', []))) aria-label="Select {{ $account->name }}">
                        @else
                            <span class="muted-cell">—</span>
                        @endif
                    </td>
                    <td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($account->name, 0, 1)) }}</span><div><strong>{{ $account->name }}</strong><small>{{ $account->email }}</small></div></div></td>
                    <td><span class="role-badge role-{{ $account->role }}">{{ $account->roleLabel() }}</span></td>
                    <td>@forelse($accountComponents as $component)<span class="component-mini-badge">{{ $component->code }}</span>@empty<span class="muted-cell">Not assigned</span>@endforelse</td>
                    <td class="align-right"><a class="table-action" href="{{ route('nstp_admin.accounts.show', $account) }}">{{ $account->isStudent() ? 'View' : 'Manage' }} →</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><strong>No matching accounts</strong><span>Try changing the role or search filter.</span></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </form>

    @if($accounts->hasPages())<div class="pagination-row"><span>Showing {{ $accounts->firstItem() }}–{{ $accounts->lastItem() }} of {{ $accounts->total() }}</span>{{ $accounts->links() }}</div>@endif
</section>

<script>
    const bulkStudentForm = document.querySelector('#bulk-student-component-form');
    const selectAllStudents = bulkStudentForm?.querySelector('[data-select-all-students]');
    const studentChecks = [...(bulkStudentForm?.querySelectorAll('.student-check') || [])];

    selectAllStudents?.addEventListener('change', function () {
        studentChecks.forEach((checkbox) => checkbox.checked = this.checked);
    });

    studentChecks.forEach((checkbox) => checkbox.addEventListener('change', () => {
        selectAllStudents.checked = studentChecks.length > 0 && studentChecks.every((item) => item.checked);
        selectAllStudents.indeterminate = studentChecks.some((item) => item.checked) && !selectAllStudents.checked;
    }));
</script>
@endsection
