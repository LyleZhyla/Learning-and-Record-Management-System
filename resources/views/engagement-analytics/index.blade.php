@extends($layout)
@section('title', 'Engagement Analytics')
@section('page-title', 'Engagement Analytics')
@section('content')
<div class="page-actions">
    <div>
        <span class="eyebrow">Consolidated student signals</span>
        <h2>{{ $isStudent ? 'My engagement summary' : 'Student engagement overview' }}</h2>
        <p>Combines attendance, 30-day login activity, learning-material use, project-task participation, and assessment submissions. Missing opportunities are excluded instead of lowering the score.</p>
    </div>
</div>

<section class="metric-grid engagement-metrics">
    <article class="metric-card"><span class="metric-icon blue">◎</span><div><small>{{ $isStudent ? 'Engagement score' : 'Average score' }}</small><strong>{{ $metrics['average'] === null ? '—' : number_format($metrics['average'], 1).'%' }}</strong><p>Across available signals</p></div></article>
    <article class="metric-card"><span class="metric-icon green">✓</span><div><small>Engaged</small><strong>{{ $metrics['engaged'] }}</strong><p>75% and above</p></div></article>
    <article class="metric-card"><span class="metric-icon orange">◷</span><div><small>Monitor</small><strong>{{ $metrics['monitor'] }}</strong><p>50% to 74.9%</p></div></article>
    <article class="metric-card"><span class="metric-icon violet">!</span><div><small>At risk</small><strong>{{ $metrics['at_risk'] }}</strong><p>Below 50%</p></div></article>
</section>

@unless($isStudent)
<section class="card term-panel">
    <form method="GET" class="term-form engagement-filter-form">
        <label class="field-group"><span>Student</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email"></label>
        <label class="field-group"><span>Component</span><select name="component_id"><option value="">All components</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected(($filters['component_id'] ?? null)==$component->id)>{{ $component->code }}</option>@endforeach</select></label>
        <label class="field-group"><span>Section</span><select name="section_id"><option value="">All sections</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected(($filters['section_id'] ?? null)==$section->id)>{{ $section->code }} · {{ $section->component?->code }}</option>@endforeach</select></label>
        <label class="field-group"><span>Risk band</span><select name="status"><option value="">All bands</option><option value="engaged" @selected(($filters['status'] ?? '')==='engaged')>Engaged</option><option value="monitor" @selected(($filters['status'] ?? '')==='monitor')>Monitor</option><option value="at_risk" @selected(($filters['status'] ?? '')==='at_risk')>At risk</option></select></label>
        <button class="filter-button">Apply filters</button>
    </form>
</section>
@endunless

<section class="card user-table-card engagement-table-card">
    <div class="card-heading"><div><h3>{{ $isStudent ? 'Signal breakdown' : 'Student engagement details' }}</h3><p>The total is normalized using only signals with available activities or requirements.</p></div><span class="pill">{{ $metrics['total'] }} record{{ $metrics['total'] === 1 ? '' : 's' }}</span></div>
    <div class="table-wrap"><table class="data-table engagement-table">
        <thead><tr><th>Student</th><th>Score</th><th>Attendance</th><th>Login</th><th>Materials</th><th>Participation</th><th>Submissions</th><th>Band</th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td><strong>{{ $row['student']->name }}</strong><br><small class="muted-cell">{{ $row['student']->email }}</small><br><small class="muted-cell">{{ $row['enrollment']->component?->code }} · {{ $row['enrollment']->section?->code ?? 'No section' }}</small></td>
                <td><strong class="engagement-score">{{ number_format($row['score'], 1) }}%</strong><br><small class="muted-cell">{{ $row['coverage'] }}/5 signals</small></td>
                @foreach($row['factors'] as $factor)
                    <td title="{{ $factor['detail'] }}"><strong>{{ $factor['available'] ? number_format($factor['score'], 1).'%' : 'N/A' }}</strong><br><small class="muted-cell">{{ $factor['weight'] }}% weight</small><br><small class="engagement-detail">{{ $factor['detail'] }}</small></td>
                @endforeach
                <td><span class="engagement-status {{ $row['status'] }}">{{ $row['statusLabel'] }}</span>@if($row['lastActivityAt'])<small class="engagement-detail">Last activity {{ $row['lastActivityAt']->diffForHumans() }}</small>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state"><strong>No engagement record found</strong><span>{{ $isStudent ? 'Your engagement summary will appear after enrollment.' : 'Try changing the filters or confirm that students are enrolled.' }}</span></div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($rows->hasPages())<div class="pagination-row"><span>Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</span>{{ $rows->links() }}</div>@endif
</section>

<section class="card engagement-method-card">
    <div class="card-heading"><div><h3>How the score is calculated</h3><p>System Logs supply login and material-use events; they are only two inputs to this separate analytics module.</p></div></div>
    <div class="engagement-method-grid">
        <div><strong>30%</strong><span>Attendance</span><small>Present = 100%; late = 80%</small></div>
        <div><strong>15%</strong><span>Login activity</span><small>Up to eight sign-ins in 30 days</small></div>
        <div><strong>15%</strong><span>Materials</span><small>List visits and distinct downloads</small></div>
        <div><strong>20%</strong><span>Participation</span><small>Project-task progress and completion</small></div>
        <div><strong>20%</strong><span>Submissions</span><small>Submitted published assessments</small></div>
    </div>
</section>
@endsection
