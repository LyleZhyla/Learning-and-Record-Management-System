@extends('layouts.nstp-admin')
@section('title', 'Staff Accounts')
@section('page-title', 'Staff Account Directory')

@section('content')
<section class="page-actions">
    <div><span class="eyebrow">NSTP staff assignments</span><h2>Coordinators and facilitators</h2><p>Manage staff component assignments separately from student accounts and records.</p></div>
</section>

@if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert danger" role="alert">{{ $errors->first() }}</div>@endif

<section class="role-summary-grid staff-account-role-grid" aria-label="Staff accounts by role">
    @foreach(['coordinator' => 'Coordinators', 'facilitator' => 'Facilitators'] as $role => $label)
        <a class="role-summary {{ request('role') === $role ? 'selected' : '' }}" href="{{ route('nstp_admin.accounts.index', ['role' => $role]) }}"><span class="role-dot role-{{ $role }}"></span><div><strong>{{ $roleCounts[$role] ?? 0 }}</strong><small>{{ $label }}</small></div></a>
    @endforeach
</section>

<section class="card password-boundary-note"><span>🔒</span><div><strong>Password access is restricted</strong><p>NSTP Admin cannot view, change, or reset passwords. Direct staff to the Super Admin when account recovery is needed.</p></div></section>

<section class="card user-table-card">
    <form class="filter-bar" method="GET" action="{{ route('nstp_admin.accounts.index') }}">
        <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search staff name or email"></label>
        <select name="role" aria-label="Filter by staff role"><option value="">All staff roles</option><option value="coordinator" @selected(($filters['role'] ?? '') === 'coordinator')>Coordinator</option><option value="facilitator" @selected(($filters['role'] ?? '') === 'facilitator')>Facilitator</option></select>
        <button class="filter-button" type="submit">Apply filters</button>
        @if(request()->hasAny(['search', 'role']))<a class="clear-filter" href="{{ route('nstp_admin.accounts.index') }}">Clear</a>@endif
    </form>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Staff member</th><th>Account type</th><th>Component</th><th>Status</th><th class="align-right">Action</th></tr></thead>
        <tbody>@forelse($accounts as $account)
            @php
                $accountComponents = collect([$account->nstpComponent])->filter();
                if ($account->isFacilitator() && $accountComponents->isEmpty()) {
                    $accountComponents = $account->facilitatedSections->pluck('component')->filter()->unique('id');
                }
            @endphp
            <tr>
                <td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($account->name, 0, 1)) }}</span><div><strong>{{ $account->name }}</strong><small>{{ $account->email }}</small></div></div></td>
                <td><span class="role-badge role-{{ $account->role }}">{{ $account->roleLabel() }}</span></td>
                <td>@forelse($accountComponents as $component)<span class="component-mini-badge">{{ $component->code }}</span>@empty<span class="muted-cell">Not assigned</span>@endforelse</td>
                <td><span class="status-badge {{ $account->status }}"><i></i>{{ $account->statusLabel() }}</span></td>
                <td class="align-right"><a class="table-action" href="{{ route('nstp_admin.accounts.show', $account) }}">Manage →</a></td>
            </tr>
        @empty<tr><td colspan="5"><div class="empty-state"><strong>No matching staff accounts</strong><span>Try changing the role or search filter.</span></div></td></tr>@endforelse</tbody>
    </table></div>
    @if($accounts->hasPages())<div class="pagination-row"><span>Showing {{ $accounts->firstItem() }}–{{ $accounts->lastItem() }} of {{ $accounts->total() }}</span>{{ $accounts->links() }}</div>@endif
</section>
@endsection
