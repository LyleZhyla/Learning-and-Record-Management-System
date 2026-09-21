<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><style>
    @page{margin:28px}body{color:#17243c;font:11px Helvetica,Arial,sans-serif}h1{margin:0;color:#173760;font-size:22px}h2{margin:22px 0 8px;color:#173760;font-size:15px}.meta{margin:6px 0 18px;color:#64748b}.summary{width:100%;margin-bottom:15px;border-collapse:collapse}.summary td{padding:8px;border:1px solid #d7e0eb}.summary strong{display:block;margin-top:3px}table.data{width:100%;border-collapse:collapse}table.data th{padding:7px;background:#173760;color:white;text-align:left}table.data td{padding:7px;border:1px solid #d7e0eb;vertical-align:top}.footer{position:fixed;bottom:-12px;left:0;right:0;color:#64748b;font-size:9px;text-align:center}
</style></head>
<body>
<h1>{{ match($downloadType) { 'grades' => 'Personal Grade Report', 'attendance' => 'Personal Attendance Report', default => 'Assessment Submission History' } }}</h1>
<p class="meta">{{ $student->name }} · {{ $student->email }} · Generated {{ now()->format('F d, Y · h:i A') }}</p>
<table class="summary"><tr><td>Component<strong>{{ $enrollment?->component?->code ?? 'Unassigned' }}</strong></td><td>Section<strong>{{ $enrollment?->section?->code ?? 'Unassigned' }}</strong></td><td>Term<strong>{{ $enrollment?->academic_year ?? 'Not available' }}</strong></td></tr></table>
@if($downloadType === 'grades')
    <h2>Grade summary</h2>
    <table class="data"><thead><tr><th>Category</th><th>Earned</th><th>Maximum</th><th>Weighted score</th><th>Graded</th></tr></thead><tbody>
    @forelse(($gradeSummary['categories'] ?? []) as $category)<tr><td>{{ $category['category']->name }}</td><td>{{ number_format($category['earned'],2) }}</td><td>{{ number_format($category['maximum'],2) }}</td><td>{{ number_format($category['weighted_score'],2) }}%</td><td>{{ $category['graded_count'] }} / {{ $category['total_count'] }}</td></tr>@empty<tr><td colspan="5">No grade data available.</td></tr>@endforelse
    </tbody></table>
    <p><strong>Weighted total:</strong> {{ $gradeSummary && $gradeSummary['percentage'] !== null ? number_format($gradeSummary['percentage'],2).'%' : '—' }} &nbsp; <strong>Final grade:</strong> {{ $gradeSummary && $gradeSummary['grade'] !== null ? number_format($gradeSummary['grade'],2) : '—' }}</p>
@elseif($downloadType === 'attendance')
    <h2>Attendance records</h2>
    <table class="data"><thead><tr><th>Session</th><th>Date</th><th>Status</th><th>Time in</th><th>Time out</th></tr></thead><tbody>
    @forelse($attendanceRecords as $record)<tr><td>{{ $record->attendanceSession->title }}</td><td>{{ $record->attendanceSession->starts_at->format('M d, Y') }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->checked_in_at?->format('h:i A') ?? '—' }}</td><td>{{ $record->checked_out_at?->format('h:i A') ?? '—' }}</td></tr>@empty<tr><td colspan="5">No attendance records available.</td></tr>@endforelse
    </tbody></table>
@else
    <h2>Assessment submissions</h2>
    <table class="data"><thead><tr><th>Assessment</th><th>Type</th><th>Submitted</th><th>Score</th><th>Feedback</th></tr></thead><tbody>
    @forelse($submissions as $submission)<tr><td>{{ $submission->assessment->title }}</td><td>{{ str($submission->assessment->type)->headline() }}</td><td>{{ $submission->submitted_at?->format('M d, Y h:i A') ?? '—' }}</td><td>{{ $submission->score === null ? 'Pending' : number_format($submission->score,2).' / '.number_format($submission->assessment->max_score,2) }}</td><td>{{ $submission->feedback ?: '—' }}</td></tr>@empty<tr><td colspan="5">No assessment submissions available.</td></tr>@endforelse
    </tbody></table>
@endif
<div class="footer">SNAPIE · Smart NSTP Management and AI-Integrated Platform</div>
</body></html>
