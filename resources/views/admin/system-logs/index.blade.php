@extends('layouts.admin')
@section('title', 'System Logs')
@section('page-title', 'System Logs')

@section('content')
<div class="page-actions">
    <div><span class="eyebrow">Audit trail</span><h2>User activity logs</h2><p>Review authenticated activity across SNAPIE. Form contents and passwords are never stored in these logs.</p></div>
</div>

<section class="system-log-metrics">
    <article><span class="metric-icon blue">◎</span><div><strong>{{ $metrics['today'] }}</strong><small>Actions today</small></div></article>
    <article><span class="metric-icon green">♙</span><div><strong>{{ $metrics['active_users'] }}</strong><small>Active users · 24h</small></div></article>
    <article><span class="metric-icon orange">↻</span><div><strong>{{ $metrics['changes'] }}</strong><small>Changes today</small></div></article>
    <article><span class="metric-icon violet">!</span><div><strong>{{ $metrics['errors'] }}</strong><small>Errors today</small></div></article>
</section>

<section class="card report-filter-card system-log-filters">
    <form method="GET" class="system-log-filter-grid">
        <label class="field-group system-log-search"><span>Search</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, activity, route, or IP"></label>
        <label class="field-group"><span>Role</span><select name="role"><option value="">All roles</option>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field-group"><span>Action</span><select name="action"><option value="">All actions</option>@foreach($availableActions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ str($action)->replace('_', ' ')->headline() }}</option>@endforeach</select></label>
        <label class="field-group"><span>Result</span><select name="status"><option value="">All results</option><option value="success" @selected(($filters['status'] ?? '') === 'success')>Successful</option><option value="error" @selected(($filters['status'] ?? '') === 'error')>Errors</option></select></label>
        <label class="field-group"><span>From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
        <label class="field-group"><span>To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
        <div class="report-filter-actions"><button class="filter-button">Apply filters</button><a class="clear-filter" href="{{ route('admin.system-logs.index') }}">Clear</a></div>
    </form>
</section>

<section class="card user-table-card system-log-card">
    <div class="table-wrap"><table class="data-table system-log-table"><thead><tr><th>Date & time</th><th>User</th><th>Activity</th><th>Request</th><th>Result</th><th>IP / Device</th></tr></thead><tbody>
        @forelse($logs as $log)
            <tr>
                <td class="log-time"><strong>{{ $log->created_at->format('M d, Y') }}</strong><small>{{ $log->created_at->format('g:i:s A') }}</small></td>
                <td><div class="log-actor"><span>{{ strtoupper(substr($log->actor_name, 0, 1)) }}</span><div><strong>{{ $log->actor_name }}</strong><small>{{ $log->actor_email }}</small><em>{{ \App\Models\User::ROLE_LABELS[$log->actor_role] ?? str($log->actor_role)->headline() }}</em></div></div></td>
                <td><span class="log-action action-{{ $log->action }}">{{ str($log->action)->replace('_', ' ')->headline() }}</span><p>{{ $log->description }}</p></td>
                <td><code>{{ $log->method }}</code><span>{{ $log->route_name ?? $log->path }}</span>@if($log->metadata)<details><summary>Context</summary><pre>{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif</td>
                <td><span class="log-result {{ $log->status_code < 400 ? 'success' : 'error' }}">{{ $log->status_code }}</span><small>{{ $log->duration_ms }} ms</small></td>
                <td class="log-device"><strong>{{ $log->ip_address ?? 'Unknown IP' }}</strong><small title="{{ $log->user_agent }}">{{ Str::limit($log->user_agent ?: 'Unknown device', 48) }}</small></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty-state"><strong>No system logs found</strong><span>Try changing the filters or wait for new user activity.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="pagination-row"><span>Showing {{ $logs->count() }} of {{ $logs->total() }} entries</span>{{ $logs->links() }}</div>
</section>
@endsection
