@extends('layouts.coordinator')
@section('title', 'ROTC Approvals')
@section('page-title', 'ROTC Approvals')

@section('content')
<section class="page-actions">
    <div><span class="eyebrow">MS-1 completion verification</span><h2>Review advanced ROTC requests</h2><p>Verify each student's proof before approving enrollment in MS-31 or MS-41.</p></div>
</section>

<section class="card user-table-card">
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Student</th><th>Requested category</th><th>Shirt size</th><th>Term</th><th>MS-1 proof</th><th class="align-right">Approval</th></tr></thead>
        <tbody>
        @forelse($pendingRequests as $enrollment)
            <tr>
                <td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($enrollment->student->name, 0, 1)) }}</span><div><strong>{{ $enrollment->student->name }}</strong><small>{{ $enrollment->student->email }}</small></div></div></td>
                <td><span class="role-badge role-student">{{ $enrollment->rotc_category }}</span></td>
                <td>{{ $enrollment->shirt_size }}</td>
                <td>{{ ucfirst($enrollment->semester) }} · {{ $enrollment->academic_year }}</td>
                <td><a class="table-action" href="{{ route('coordinator.rotc-approvals.proof', $enrollment) }}">Download proof</a></td>
                <td class="align-right"><form method="POST" action="{{ route('coordinator.rotc-approvals.approve', $enrollment) }}">@csrf @method('PATCH')<button class="success-button" type="submit">Approve {{ $enrollment->rotc_category }}</button></form></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty-state"><strong>No pending ROTC requests</strong><span>MS-31 and MS-41 requests will appear here after students upload proof.</span></div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($pendingRequests->hasPages())<div class="pagination-row"><span>Showing {{ $pendingRequests->firstItem() }}–{{ $pendingRequests->lastItem() }} of {{ $pendingRequests->total() }}</span>{{ $pendingRequests->links() }}</div>@endif
</section>
@endsection
