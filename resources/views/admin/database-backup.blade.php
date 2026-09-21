@extends('layouts.admin')
@section('title', 'Database Management')
@section('page-title', 'Database Management')

@section('content')
<div class="page-actions">
    <div><span class="eyebrow">Administration</span><h2>Database management</h2><p>Create, download, restore, and permanently delete private database archives.</p></div>
    <form method="POST" action="{{ route('admin.database-backup.archive') }}">@csrf<button class="primary-button compact">Archive current database</button></form>
</div>

<section class="system-log-metrics">
    <article><span class="metric-icon blue">▣</span><div><strong>{{ $database['tables'] }}</strong><small>Database tables</small></div></article>
    <article><span class="metric-icon green">⇩</span><div><strong>{{ $archives->count() }}</strong><small>Stored archives</small></div></article>
    <article><span class="metric-icon orange">◫</span><div><strong>{{ number_format($archiveSize / 1048576, 2) }} MB</strong><small>Archive storage</small></div></article>
    <article><span class="metric-icon violet">◎</span><div><strong>{{ strtoupper($database['driver']) }}</strong><small>{{ $database['name'] }}</small></div></article>
</section>

<section class="card form-card">
    <div class="card-heading"><div><span class="eyebrow">External recovery file</span><h3>Upload database backup</h3><p>Upload a SNAPIE-generated SQL backup to keep it as an archive or restore it immediately.</p></div><span class="settings-clock">↑</span></div>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.database-backup.upload') }}">
        @csrf
        <div class="form-grid">
            <label class="field-group full"><span>SNAPIE SQL backup</span><input type="file" name="database_file" accept=".sql" required><small class="form-help">Maximum size: 100 MB. The backup driver must match the active database.</small>@error('database_file')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="field-group"><span>Restore confirmation</span><input name="confirmation" value="" autocomplete="off" placeholder="Type RESTORE for immediate restore"><small class="form-help">Leave blank when uploading to the archive list only.</small>@error('confirmation')<small class="field-error">{{ $message }}</small>@enderror</label>
        </div>
        <div class="form-actions">
            <button class="secondary-outline-button" type="submit" name="action" value="archive">Upload to archives</button>
            <button class="danger-button" type="submit" name="action" value="restore" onclick="return confirm('Upload this file and replace the live database? A safety archive will be created first.')">Upload &amp; restore</button>
        </div>
    </form>
</section>

<section class="card user-table-card">
    <div class="sectioning-toolbar">
        <div><span class="eyebrow">Recovery snapshots</span><h3>Database archives</h3><p class="muted-cell">Every restore automatically saves the current database as a new safety archive first.</p></div>
        <form method="POST" action="{{ route('admin.database-backup.download') }}">@csrf<button class="secondary-outline-button">Download fresh SQL without archiving</button></form>
    </div>
    <div class="settings-impact-note"><span>!</span><p><strong>Restricted institutional data</strong>Archives can contain student profiles, attendance, grades, credentials, and account data. Keep downloaded files secure.</p></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Archive</th><th>Created</th><th>Size</th><th>Format</th><th class="align-right">Actions</th></tr></thead><tbody>
        @forelse($archives as $archive)
            <tr>
                <td><strong>{{ $archive['name'] }}</strong></td>
                <td>{{ $archive['created_at']->format('M d, Y · h:i:s A') }}</td>
                <td>{{ number_format($archive['size'] / 1024, 2) }} KB</td>
                <td><span class="component-mini-badge">SQL</span></td>
                <td class="align-right">
                    <div class="account-row-actions">
                        <a class="table-action" href="{{ route('admin.database-backup.archives.download', $archive['name']) }}">Download</a>
                        <button class="table-action" type="button" onclick="document.getElementById('restore-{{ $loop->index }}').showModal()">Restore</button>
                        <button class="link-danger" type="button" onclick="document.getElementById('delete-{{ $loop->index }}').showModal()">Delete</button>
                    </div>
                    <dialog class="student-qr-dialog" id="restore-{{ $loop->index }}">
                        <form method="POST" action="{{ route('admin.database-backup.archives.restore', $archive['name']) }}">@csrf
                            <div class="student-qr-dialog-heading"><div><span class="eyebrow">Destructive operation</span><strong>Restore database archive</strong></div><button type="button" onclick="this.closest('dialog').close()">×</button></div>
                            <p>This replaces the live database with <strong>{{ $archive['name'] }}</strong>. A safety archive of the current database will be created first.</p>
                            <label class="field-group"><span>Type RESTORE to confirm</span><input name="confirmation" required pattern="RESTORE" autocomplete="off"></label>
                            <div class="form-actions"><button class="danger-button">Restore database</button></div>
                        </form>
                    </dialog>
                    <dialog class="student-qr-dialog" id="delete-{{ $loop->index }}">
                        <form method="POST" action="{{ route('admin.database-backup.archives.destroy', $archive['name']) }}">@csrf @method('DELETE')
                            <div class="student-qr-dialog-heading"><div><span class="eyebrow">Permanent deletion</span><strong>Delete database archive</strong></div><button type="button" onclick="this.closest('dialog').close()">×</button></div>
                            <p>This permanently deletes <strong>{{ $archive['name'] }}</strong>. The file cannot be recovered from SNAPIE.</p>
                            <label class="field-group"><span>Type DELETE to confirm</span><input name="confirmation" required pattern="DELETE" autocomplete="off"></label>
                            <div class="form-actions"><button class="danger-button">Permanently delete</button></div>
                        </form>
                    </dialog>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty-state"><strong>No database archives yet</strong><span>Create an archive before a major update or data operation.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection
