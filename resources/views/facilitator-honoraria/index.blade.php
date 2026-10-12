@extends($layout)

@section('title', auth()->user()->isFacilitator() ? 'Monthly Accomplishment Reports' : 'Honorarium & Accomplishment Reports')
@section('page-title', auth()->user()->isFacilitator() ? 'Monthly Accomplishment Reports' : 'Facilitator Honorarium & Reports')

@section('content')
<section class="honorarium-hero">
    <div>
        <span class="eyebrow">{{ auth()->user()->isFacilitator() ? 'Monthly submission workspace' : 'NSTP facilitator monitoring' }}</span>
        <h2>{{ auth()->user()->isFacilitator() ? 'Submit accomplishments by month' : 'See every monthly report before preparing honorarium documents' }}</h2>
        <p>{{ auth()->user()->isFacilitator() ? 'Upload one consolidated accomplishment report for each component, term, and month. Resubmitting the same month replaces your previous file.' : 'Filter submissions by month, download the supporting report, review it, and keep the cashier-document workflow in the same workspace.' }}</p>
    </div>
    @if($canCreateRequests)<a class="primary-button" href="{{ route($routePrefix.'.honoraria.create') }}">Create honorarium request</a>@endif
</section>

@if(auth()->user()->isFacilitator())
    <section class="honorarium-workspace">
        <article class="card monthly-report-form-card">
            <div class="section-heading">
                <div><span class="eyebrow">New monthly report</span><h3>Record this month's accomplishments</h3><p>PDF, Word, or Excel · maximum 10 MB.</p></div>
                <span class="month-chip">{{ now()->format('F Y') }}</span>
            </div>
            @if($assignments->isEmpty())
                <div class="empty-state"><strong>No active assignment</strong><span>You need an active facilitated section before submitting a monthly report.</span></div>
            @else
                <form method="POST" action="{{ route('facilitator.honoraria.monthly-reports.store') }}" enctype="multipart/form-data" class="monthly-report-form">
                    @csrf
                    <label class="field-group"><span>Report month</span><input type="month" name="report_month" max="{{ now()->format('Y-m') }}" value="{{ old('report_month', now()->format('Y-m')) }}" required>@error('report_month')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field-group"><span>Component and academic term</span><select name="assignment_id" required><option value="">Select assignment</option>@foreach($assignments as $assignment)<option value="{{ $assignment->id }}" @selected(old('assignment_id') == $assignment->id)>{{ $assignment->component->code }} · {{ $assignment->academic_year }} · {{ $assignment->semesterLabel() }}</option>@endforeach</select>@error('assignment_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field-group full"><span>Accomplishment summary</span><textarea name="accomplishment_summary" rows="5" maxlength="3000" placeholder="Summarize activities completed, outputs delivered, and important outcomes for the month." required>{{ old('accomplishment_summary') }}</textarea>@error('accomplishment_summary')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="report-upload-zone full"><span class="report-upload-icon">⇧</span><span><strong>Choose accomplishment report</strong><small>PDF, DOC, DOCX, XLS, or XLSX</small></span><input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx" required>@error('attachment')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <div class="monthly-report-submit full"><p>Submitting again for the same month and term replaces the earlier file and returns it to <strong>For review</strong>.</p><button class="primary-button" type="submit">Submit monthly report</button></div>
                </form>
            @endif
        </article>

        <aside class="card monthly-guide-card">
            <span class="eyebrow">How it works</span><h3>A simple monthly cycle</h3>
            <ol><li><span>1</span><div><strong>Select the month</strong><small>Use the month covered by the report.</small></div></li><li><span>2</span><div><strong>Upload one consolidated file</strong><small>Include the activities and outputs for the selected term.</small></div></li><li><span>3</span><div><strong>Track the NSTP Admin review</strong><small>Revise and resubmit if a correction is requested.</small></div></li></ol>
        </aside>
    </section>

    <section class="card monthly-report-history">
        <div class="section-heading"><div><span class="eyebrow">Submission history</span><h3>Your reports by month</h3><p>The newest reporting month appears first.</p></div></div>
        <div class="monthly-report-list">
            @forelse($accomplishmentReports as $report)
                <article class="monthly-report-row">
                    <div class="report-month-tile"><strong>{{ $report->report_month->format('M') }}</strong><span>{{ $report->report_month->format('Y') }}</span></div>
                    <div class="report-main"><div class="report-title-line"><strong>{{ $report->component->code }} accomplishment report</strong><span class="report-status status-{{ $report->status }}">{{ $report->statusLabel() }}</span></div><p>{{ $report->accomplishment_summary }}</p><small>{{ $report->academic_year }} · {{ $report->semesterLabel() }} · Submitted {{ $report->submitted_at->format('M d, Y g:i A') }}</small>@if($report->review_notes)<div class="report-review-note"><strong>NSTP Admin note:</strong> {{ $report->review_notes }}</div>@endif</div>
                    <a class="secondary-outline-button compact" href="{{ route('facilitator.honoraria.monthly-reports.download', $report) }}">Download file</a>
                </article>
            @empty
                <div class="empty-state"><strong>No monthly report submitted yet</strong><span>Your submitted accomplishment reports and review status will appear here.</span></div>
            @endforelse
        </div>
    </section>

    <section class="card user-table-card honorarium-status-card">
        <div class="section-heading"><div><span class="eyebrow">Payment document status</span><h3>Honorarium processing history</h3><p>Approval and payment remain with the University Cashier.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Reference</th><th>Component</th><th>Academic term</th><th>Service period</th><th>Status</th><th>Last updated</th></tr></thead><tbody>@forelse($records as $record)<tr><td><strong>{{ $record->reference_number }}</strong></td><td>{{ $record->component->code }}</td><td>{{ $record->academic_year }} · {{ $record->semesterLabel() }}</td><td>{{ $record->period_start->format('M d, Y') }} – {{ $record->period_end->format('M d, Y') }}</td><td><span class="status-badge {{ $record->status === 'forwarded_to_cashier' ? 'active' : 'inactive' }}"><i></i>{{ $record->statusLabel() }}</span></td><td>{{ $record->updated_at->format('M d, Y g:i A') }}</td></tr>@empty<tr><td colspan="6"><div class="empty-state"><strong>No honorarium record yet</strong><span>Processing status will appear after a request is created.</span></div></td></tr>@endforelse</tbody></table></div>
    </section>
@else
    @if(auth()->user()->isNstpAdmin())
        <section class="card monthly-admin-toolbar">
            <div><span class="eyebrow">Monthly accomplishment reports</span><h3>{{ $reportMonth->format('F Y') }} submissions</h3><p>Reports are arranged by facilitator last name for faster checking.</p></div>
            <form method="GET" action="{{ route('nstp_admin.honoraria.index') }}"><label class="field-group"><span>Reporting month</span><input type="month" name="month" value="{{ $selectedMonth }}" onchange="this.form.submit()"></label><noscript><button class="primary-button compact" type="submit">Apply month</button></noscript></form>
        </section>
        <section class="honorarium-report-metrics" aria-label="Monthly accomplishment report summary">
            <article><span class="metric-icon blue">▤</span><div><small>Reports received</small><strong>{{ $reportMetrics['submitted'] }}</strong><p>For {{ $reportMonth->format('F Y') }}</p></div></article>
            <article><span class="metric-icon violet">◉</span><div><small>Facilitators submitted</small><strong>{{ $reportMetrics['facilitators'] }}</strong><p>{{ $reportMetrics['coverage'] }}% submission coverage</p></div></article>
            <article><span class="metric-icon orange">◷</span><div><small>For review</small><strong>{{ $reportMetrics['pending_review'] }}</strong><p>Needs NSTP Admin action</p></div></article>
            <article><span class="metric-icon red">!</span><div><small>Missing / revision</small><strong>{{ $reportMetrics['missing'] + $reportMetrics['needs_revision'] }}</strong><p>{{ $reportMetrics['missing'] }} missing · {{ $reportMetrics['needs_revision'] }} revision</p></div></article>
        </section>
        <section class="card admin-monthly-reports">
            <div class="section-heading"><div><span class="eyebrow">Submitted files</span><h3>Accomplishment reports</h3><p>Open the submitted file, then record the review result.</p></div></div>
            <div class="monthly-report-list">
                @forelse($accomplishmentReports as $report)
                    <article class="monthly-report-row admin-report-row">
                        <div class="facilitator-avatar">{{ str($report->facilitator->name)->substr(0, 1)->upper() }}</div>
                        <div class="report-main"><div class="report-title-line"><strong>{{ $report->facilitator->studentRecordName() }}</strong><span class="report-status status-{{ $report->status }}">{{ $report->statusLabel() }}</span></div><p>{{ $report->accomplishment_summary }}</p><small>{{ $report->component->code }} · {{ $report->academic_year }} · {{ $report->semesterLabel() }} · Submitted {{ $report->submitted_at->format('M d, Y g:i A') }}</small>@if($report->review_notes)<div class="report-review-note"><strong>Review note:</strong> {{ $report->review_notes }}</div>@endif</div>
                        <div class="report-actions"><a class="secondary-outline-button compact" href="{{ route('nstp_admin.honoraria.monthly-reports.download', $report) }}">Download</a><details><summary>Review</summary><form method="POST" action="{{ route('nstp_admin.honoraria.monthly-reports.review', $report) }}" class="stack-form report-review-form">@csrf @method('PATCH')<label class="field-group"><span>Decision</span><select name="status"><option value="reviewed">Mark as reviewed</option><option value="needs_revision">Return for revision</option></select></label><label class="field-group"><span>Review note</span><textarea name="review_notes" rows="3" maxlength="2000" placeholder="Required when returning for revision"></textarea></label><button class="primary-button compact" type="submit">Save review</button></form></details></div>
                    </article>
                @empty
                    <div class="empty-state"><strong>No reports for {{ $reportMonth->format('F Y') }}</strong><span>Choose another month or wait for facilitator submissions.</span></div>
                @endforelse
            </div>
        </section>
    @endif

    <section class="metric-grid honorarium-payment-metrics" aria-label="Honorarium processing summary">
        <article class="metric-card"><span class="metric-icon blue">▤</span><div><small>Total requests</small><strong>{{ $metrics['total'] }}</strong><p>Within your scope</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">◷</span><div><small>For preparation</small><strong>{{ $metrics['for_preparation'] }}</strong><p>Needs document preparation</p></div></article>
        <article class="metric-card"><span class="metric-icon violet">▤</span><div><small>Documents prepared</small><strong>{{ $metrics['prepared'] }}</strong><p>Voucher package is ready</p></div></article>
        <article class="metric-card"><span class="metric-icon green">→</span><div><small>Forwarded</small><strong>{{ $metrics['forwarded'] }}</strong><p>Sent to University Cashier</p></div></article>
    </section>
    <section class="card user-table-card">
        <div class="section-heading"><div><span class="eyebrow">Cashier document records</span><h3>Honorarium requests and vouchers</h3></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Request</th><th>Facilitator</th><th>Term and period</th><th>Amounts</th><th>Status</th><th>Document preparation</th></tr></thead><tbody>
        @forelse($records as $record)
            <tr><td><strong>{{ $record->reference_number }}</strong><small class="table-secondary-line">Created by {{ $record->requester?->name ?? 'Former user' }} · {{ $record->requested_at->format('M d, Y') }}</small></td><td>{{ $record->facilitator->studentRecordName() }}<small class="table-secondary-line">{{ $record->component->code }}</small></td><td>{{ $record->academic_year }} · {{ $record->semesterLabel() }}<small class="table-secondary-line">{{ $record->period_start->format('M d') }} – {{ $record->period_end->format('M d, Y') }}</small></td><td>Gross ₱{{ number_format((float)$record->gross_amount, 2) }}<small class="table-secondary-line">Deductions ₱{{ number_format((float)$record->deductions, 2) }} · Net ₱{{ number_format((float)$record->net_amount, 2) }}</small></td><td><strong>{{ $record->statusLabel() }}</strong>@if($record->preparation_notes)<small class="table-secondary-line">{{ $record->preparation_notes }}</small>@endif @if($record->disbursement_voucher_number)<small class="table-secondary-line">DV: {{ $record->disbursement_voucher_number }}</small>@endif</td><td>
                @if(in_array($record->status, ['documents_prepared', 'forwarded_to_cashier'], true))<a class="table-action" href="{{ route($routePrefix.'.honoraria.voucher', $record) }}">Download cashier voucher</a>@endif
                @if($canPrepareDocuments)<details><summary>{{ $record->prepared_at ? 'Update document package' : 'Prepare cashier documents' }}</summary><form method="POST" action="{{ route($routePrefix.'.honoraria.documents.update', $record) }}" class="stack-form" style="min-width:20rem">@csrf @method('PUT')<label class="field-group"><span>Processing status</span><select name="status"><option value="documents_prepared" @selected($record->status === 'documents_prepared')>Documents prepared</option><option value="forwarded_to_cashier" @selected($record->status === 'forwarded_to_cashier')>Forwarded to University Cashier</option><option value="returned_for_correction" @selected($record->status === 'returned_for_correction')>Returned for correction</option></select></label><label class="field-group"><span>Disbursement voucher number</span><input name="disbursement_voucher_number" value="{{ $record->disbursement_voucher_number }}" maxlength="100"></label><label class="field-group"><span>Obligation request number</span><input name="obligation_request_number" value="{{ $record->obligation_request_number }}" maxlength="100"></label><label class="field-group"><span>Payroll/reference number</span><input name="payroll_reference" value="{{ $record->payroll_reference }}" maxlength="100"></label><label class="field-group"><span>Forwarded to cashier at</span><input type="datetime-local" name="cashier_forwarded_at" value="{{ $record->cashier_forwarded_at?->format('Y-m-d\TH:i') }}"></label><label class="field-group"><span>Preparation notes</span><textarea name="preparation_notes" rows="3" maxlength="3000">{{ $record->preparation_notes }}</textarea></label><button class="primary-button compact" type="submit">Save document status</button></form></details>@endif
            </td></tr>
        @empty<tr><td colspan="6"><div class="empty-state"><strong>No honorarium requests</strong><span>Create the first facilitator payment request for the active term.</span></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
@endif
@endsection
