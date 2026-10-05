<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report['title'] }} · Smart NSTP</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <style>
        *{box-sizing:border-box}body{margin:0;padding:34px;color:#17243c;font:12px Arial,sans-serif}.print-toolbar{display:flex;justify-content:flex-end;gap:10px;margin-bottom:20px}.print-toolbar button,.print-toolbar a{padding:9px 14px;border:1px solid #cbd5e1;border-radius:6px;background:white;color:#173760;text-decoration:none;cursor:pointer}.print-toolbar button{border-color:#173760;background:#173760;color:white}.official-document-header,.official-document-footer{width:100%;margin:0 auto 18px}.official-document-footer{margin:18px auto 0}.official-document-header img,.official-document-footer img{display:block;width:100%}.report-header{display:flex;justify-content:space-between;gap:30px;padding-bottom:18px;border-bottom:3px solid #173760}.brand{display:flex;align-items:center;color:#008000;font-weight:bold}.report-header h1{margin:0 0 5px;font-size:22px;text-align:right}.report-header p{margin:0;color:#64748b;text-align:right}.filter-summary{margin:18px 0;padding:11px 14px;background:#f1f5f9}.filter-summary span{margin-right:18px}.section-group+.section-group{page-break-before:always}.section-heading{display:flex;align-items:center;justify-content:space-between;margin:0 0 9px;padding:10px 12px;border-left:3px solid #2468ca;background:#f1f5f9}.section-heading h2{margin:0 0 3px;color:#173760;font-size:15px}.section-heading p{margin:0;color:#64748b;font-size:10px}.section-heading strong{color:#2468ca;font-size:10px}table{width:100%;border-collapse:collapse}thead{display:table-header-group}th{background:#e8eef6;color:#173760;font-size:9px;text-align:left;text-transform:uppercase}th,td{padding:9px;border:1px solid #cbd5e1;vertical-align:top}.empty{text-align:center;color:#64748b}@media print{body{padding:0}.print-toolbar{display:none}.official-document-header{position:fixed;top:-38mm;right:0;left:0;margin:0}.official-document-footer{position:fixed;right:0;bottom:-32mm;left:0;margin:0}@page{size:landscape;margin:42mm 8mm 36mm}}
    </style>
</head>
<body>
    <div class="print-toolbar"><a href="{{ route($routePrefix.'.reports.index', collect($filters)->except('facilitator_id')->all()) }}">Back to reports</a><button onclick="window.print()">Print now</button></div>
    <div class="official-document-header"><img src="{{ asset('images/official-document-header.png') }}" alt="Tarlac Agricultural University header"></div>
    <div class="official-document-footer"><img src="{{ asset('images/official-document-footer.png') }}" alt="Tarlac Agricultural University footer"></div>
    <header class="report-header"><div class="brand">NATIONAL SERVICE TRAINING PROGRAM</div><div><h1>{{ $report['title'] }}</h1><p>Generated {{ $report['generated_at']->format('F d, Y · h:i A') }}</p></div></header>
    <div class="filter-summary"><strong>Report filters:</strong> <span>Academic year: {{ $filters['academic_year'] ?? 'All' }}</span><span>Semester: {{ isset($filters['semester']) ? (\App\Models\NstpSection::SEMESTERS[$filters['semester']] ?? $filters['semester']) : 'All' }}</span><span>Component ID: {{ $filters['component_id'] ?? 'All' }}</span><span>Section ID: {{ $filters['section_id'] ?? 'All' }}</span></div>
    @if(array_key_exists('groups', $report) && $report['groups']->isNotEmpty())
        @foreach($report['groups'] as $group)
            <section class="section-group"><div class="section-heading"><div><h2>{{ $group['title'] }}</h2><p>{{ $group['subtitle'] }}</p></div><strong>{{ $group['rows']->count() }} student{{ $group['rows']->count() === 1 ? '' : 's' }}</strong></div><table><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach($group['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody></table></section>
        @endforeach
    @else
        <table><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@forelse($report['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@empty<tr><td class="empty" colspan="{{ count($report['headers']) }}">No records matched the selected filters.</td></tr>@endforelse</tbody></table>
    @endif
</body>
</html>
