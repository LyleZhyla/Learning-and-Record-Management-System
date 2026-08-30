@extends('layouts.nstp-admin')
@section('title', 'Account Directory') @section('page-title', 'Account Directory')
@section('content')
<section class="page-actions"><div><span class="eyebrow">NSTP account assignments</span><h2>Coordinators, facilitators, and students</h2><p>Assign NSTP components to coordinators and facilitators, import students, and view student records. Password concerns must still be handled by the Super Admin.</p></div><a class="import-students-button" href="{{ route('nstp_admin.students.import.create') }}" aria-label="Import students from Excel"><span aria-hidden="true">⇧</span> Import Students</a></section>

<section class="role-summary-grid nstp-account-role-grid" aria-label="Visible accounts by role">
    @foreach(['coordinator' => 'Coordinators', 'facilitator' => 'Facilitators', 'student' => 'Students'] as $role => $label)
    <a class="role-summary {{ request('role') === $role ? 'selected' : '' }}" href="{{ route('nstp_admin.accounts.index', ['role' => $role]) }}"><span class="role-dot role-{{ $role }}"></span><div><strong>{{ $roleCounts[$role] ?? 0 }}</strong><small>{{ $label }}</small></div></a>
    @endforeach
</section>

<section class="card password-boundary-note"><span>🔒</span><div><strong>Password access is restricted</strong><p>NSTP Admin cannot view, change, or reset passwords. Direct the user to the Super Admin when account recovery is needed.</p></div></section>

<section class="card user-table-card">
    <form class="filter-bar" method="GET"><label class="search-field"><span>⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email"></label><select name="role"><option value="">All visible roles</option><option value="coordinator" @selected(($filters['role'] ?? '')==='coordinator')>Coordinator</option><option value="facilitator" @selected(($filters['role'] ?? '')==='facilitator')>Facilitator</option><option value="student" @selected(($filters['role'] ?? '')==='student')>Student</option></select><button class="filter-button">Apply filters</button>@if(request()->hasAny(['search','role']))<a class="clear-filter" href="{{ route('nstp_admin.accounts.index') }}">Clear</a>@endif</form>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Name and email</th><th>Account type</th><th>Component</th><th class="align-right">Details</th></tr></thead><tbody>
    @forelse($accounts as $account)
        @php
            $accountComponents = ($account->isCoordinator() || $account->isFacilitator())
                ? collect([$account->nstpComponent])->filter()
                : ($account->isFacilitator()
                    ? $account->facilitatedSections->pluck('component')->filter()->unique('id')
                    : $account->nstpEnrollments->pluck('component')->filter()->unique('id'));
            if ($account->isFacilitator() && $accountComponents->isEmpty()) {
                $accountComponents = $account->facilitatedSections->pluck('component')->filter()->unique('id');
            }
        @endphp
        <tr><td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($account->name,0,1)) }}</span><div><strong>{{ $account->name }}</strong><small>{{ $account->email }}</small></div></div></td><td><span class="role-badge role-{{ $account->role }}">{{ $account->roleLabel() }}</span></td><td>@forelse($accountComponents as $component)<span class="component-mini-badge">{{ $component->code }}</span>@empty<span class="muted-cell">Not assigned</span>@endforelse</td><td class="align-right"><a class="table-action" href="{{ route('nstp_admin.accounts.show',$account) }}">{{ $account->isStudent() ? 'View' : 'Manage' }} →</a></td></tr>
    @empty<tr><td colspan="4"><div class="empty-state"><strong>No matching accounts</strong><span>Try changing the role or search filter.</span></div></td></tr>@endforelse
    </tbody></table></div>
    @if($accounts->hasPages())<div class="pagination-row"><span>Showing {{ $accounts->firstItem() }}–{{ $accounts->lastItem() }} of {{ $accounts->total() }}</span>{{ $accounts->links() }}</div>@endif
</section>
@endsection
