@extends($layout)
@section('title', 'Automatic Scheduling')
@section('page-title', 'Automatic Scheduling')

@section('content')
<link rel="stylesheet" href="{{ asset('css/scheduling.css') }}?v={{ filemtime(public_path('css/scheduling.css')) }}">
@php
    $timeLabel = fn ($time) => $time ? \Carbon\Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time.':00' : $time)->format('g:i A') : '—';
    $hours = old('session_hours', number_format($setting->session_minutes / 60, 2, '.', ''));
@endphp

<section class="page-actions">
    <div><span class="eyebrow">Conflict-aware section planner</span><h2>Automatic section scheduling</h2><p>Set the available hours and session length. Sections handled by the same facilitator will automatically receive different time slots.</p></div>
</section>

<section class="card schedule-filter-card">
    <form method="GET" action="{{ route($routePrefix.'.schedules.index') }}" class="schedule-filter-grid">
        @if(!auth()->user()->isCoordinator())
            <label class="field-group"><span>NSTP component</span><select name="component_id">@foreach($components as $component)<option value="{{ $component->id }}" @selected($componentId === $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
        @endif
        <label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ $academicYear }}" pattern="\d{4}-\d{4}" required></label>
        <label class="field-group"><span>Semester</span><select name="semester">@foreach(\App\Models\NstpSection::SEMESTERS as $value => $label)<option value="{{ $value }}" @selected($semester === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="filter-button" type="submit">Open schedule</button>
    </form>
</section>

<section class="schedule-workspace">
    <article class="card schedule-settings-card">
        <div class="card-heading"><div><span class="eyebrow">Step 1</span><h3>Scheduling hours</h3><p>Times use your device's time picker. The AM/PM equivalent is shown in the schedule preview.</p></div></div>
        <form method="POST" action="{{ route($routePrefix.'.schedules.settings.update') }}">
            @csrf @method('PUT')
            <input type="hidden" name="component_id" value="{{ $componentId }}">
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <input type="hidden" name="semester" value="{{ $semester }}">
            <div class="schedule-settings-grid">
                <label class="field-group full"><span>Meeting day</span><select name="day_of_week" required>@foreach($days as $value => $label)<option value="{{ $value }}" @selected((int) old('day_of_week', $setting->day_of_week) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="field-group"><span>Day starts</span><input type="time" name="day_start" value="{{ old('day_start', substr($setting->day_start, 0, 5)) }}" required><small>Choose AM or PM in the time picker.</small></label>
                <label class="field-group"><span>Day ends</span><input type="time" name="day_end" value="{{ old('day_end', substr($setting->day_end, 0, 5)) }}" required><small>Latest allowed section end.</small></label>
                <label class="field-group"><span>Noon break starts</span><input type="time" name="break_start" value="{{ old('break_start', $setting->break_start ? substr($setting->break_start, 0, 5) : '') }}"><small>Leave both break fields empty for no break.</small></label>
                <label class="field-group"><span>Noon break ends</span><input type="time" name="break_end" value="{{ old('break_end', $setting->break_end ? substr($setting->break_end, 0, 5) : '') }}"></label>
                <label class="field-group full"><span>Required hours per section</span><input type="number" name="session_hours" value="{{ $hours }}" min="0.5" max="12" step="0.5" required><small>The break is excluded from required class hours.</small></label>
            </div>
            @error('break_start')<div class="schedule-error">{{ $message }}</div>@enderror
            @error('session_hours')<div class="schedule-error">{{ $message }}</div>@enderror
            <div class="form-actions"><button class="secondary-outline-button" type="submit">Save scheduling hours</button></div>
        </form>
    </article>

    <article class="card schedule-generation-card">
        <div><span class="eyebrow">Step 2</span><h3>Generate schedule</h3><p>The system checks every section assigned to the same facilitator in {{ $academicYear }} before choosing a time.</p></div>
        <dl>
            <div><dt>Meeting day</dt><dd>{{ $days[$setting->day_of_week] }}</dd></div>
            <div><dt>Available time</dt><dd>{{ $timeLabel($setting->day_start) }} – {{ $timeLabel($setting->day_end) }}</dd></div>
            <div><dt>Noon break</dt><dd>{{ $setting->break_start ? $timeLabel($setting->break_start).' – '.$timeLabel($setting->break_end) : 'No break' }}</dd></div>
            <div><dt>Each section</dt><dd>{{ number_format($setting->session_minutes / 60, 1) }} hours</dd></div>
            <div><dt>Active sections</dt><dd>{{ $sections->count() }}</dd></div>
        </dl>
        @error('schedule')<div class="schedule-error">{{ $message }}</div>@enderror
        <form method="POST" action="{{ route($routePrefix.'.schedules.generate') }}">
            @csrf
            <input type="hidden" name="component_id" value="{{ $componentId }}">
            <input type="hidden" name="academic_year" value="{{ $academicYear }}">
            <input type="hidden" name="semester" value="{{ $semester }}">
            <button class="primary-button compact" type="submit" @disabled(!$setting->exists || $sections->isEmpty())>Run automatic scheduling</button>
        </form>
        @if(!$setting->exists)<small class="schedule-note">Save the scheduling hours first.</small>@endif
    </article>
</section>

<section class="card user-table-card schedule-table-card">
    <div class="sectioning-toolbar"><div><span class="eyebrow">Editable results</span><h3>Section schedules</h3><p class="muted-cell">Manual changes must keep the required class hours and cannot overlap another section of the same facilitator.</p></div></div>
    <div class="table-wrap"><table class="data-table schedule-table"><thead><tr><th>Section</th><th>Facilitator</th><th>Current schedule</th><th>Manual adjustment</th><th>Source</th></tr></thead><tbody>
        @forelse($sections as $section)
            @php($schedule = $section->schedule)
            <tr>
                <td><strong>{{ $section->code }}</strong><small>{{ $section->name }}</small></td>
                <td>{{ $section->facilitator?->name ?? 'Unassigned' }}</td>
                <td>@if($schedule)<strong>{{ $days[$schedule->day_of_week] }}</strong><small>{{ $timeLabel($schedule->starts_at) }} – {{ $timeLabel($schedule->ends_at) }}</small>@else<span class="muted-cell">Not scheduled</span>@endif</td>
                <td>
                    <form method="POST" action="{{ route($routePrefix.'.schedules.sections.update', $section) }}" class="schedule-inline-form">
                        @csrf @method('PUT')
                        <input type="hidden" name="component_id" value="{{ $componentId }}">
                        <select name="day_of_week" aria-label="Meeting day">@foreach($days as $value => $label)<option value="{{ $value }}" @selected((int) ($schedule?->day_of_week ?? $setting->day_of_week) === $value)>{{ substr($label, 0, 3) }}</option>@endforeach</select>
                        <input type="time" name="starts_at" value="{{ $schedule ? substr($schedule->starts_at, 0, 5) : '' }}" aria-label="Start time" required>
                        <span>to</span>
                        <input type="time" name="ends_at" value="{{ $schedule ? substr($schedule->ends_at, 0, 5) : '' }}" aria-label="End time" required>
                        <button class="filter-button" type="submit">Save</button>
                    </form>
                    @if($errors->has('starts_at') || $errors->has('ends_at'))<small class="field-error">{{ $errors->first('starts_at') ?: $errors->first('ends_at') }}</small>@endif
                </td>
                <td><span class="status-badge {{ $schedule?->is_automatic ? 'active' : 'inactive' }}"><i></i>{{ !$schedule ? 'Pending' : ($schedule->is_automatic ? 'Automatic' : 'Manual') }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty-state"><strong>No active sections in this term</strong><span>Create or activate sections before generating a schedule.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection
