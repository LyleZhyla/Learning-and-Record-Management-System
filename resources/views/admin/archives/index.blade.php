@extends('layouts.admin')
@section('title', 'Records Archive')
@section('page-title', 'Records Archive')

@section('content')
<section class="page-actions archive-page-heading">
    <div><span class="eyebrow">Data retention</span><h2>Operational records archive</h2><p>Move completed records out of active screens without deleting them. Archived records remain stored and may be restored at any time.</p></div>
</section>

<section class="archive-group-grid" aria-label="Archivable record groups">
    @foreach($groups as $group)
        <article class="card archive-group-card">
            <div class="archive-group-title"><span>{{ $group['icon'] }}</span><div><h3>{{ $group['label'] }}</h3><p>{{ $group['description'] }}</p></div></div>
            <dl><div><dt>Active</dt><dd>{{ number_format($group['active_count']) }}</dd></div><div><dt>Archived</dt><dd>{{ number_format($group['archived_count']) }}</dd></div></dl>
            <div class="archive-actions">
                <form method="POST" action="{{ route('admin.archives.archive', $group['type']) }}" onsubmit="return confirm('Archive all active {{ strtolower($group['label']) }}? They will disappear from normal screens but can be restored here.')">@csrf<button class="secondary-outline-button" type="submit" @disabled(!$group['active_count'])>Archive all active</button></form>
                <form method="POST" action="{{ route('admin.archives.restore', $group['type']) }}" onsubmit="return confirm('Restore all archived {{ strtolower($group['label']) }} to active screens?')">@csrf @method('PATCH')<button class="clear-filter" type="submit" @disabled(!$group['archived_count'])>Restore all</button></form>
            </div>
        </article>
    @endforeach
</section>

<section class="card user-table-card archive-history-card">
    <div class="sectioning-toolbar"><div><span class="eyebrow">Archive activity</span><h3>Recently archived records</h3><p class="muted-cell">Latest records moved out of active operational screens.</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Record type</th><th>Record</th><th>Details</th><th>Archived</th></tr></thead><tbody>
        @forelse($recentArchives as $record)
            <tr><td><span class="component-mini-badge">{{ $record['type'] }}</span></td><td><strong>{{ $record['title'] }}</strong></td><td class="muted-cell">{{ $record['detail'] }}</td><td>{{ $record['archived_at']?->format('M d, Y · h:i A') }}</td></tr>
        @empty
            <tr><td colspan="4"><div class="empty-state"><strong>No archived records yet</strong><span>Use an Archive all active button above to move records here.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
</section>

<section class="card password-boundary-note archive-accountability-note"><span>ℹ</span><div><strong>Audit accountability</strong><p>Archiving system logs creates a new active audit entry documenting the archive action. This accountability record can be included in a later archive batch.</p></div></section>
@endsection
