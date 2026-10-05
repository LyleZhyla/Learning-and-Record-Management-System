<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 155px 24px 132px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17243c; font-family: Helvetica, Arial, sans-serif; font-size: 8px; }
        .official-document-header { position: fixed; top: -143px; right: 0; left: 0; width: 100%; }
        .official-document-footer { position: fixed; right: 0; bottom: -120px; left: 0; width: 100%; }
        .official-document-header img, .official-document-footer img { display: block; width: 100%; }
        .report-header { width: 100%; padding-bottom: 13px; border-bottom: 3px solid #173760; }
        .report-header td { vertical-align: middle; }
        .brand-cell { width: 52%; color: #008000; font-size: 11px; font-weight: bold; }
        .title-cell { width: 48%; text-align: right; }
        h1 { margin: 0; color: #173760; font-size: 17px; }
        .generated { margin-top: 4px; color: #64748b; font-size: 8px; }
        .filter-summary { margin: 12px 0; padding: 8px 10px; border-left: 3px solid #2468ca; background: #eef4fb; color: #334155; line-height: 1.35; }
        .record-count { margin-bottom: 7px; color: #64748b; font-size: 8px; }
        .section-group + .section-group { page-break-before: always; }
        .section-heading { margin: 0 0 8px; padding: 8px 10px; border-left: 3px solid #2468ca; background: #f5f8fc; }
        .section-heading h2 { margin: 0 0 3px; color: #173760; font-size: 12px; }
        .section-heading p { margin: 0; color: #64748b; font-size: 7px; }
        table.report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table thead { display: table-header-group; }
        .report-table th { padding: 7px 5px; border: 1px solid #173760; background: #173760; color: white; font-size: 7px; text-align: center; text-transform: uppercase; vertical-align: middle; }
        .report-table td { padding: 6px 5px; border-bottom: 1px solid #dce3ec; color: #27364d; line-height: 1.25; vertical-align: top; word-wrap: break-word; }
        .report-table tbody tr:nth-child(even) td { background: #f5f8fc; }
        .report-table tr { page-break-inside: avoid; }
        .empty { padding: 22px !important; color: #64748b !important; text-align: center; }
    </style>
</head>
<body>
    <div class="official-document-header"><img src="{{ $documentHeader }}" alt="Tarlac Agricultural University header"></div>
    <div class="official-document-footer"><img src="{{ $documentFooter }}" alt="Tarlac Agricultural University footer"></div>
    <table class="report-header">
        <tr>
            <td class="brand-cell">
                NATIONAL SERVICE TRAINING PROGRAM
            </td>
            <td class="title-cell"><h1>{{ $report['title'] }}</h1><div class="generated">Generated {{ $report['generated_at']->format('F d, Y - h:i A') }}</div></td>
        </tr>
    </table>

    <div class="filter-summary"><strong>Applied filters:</strong> {{ $filterSummary }}</div>
    <div class="record-count">Total records: {{ $report['rows']->count() }}</div>

    @if(array_key_exists('groups', $report) && $report['groups']->isNotEmpty())
        @foreach($report['groups'] as $group)
            <section class="section-group">
                <div class="section-heading"><h2>{{ $group['title'] }}</h2><p>{{ $group['subtitle'] }} | {{ $group['rows']->count() }} student{{ $group['rows']->count() === 1 ? '' : 's' }}</p></div>
                <table class="report-table"><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach($group['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value === '—' ? '-' : $value }}</td>@endforeach</tr>@endforeach</tbody></table>
            </section>
        @endforeach
    @else
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
    @endif
</body>
</html>
