@extends($layout)

@section('title', 'Registration Reviews')
@section('page-title', 'Registration Reviews')

@section('content')
    <section class="registration-review-hero">
        <div>
            <span class="eyebrow">Document verification</span>
            <h2>Review student registration documents</h2>
            <p>Check whether the submitted COR and formal photo are present and valid before recording your verification decision.</p>
        </div>
        <span class="registration-review-total">{{ $registrations->total() }} matching registration{{ $registrations->total() === 1 ? '' : 's' }}</span>
    </section>

    <div class="registration-status-grid">
        @foreach($statuses as $value => $label)
            <a href="{{ route($routePrefix.'.registrations.index', ['status' => $value]) }}" class="registration-status-card {{ request('status') === $value ? 'selected' : '' }}">
                <strong>{{ number_format($statusCounts[$value] ?? 0) }}</strong>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>

    <section class="card user-table-card">
        <form class="filter-bar" method="GET" action="{{ route($routePrefix.'.registrations.index') }}">
            <label class="search-field">
                <span>⌕</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, student number, email, or reference">
            </label>
            <select name="status" aria-label="Registration review status">
                <option value="">All review statuses</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="filter-button" type="submit">Apply filters</button>
            @if(request()->hasAny(['search', 'status']))
                <a class="clear-filter" href="{{ route($routePrefix.'.registrations.index') }}">Clear</a>
            @endif
        </form>

        <div class="table-wrap">
            <table class="data-table registration-review-table">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Academic details</th>
                        <th>Submitted</th>
                        <th>Required documents</th>
                        <th>Review status</th>
                        <th class="align-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registrations as $registration)
                        @php
                            $documents = $checklists[$registration->id];
                            $completeCount = collect($documents)->where('complete', true)->count();
                        @endphp
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <span class="table-avatar">{{ str($registration->first_name)->substr(0, 1) }}{{ str($registration->last_name)->substr(0, 1) }}</span>
                                    <span>
                                        <strong>{{ $registration->last_name }}, {{ $registration->first_name }} {{ $registration->middle_name }}</strong>
                                        <small>{{ $registration->email }} · {{ $registration->reference_code }}</small>
                                    </span>
                                </div>
                            </td>
                            <td><strong>{{ $registration->student_number }}</strong><br><span class="muted-cell">{{ $registration->college }} · {{ $registration->course }}</span></td>
                            <td>{{ $registration->created_at->format('M d, Y') }}<br><span class="muted-cell">{{ $registration->created_at->format('g:i A') }}</span></td>
                            <td>
                                <span class="document-count {{ $completeCount === 2 ? 'complete' : 'incomplete' }}">{{ $completeCount }} of 2 files available</span>
                                <small class="document-summary">COR: {{ $documents['cor']['complete'] ? 'ready' : 'issue found' }} · Photo: {{ $documents['formal_photo']['complete'] ? 'ready' : 'issue found' }}</small>
                            </td>
                            <td><span class="registration-status status-{{ $registration->status }}">{{ $registration->statusLabel() }}</span></td>
                            <td class="align-right"><a class="table-action" href="{{ route($routePrefix.'.registrations.show', $registration) }}">Review documents →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No registrations found</strong><span>Try changing the search or status filter.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($registrations->hasPages())
            <div class="registration-pagination">{{ $registrations->links() }}</div>
        @endif
    </section>
@endsection
