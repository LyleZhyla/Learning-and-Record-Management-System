<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} · Smart NSTP</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <style>
        *{box-sizing:border-box}body{margin:0;padding:34px;color:#17243c;font:12px Arial,sans-serif}.print-toolbar{display:flex;justify-content:flex-end;gap:10px;margin-bottom:20px}.print-toolbar button,.print-toolbar a{padding:9px 14px;border:1px solid #cbd5e1;border-radius:6px;background:white;color:#173760;text-decoration:none;cursor:pointer}.print-toolbar button{border-color:#173760;background:#173760;color:white}.report-header{display:flex;justify-content:space-between;gap:30px;padding-bottom:18px;border-bottom:3px solid #173760}.brand{display:flex;align-items:center;gap:10px}.brand-mark{display:grid;width:42px;height:42px;place-items:center;border-radius:8px;background:#173760;color:white;font-weight:bold}.brand strong,.brand small{display:block}.brand small{margin-top:3px;color:#64748b}.report-header h1{margin:0 0 5px;font-size:22px;text-align:right}.report-header p{margin:0;color:#64748b;text-align:right}.filter-summary{margin:18px 0;padding:11px 14px;background:#f1f5f9}.filter-summary span{margin-right:18px}table{width:100%;border-collapse:collapse}th{background:#e8eef6;color:#173760;font-size:9px;text-align:left;text-transform:uppercase}th,td{padding:9px;border:1px solid #cbd5e1;vertical-align:top}.empty{text-align:center;color:#64748b}.report-footer{margin-top:18px;color:#64748b;font-size:9px;text-align:center}@media print{body{padding:0}.print-toolbar{display:none}@page{size:landscape;margin:12mm}}
        .print-brand-logo{display:block;width:54px;height:54px;border-radius:11px;object-fit:contain}
    </style>
</head>
<body>
    <div class="print-toolbar"><a href="{{ route('admin.reports.index', $filters) }}">Back to reports</a><button onclick="window.print()">Print now</button></div>
    <header class="report-header"><div class="brand"><img class="print-brand-logo" src="{{ asset('images/snapie-logo-160.png') }}" alt="SNAPIE logo"><span><strong>Smart NSTP</strong><small>Management and AI-Integrated Platform</small></span></div><div><h1>{{ $report['title'] }}</h1><p>Generated {{ $report['generated_at']->format('F d, Y · h:i A') }}</p></div></header>
    <div class="filter-summary"><strong>Report filters:</strong> <span>Academic year: {{ $filters['academic_year'] ?? 'All' }}</span><span>Semester: {{ isset($filters['semester']) ? (\App\Models\NstpSection::SEMESTERS[$filters['semester']] ?? $filters['semester']) : 'All' }}</span><span>Component ID: {{ $filters['component_id'] ?? 'All' }}</span><span>Section ID: {{ $filters['section_id'] ?? 'All' }}</span></div>
    <table><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@forelse($report['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@empty<tr><td class="empty" colspan="{{ count($report['headers']) }}">No records matched the selected filters.</td></tr>@endforelse</tbody></table>
    <footer class="report-footer">This report was generated automatically by Smart NSTP. Total records: {{ $report['rows']->count() }}.</footer>
</body>
</html>
