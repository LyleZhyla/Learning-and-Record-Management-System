@extends($layout)

@section('title', 'Community Projects')
@section('page-title', 'Community Project Management')

@section('content')
    <section class="welcome-banner">
        <div><span class="eyebrow">Community engagement</span><h2>Projects from proposal to completion</h2><p>Track beneficiaries, location, budget, approval, planned activities, accomplishments, and implementation status in one record.</p></div>
        <a class="primary-button" href="{{ route($routePrefix.'.community-projects.create') }}">+ New project proposal</a>
    </section>

    @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="card user-table-card">
        <form method="GET" class="report-filter-grid" style="padding: 1.25rem">
            <label class="field-group"><span>Search</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Title, reference, location, beneficiary"></label>
            <label class="field-group"><span>Approval</span><select name="approval_status"><option value="">All approval statuses</option>@foreach($approvalStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['approval_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Implementation</span><select name="implementation_status"><option value="">All implementation statuses</option>@foreach($implementationStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['implementation_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <div class="report-filter-actions"><button class="filter-button" type="submit">Apply filters</button><a class="clear-filter" href="{{ route($routePrefix.'.community-projects.index') }}">Clear</a></div>
        </form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>Scope</th><th>Beneficiaries</th><th>Budget</th><th>Approval</th><th>Implementation</th><th>Activities</th><th></th></tr></thead><tbody>
            @forelse($projects as $project)
                <tr>
                    <td><strong>{{ $project->title }}</strong><small class="table-secondary-line">{{ $project->reference_number }} · Proposed by {{ $project->proposer->name }}</small></td>
                    <td>{{ $project->component->code }}<small class="table-secondary-line">{{ $project->section?->code ?? 'Component-wide' }} · {{ $project->location }}</small></td>
                    <td>{{ $project->beneficiary_count ? number_format($project->beneficiary_count).' · ' : '' }}{{ str($project->beneficiaries)->limit(55) }}</td>
                    <td>₱{{ number_format((float) $project->budget, 2) }}</td>
                    <td><span class="state {{ $project->approval_status === 'approved' ? 'done' : '' }}">{{ $project->approvalLabel() }}</span></td>
                    <td><span class="state {{ $project->implementation_status === 'completed' ? 'done' : '' }}">{{ $project->implementationLabel() }}</span></td>
                    <td>{{ $project->activities_count }}</td>
                    <td><a href="{{ route($routePrefix.'.community-projects.show', $project) }}">Open →</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state"><strong>No community projects yet</strong><span>Create the first proposal to begin approval and implementation tracking.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
        @if($projects->total() > 0)<div class="pagination-row">{{ $projects->links() }}</div>@endif
    </section>
@endsection
