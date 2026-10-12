@extends('layouts.nstp-admin')

@section('title', 'Service Requests')
@section('page-title', 'Service Request Applications')

@section('content')
<section class="service-admin-hero">
    <div><span class="eyebrow">NSTP Office request desk</span><h2>Review and update public applications</h2><p>Manage serial-number, certification, completion-certificate, and event-assistance requests submitted through the public portal.</p></div>
    <span class="service-admin-total"><strong>{{ $metrics['new'] }}</strong><small>new applications</small></span>
</section>

<section class="service-admin-metrics" aria-label="Service request summary">
    <article><span class="metric-icon blue">☷</span><div><small>All applications</small><strong>{{ $metrics['total'] }}</strong><p>Public requests received</p></div></article>
    <article><span class="metric-icon orange">●</span><div><small>New submissions</small><strong>{{ $metrics['new'] }}</strong><p>Waiting for initial review</p></div></article>
    <article><span class="metric-icon violet">◷</span><div><small>In progress</small><strong>{{ $metrics['in_progress'] }}</strong><p>Review, information, or approval</p></div></article>
    <article><span class="metric-icon green">✓</span><div><small>Completed</small><strong>{{ $metrics['completed'] }}</strong><p>Requests fully processed</p></div></article>
</section>

<section class="card service-admin-list-card">
    <div class="service-admin-heading"><div><span class="eyebrow">Application inbox</span><h3>Submitted service requests</h3><p>Open an application to review its details and update the applicant-facing status.</p></div></div>
    <form method="GET" action="{{ route('nstp_admin.service-requests.index') }}" class="service-admin-filters">
        <label class="field-group service-admin-search"><span>Search</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reference, requester, email, or student no."></label>
        <label class="field-group"><span>Request type</span><select name="request_type"><option value="">All request types</option>@foreach($requestTypes as $value => $label)<option value="{{ $value }}" @selected(($filters['request_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field-group"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="report-filter-actions"><button class="filter-button" type="submit">Apply filters</button>@if(request()->hasAny(['search','request_type','status']))<a class="clear-filter" href="{{ route('nstp_admin.service-requests.index') }}">Clear</a>@endif</div>
    </form>
    @if($requests->total() > 0)<div class="service-admin-page-size"><div><strong>Applications shown</strong><span>Choose the number of records in the list.</span></div>@include('pagination.page-size', ['paginator' => $requests])</div>@endif
    <div class="table-wrap"><table class="data-table service-request-table"><thead><tr><th>Application</th><th>Requester</th><th>Type</th><th>Submitted</th><th>Status</th><th class="align-right">Action</th></tr></thead><tbody>
        @forelse($requests as $serviceRequest)
            <tr><td><strong>{{ $serviceRequest->reference_code }}</strong><small class="table-secondary-line">{{ $serviceRequest->student_number ?: 'No student number' }}</small></td><td><strong>{{ $serviceRequest->requesterDisplayName() }}</strong><small class="table-secondary-line">{{ $serviceRequest->email }}</small></td><td>{{ $serviceRequest->requestTypeLabel() }}@if($serviceRequest->assistanceTypeLabel())<small class="table-secondary-line">{{ $serviceRequest->assistanceTypeLabel() }}</small>@endif</td><td>{{ $serviceRequest->created_at->format('M d, Y') }}<small class="table-secondary-line">{{ $serviceRequest->created_at->format('g:i A') }}</small></td><td><span class="service-request-status status-{{ $serviceRequest->status }}"><i></i>{{ $serviceRequest->statusLabel() }}</span>@if($serviceRequest->processor)<small class="table-secondary-line">by {{ $serviceRequest->processor->name }}</small>@endif</td><td class="align-right"><a class="table-action" href="{{ route('nstp_admin.service-requests.show', $serviceRequest) }}">Review application →</a></td></tr>
        @empty
            <tr><td colspan="6"><div class="empty-state"><strong>No matching applications</strong><span>Try changing the filters or wait for a new public service request.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
    @if($requests->total() > 0)<div class="pagination-row"><span>Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ $requests->total() }}</span>{{ $requests->links('pagination.compact', ['showPageSize' => false]) }}</div>@endif
</section>
@endsection
