@extends($layout)

@section('title', 'Honorarium Status')
@section('page-title', auth()->user()->isFacilitator() ? 'My Honorarium Status' : 'Facilitator Honorarium Administration')

@section('content')
<section class="welcome-banner"><div><span class="eyebrow">Administrative processing</span><h2>{{ auth()->user()->isFacilitator() ? 'Track your honorarium status' : 'Honorarium requests and disbursement' }}</h2><p>{{ auth()->user()->isFacilitator() ? 'View read-only processing updates prepared by the Coordinator and NSTP Admin.' : 'Prepare payment requests, record approval decisions, generate payslips, and document disbursement.' }}</p></div>@if($canCreateRequests)<a class="primary-button" href="{{ route($routePrefix.'.honoraria.create') }}">Create payment request</a>@endif</section>

@if(auth()->user()->isFacilitator())
    <section class="card user-table-card">
        <div class="section-heading"><div><span class="eyebrow">Read-only status</span><h3>Honorarium processing history</h3><p>For questions about amounts or payment details, contact your Coordinator or NSTP Admin.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Reference</th><th>Component</th><th>Academic term</th><th>Service period</th><th>Status</th><th>Last updated</th></tr></thead><tbody>@forelse($records as $record)<tr><td><strong>{{ $record->reference_number }}</strong></td><td>{{ $record->component->code }}</td><td>{{ $record->academic_year }} · {{ $record->semesterLabel() }}</td><td>{{ $record->period_start->format('M d, Y') }} – {{ $record->period_end->format('M d, Y') }}</td><td><span class="status-badge {{ $record->status === 'disbursed' ? 'active' : 'inactive' }}"><i></i>{{ $record->statusLabel() }}</span>@if($record->status === 'rejected' && $record->approval_notes)<small class="table-secondary-line">Please coordinate with the office regarding this request.</small>@endif</td><td>{{ $record->updated_at->format('M d, Y g:i A') }}</td></tr>@empty<tr><td colspan="6"><div class="empty-state"><strong>No honorarium record yet</strong><span>Your processing status will appear after a Coordinator or NSTP Admin creates a request.</span></div></td></tr>@endforelse</tbody></table></div>
    </section>
@else
    <section class="metric-grid" aria-label="Honorarium processing summary">
        <article class="metric-card"><span class="metric-icon blue">▤</span><div><small>Total requests</small><strong>{{ $metrics['total'] }}</strong><p>Within your scope</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">◷</span><div><small>Pending approval</small><strong>{{ $metrics['pending'] }}</strong><p>Requires NSTP Admin action</p></div></article>
        <article class="metric-card"><span class="metric-icon violet">✓</span><div><small>Approved</small><strong>{{ $metrics['approved'] }}</strong><p>Awaiting disbursement</p></div></article>
        <article class="metric-card"><span class="metric-icon green">₱</span><div><small>Disbursed</small><strong>{{ $metrics['disbursed'] }}</strong><p>Completed records</p></div></article>
    </section>
    <section class="card user-table-card">
        <div class="section-heading"><div><span class="eyebrow">Administrative records</span><h3>Honorarium payment requests</h3></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Request</th><th>Facilitator</th><th>Term and period</th><th>Amounts</th><th>Status</th><th>Administrative action</th></tr></thead><tbody>
        @forelse($records as $record)
            <tr><td><strong>{{ $record->reference_number }}</strong><small class="table-secondary-line">Requested by {{ $record->requester?->name ?? 'Former user' }} · {{ $record->requested_at->format('M d, Y') }}</small></td><td>{{ $record->facilitator->name }}<small class="table-secondary-line">{{ $record->component->code }}</small></td><td>{{ $record->academic_year }} · {{ $record->semesterLabel() }}<small class="table-secondary-line">{{ $record->period_start->format('M d') }} – {{ $record->period_end->format('M d, Y') }}</small></td><td>Gross ₱{{ number_format((float)$record->gross_amount, 2) }}<small class="table-secondary-line">Deductions ₱{{ number_format((float)$record->deductions, 2) }} · Net ₱{{ number_format((float)$record->net_amount, 2) }}</small></td><td><strong>{{ $record->statusLabel() }}</strong>@if($record->approval_notes)<small class="table-secondary-line">{{ $record->approval_notes }}</small>@endif @if($record->disbursement_reference)<small class="table-secondary-line">Reference: {{ $record->disbursement_reference }}</small>@endif</td><td>
                @if(in_array($record->status, ['approved', 'disbursed'], true))<a class="table-action" href="{{ route($routePrefix.'.honoraria.payslip', $record) }}">Download payslip</a>@endif
                @if($canApprove && $record->status === 'pending_approval')<details><summary>Review request</summary><form method="POST" action="{{ route($routePrefix.'.honoraria.review', $record) }}" class="stack-form" style="min-width:18rem">@csrf @method('PUT')<label class="field-group"><span>Decision</span><select name="decision"><option value="approved">Approve for payment</option><option value="rejected">Return / reject</option></select></label><label class="field-group"><span>Approval notes</span><textarea name="approval_notes" rows="3" maxlength="3000"></textarea></label><button class="primary-button compact" type="submit">Save decision</button></form></details>@endif
                @if($canApprove && $record->status === 'approved')<details><summary>Record disbursement</summary><form method="POST" action="{{ route($routePrefix.'.honoraria.disburse', $record) }}" class="stack-form" style="min-width:18rem">@csrf @method('PUT')<label class="field-group"><span>Transaction/reference number</span><input name="disbursement_reference" required maxlength="255"></label><label class="field-group"><span>Disbursement date</span><input type="date" name="disbursed_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></label><button class="primary-button compact" type="submit">Mark as disbursed</button></form></details>@endif
            </td></tr>
        @empty<tr><td colspan="6"><div class="empty-state"><strong>No honorarium requests</strong><span>Create the first facilitator payment request for the active term.</span></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
@endif
@endsection
