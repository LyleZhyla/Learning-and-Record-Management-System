<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 28px 30px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17243c; font-family: Helvetica, Arial, sans-serif; font-size: 8px; }
        .report-header { width: 100%; padding-bottom: 13px; border-bottom: 3px solid #173760; }
        .report-header td { vertical-align: middle; }
        .brand-cell { width: 52%; }
        .brand-logo { width: 45px; height: 45px; vertical-align: middle; }
        .brand-copy { display: inline-block; margin-left: 9px; vertical-align: middle; }
        .brand-copy strong { display: block; color: #173760; font-size: 13px; }
        .brand-copy span { display: block; margin-top: 3px; color: #64748b; font-size: 7px; }
        .title-cell { width: 48%; text-align: right; }
        h1 { margin: 0; color: #173760; font-size: 17px; }
        .generated { margin-top: 4px; color: #64748b; font-size: 8px; }
        .filter-summary { margin: 12px 0; padding: 8px 10px; border-left: 3px solid #2468ca; background: #eef4fb; color: #334155; line-height: 1.35; }
        .record-count { margin-bottom: 7px; color: #64748b; font-size: 8px; }
        table.report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table thead { display: table-header-group; }
        .report-table th { padding: 7px 5px; border: 1px solid #173760; background: #173760; color: white; font-size: 7px; text-align: center; text-transform: uppercase; vertical-align: middle; }
        .report-table td { padding: 6px 5px; border-bottom: 1px solid #dce3ec; color: #27364d; line-height: 1.25; vertical-align: top; word-wrap: break-word; }
        .report-table tbody tr:nth-child(even) td { background: #f5f8fc; }
        .report-table tr { page-break-inside: avoid; }
        .empty { padding: 22px !important; color: #64748b !important; text-align: center; }
        .report-footer { position: fixed; right: 120px; bottom: -21px; left: 0; color: #64748b; font-size: 7px; }
    </style>
</head>
<body>
    <table class="report-header">
        <tr>
            <td class="brand-cell">
                @if($logo)<img class="brand-logo" src="{{ $logo }}" alt="SNAPIE logo">@endif
                <span class="brand-copy"><strong>Smart NSTP</strong><span>Management and AI-Integrated Platform</span></span>
            </td>
            <td class="title-cell"><h1>{{ $report['title'] }}</h1><div class="generated">Generated {{ $report['generated_at']->format('F d, Y - h:i A') }}</div></td>
        </tr>
    </table>

    <div class="filter-summary"><strong>Applied filters:</strong> {{ $filterSummary }}</div>
    <div class="record-count">Total records: {{ $report['rows']->count() }}</div>

    <table class="report-table">
        <thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>@foreach($row as $value)<td>{{ $value === '—' ? '-' : $value }}</td>@endforeach</tr>
            @empty
                <tr><td class="empty" colspan="{{ count($report['headers']) }}">No records matched the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="report-footer">Generated automatically by Smart NSTP.</div>
</body>
</html>
