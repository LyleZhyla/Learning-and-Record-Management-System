@extends('layouts.coordinator')
@section('title', 'Component Accounts') @section('page-title', 'Facilitators & Students')
@section('content')
<section class="page-actions"><div><span class="eyebrow">Assigned component only</span><h2>{{ auth()->user()->nstpComponent?->code ?? 'No component assigned' }} account directory</h2><p>Only facilitators and students connected to your assigned component are shown here.</p></div></section>

<section class="role-summary-grid nstp-account-role-grid" aria-label="Visible accounts by role">
    @foreach(['facilitator' => 'Facilitators', 'student' => 'Students'] as $role => $label)
    <a class="role-summary {{ request('role') === $role ? 'selected' : '' }}" href="{{ route('coordinator.accounts.index', ['role' => $role]) }}"><span class="role-dot role-{{ $role }}"></span><div><strong>{{ $roleCounts[$role] ?? 0 }}</strong><small>{{ $label }}</small></div></a>
    @endforeach
</section>

<section class="card user-table-card">
    <form class="filter-bar" method="GET"><label class="search-field"><span>⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email"></label><select name="role"><option value="">Facilitators and students</option><option value="facilitator" @selected(($filters['role'] ?? '')==='facilitator')>Facilitator</option><option value="student" @selected(($filters['role'] ?? '')==='student')>Student</option></select><button class="filter-button">Apply filters</button>@if(request()->hasAny(['search','role']))<a class="clear-filter" href="{{ route('coordinator.accounts.index') }}">Clear</a>@endif</form>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Name and email</th><th>Account type</th><th>Assignment</th><th class="align-right">Details</th></tr></thead><tbody>
    @forelse($accounts as $account)
        @php $assignments = $account->isFacilitator() ? $account->facilitatedSections : $account->nstpEnrollments; @endphp
        <tr><td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($account->name,0,1)) }}</span><div><strong>{{ $account->name }}</strong><small>{{ $account->email }}</small></div></div></td><td><span class="role-badge role-{{ $account->role }}">{{ $account->roleLabel() }}</span></td><td>@forelse($assignments as $assignment)<span class="component-mini-badge">{{ $account->isFacilitator() ? $assignment->code : ($assignment->section?->code ?? $assignment->component?->code) }}</span>@empty<span class="muted-cell">Not assigned</span>@endforelse</td><td class="align-right"><a class="table-action" href="{{ route('coordinator.accounts.show',$account) }}">View →</a></td></tr>
    @empty<tr><td colspan="4"><div class="empty-state"><strong>No matching accounts</strong><span>No facilitator or student is currently connected to this component.</span></div></td></tr>@endforelse
    </tbody></table></div>
    @if($accounts->hasPages())<div class="pagination-row"><span>Showing {{ $accounts->firstItem() }}–{{ $accounts->lastItem() }} of {{ $accounts->total() }}</span>{{ $accounts->links() }}</div>@endif
</section>
@endsection
