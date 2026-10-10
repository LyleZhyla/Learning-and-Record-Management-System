@extends($layout)

@section('title', 'Registration Reviews')
@section('page-title', 'Registration Reviews')

@section('content')
    <section class="registration-review-hero">
        <div>
            <span class="eyebrow">Registration review workspace</span>
            <h2>Verify every application with confidence.</h2>
            <p>Review the student profile, inspect both required files, and record a clear decision from one focused workspace.</p>
        </div>
        <div class="registration-queue-summary" aria-label="Registration queue summary">
            <span><strong>{{ number_format($registrations->total()) }}</strong><small>Matching results</small></span>
            <span><strong>{{ number_format($activeCount) }}</strong><small>Active records</small></span>
            <span><strong>{{ number_format($archivedCount) }}</strong><small>Archived</small></span>
        </div>
    </section>

    <section class="card registration-period-control">
        <div class="registration-period-overview">
            <span class="registration-period-icon {{ $registrationOpen ? 'is-open' : 'is-closed' }}" aria-hidden="true">{{ $registrationOpen ? '●' : '—' }}</span>
            <div><span class="eyebrow">Public registration period</span><h3>{{ $registrationOpen ? 'Accepting applications' : 'Applications are paused' }}</h3><p>{{ $semesters[$registrationSemester] ?? str($registrationSemester)->headline() }} · {{ $registrationAcademicYear }}</p></div>
        </div>
        <details class="registration-period-settings">
            <summary>Manage registration period <span aria-hidden="true">⌄</span></summary>
            <form method="POST" action="{{ route($routePrefix.'.registrations.settings.update') }}">@csrf @method('PATCH')
                <label class="field-group"><span>Status</span><select name="student_registration_open"><option value="1" @selected($registrationOpen)>Open</option><option value="0" @selected(! $registrationOpen)>Closed</option></select></label>
                <label class="field-group"><span>Academic year</span><input name="student_registration_academic_year" value="{{ $registrationAcademicYear }}" pattern="\d{4}-\d{4}" required></label>
                <label class="field-group"><span>Semester</span><select name="student_registration_semester">@foreach($semesters as $value => $label)<option value="{{ $value }}" @selected($registrationSemester === $value)>{{ $label }}</option>@endforeach</select></label>
                <button class="primary-button compact" type="submit">Save period</button>
            </form>
        </details>
    </section>

    <nav class="registration-record-tabs" aria-label="Registration record state">
        <a href="{{ route($routePrefix.'.registrations.index', ['record_state' => 'active']) }}" class="{{ $recordState === 'active' ? 'selected' : '' }}"><span>Active review queue</span><strong>{{ number_format($activeCount) }}</strong></a>
        <a href="{{ route($routePrefix.'.registrations.index', ['record_state' => 'archived']) }}" class="{{ $recordState === 'archived' ? 'selected' : '' }}"><span>Archived records</span><strong>{{ number_format($archivedCount) }}</strong></a>
    </nav>

    @if($recordState === 'active')
    <div class="registration-status-grid" aria-label="Filter active registrations by review status">
        @foreach($statuses as $value => $label)
            <a href="{{ route($routePrefix.'.registrations.index', ['status' => $value, 'record_state' => 'active']) }}" class="registration-status-card status-card-{{ $value }} {{ request('status') === $value ? 'selected' : '' }}">
                <strong>{{ number_format($statusCounts[$value] ?? 0) }}</strong>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </div>
    @endif

    <section class="card user-table-card registration-list-card">
        <header class="registration-list-heading">
            <div><span class="eyebrow">{{ $recordState === 'archived' ? 'Records archive' : 'Review queue' }}</span><h3>{{ $recordState === 'archived' ? 'Archived registrations' : 'Student applications' }}</h3><p>{{ $registrations->total() }} result{{ $registrations->total() === 1 ? '' : 's' }} in the current view</p></div>
            @if(request()->hasAny(['search', 'status']))<span class="registration-filter-active">Filters active</span>@endif
        </header>
        <form class="filter-bar" method="GET" action="{{ route($routePrefix.'.registrations.index') }}">
            <input type="hidden" name="record_state" value="{{ $recordState }}">
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
                <a class="clear-filter" href="{{ route($routePrefix.'.registrations.index', ['record_state' => $recordState]) }}">Clear</a>
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
                            <td data-label="Applicant">
                                <div class="user-cell">
                                    <span class="table-avatar">{{ str($registration->first_name)->substr(0, 1) }}{{ str($registration->last_name)->substr(0, 1) }}</span>
                                    <span>
                                        <strong>{{ $registration->last_name }}, {{ $registration->first_name }} {{ $registration->middle_name }}</strong>
                                        <small>{{ $registration->email }} · {{ $registration->reference_code }}</small>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Academic details"><strong>{{ $registration->student_number }}</strong><br><span class="muted-cell">{{ $registration->college }} · {{ $registration->course }}</span><br><small class="muted-cell">{{ str($registration->nstp_level)->replace('_', ' ')->upper() }} · {{ ucfirst($registration->semester ?? 'Unspecified') }} · {{ $registration->academic_year ?? 'Legacy record' }}</small></td>
                            <td data-label="Submitted">{{ $registration->created_at->format('M d, Y') }}<br><span class="muted-cell">{{ $registration->created_at->format('g:i A') }}</span></td>
                            <td data-label="Required documents">
                                <span class="document-count {{ $completeCount === 2 ? 'complete' : 'incomplete' }}">{{ $completeCount }} of 2 files available</span>
                                <small class="document-summary">COR: {{ $documents['cor']['complete'] ? 'ready' : 'issue found' }} · Photo: {{ $documents['formal_photo']['complete'] ? 'ready' : 'issue found' }}</small>
                            </td>
                            <td data-label="Review status"><span class="registration-status review-category-badge" style="--review-category-color: {{ $registration->statusColor() }}">{{ $registration->statusLabel() }}</span></td>
                            <td class="align-right" data-label="Action">
                                <div class="account-row-actions">
                                    <a class="table-action" href="{{ route($routePrefix.'.registrations.show', $registration) }}">{{ $registration->archived_at ? 'View record' : 'Review documents' }} →</a>
                                    @if($registration->archived_at)
                                        <form method="POST" action="{{ route($routePrefix.'.registrations.restore', $registration) }}">@csrf @method('PATCH')<button class="clear-filter" type="submit">Restore</button></form>
                                    @else
                                        <form method="POST" action="{{ route($routePrefix.'.registrations.archive', $registration) }}" onsubmit="return confirm('Archive this registration? It will move out of the active list but can be restored.')">@csrf @method('PATCH')<button class="clear-filter" type="submit">Archive</button></form>
                                    @endif
                                </div>
                            </td>
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
