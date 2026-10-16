@extends('layouts.admin')
@section('title', 'Records Archive')
@section('page-title', 'Records Archive')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/archive-management.css') }}?v={{ filemtime(public_path('css/archive-management.css')) }}">
@endpush

@section('content')
@php
    $totalActive = $groups->sum('active_count');
    $totalArchived = $groups->sum('archived_count');
@endphp

<section class="page-actions archive-page-heading">
    <div><span class="eyebrow">Data retention</span><h2>Records archive</h2><p>Review archived operational records, export retained data, and move completed active records into the archive.</p></div>
    <div class="page-action-buttons"><a class="secondary-outline-button" href="{{ route('admin.workflows.index', ['module' => 'archives']) }}">Archiving rules</a><a class="primary-button compact" href="{{ route('admin.database-backup.index') }}#record-maintenance">Database maintenance →</a></div>
</section>

<section class="system-log-metrics archive-overview-metrics" aria-label="Archive overview">
    <article><span class="metric-icon blue">▱</span><div><strong>{{ $groups->count() }}</strong><small>Record groups</small></div></article>
    <article><span class="metric-icon green">●</span><div><strong>{{ number_format($totalActive) }}</strong><small>Active records</small></div></article>
    <article><span class="metric-icon orange">◫</span><div><strong>{{ number_format($totalArchived) }}</strong><small>Archived records</small></div></article>
    <article><span class="metric-icon violet">⇩</span><div><strong>CSV</strong><small>Export format</small></div></article>
</section>

<section class="card password-boundary-note archive-safety-note"><span>ℹ</span><div><strong>Restore and delete controls moved</strong><p>Record restoration and permanent deletion are now handled only in Database Management, together with backups and recovery snapshots.</p></div><a class="secondary-outline-button" href="{{ route('admin.database-backup.index') }}#record-maintenance">Open Database Management</a></section>

<section class="archive-group-grid" aria-label="Archivable record groups">
    @foreach($groups as $group)
        @php($total = $group['active_count'] + $group['archived_count'])
        <article class="card archive-group-card">
            <div class="archive-group-title"><span>{{ $group['icon'] }}</span><div><h3>{{ $group['label'] }}</h3><p>{{ $group['description'] }}</p></div></div>
            <dl><div><dt>Active</dt><dd>{{ number_format($group['active_count']) }}</dd></div><div><dt>Archived</dt><dd>{{ number_format($group['archived_count']) }}</dd></div></dl>
            <div class="archive-retention-bar" aria-label="{{ $group['label'] }} archive ratio"><span style="width:{{ $total ? round(($group['archived_count'] / $total) * 100) : 0 }}%"></span></div>
            <small class="archive-retention-caption">{{ $total ? round(($group['archived_count'] / $total) * 100) : 0 }}% currently archived</small>
            <div class="archive-actions">
                <a class="secondary-outline-button" href="{{ route('admin.archives.export', $group['type']) }}">Download archive</a>
                <form method="POST" action="{{ route('admin.archives.archive', $group['type']) }}" onsubmit="return confirm('Archive all active {{ strtolower($group['label']) }}? They will be removed from normal operational screens.')">@csrf<button class="primary-button compact" type="submit" @disabled(!$group['active_count'])>Archive all active</button></form>
            </div>
        </article>
    @endforeach
</section>

<section class="card user-table-card archive-history-card">
    <div class="sectioning-toolbar"><div><span class="eyebrow">Archive activity</span><h3>Recently archived records</h3><p class="muted-cell">Latest records moved out of active operational screens.</p></div><a class="secondary-outline-button" href="{{ route('admin.database-backup.index') }}#record-maintenance">Manage archived records</a></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Record type</th><th>Record</th><th>Details</th><th>Archived</th></tr></thead><tbody>
        @forelse($recentArchives as $record)
            <tr><td><span class="component-mini-badge">{{ $record['type'] }}</span></td><td><strong>{{ $record['title'] }}</strong></td><td class="muted-cell">{{ $record['detail'] }}</td><td>{{ $record['archived_at']?->format('M d, Y · h:i A') }}</td></tr>
        @empty
            <tr><td colspan="4"><div class="empty-state"><strong>No archived records yet</strong><span>Archive completed operational records using the cards above.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
</section>

<section class="card password-boundary-note archive-accountability-note"><span>✓</span><div><strong>Archive actions remain auditable</strong><p>Archiving keeps records available for reporting while removing them from active screens. Every archive operation remains attributable through System Logs.</p></div></section>
@endsection
