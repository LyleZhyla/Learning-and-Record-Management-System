@props(['calendar', 'calendarRoute', 'description'])

@php
    $month = $calendar['month'];
    $monthEvents = $calendar['month_events'];
@endphp

<section class="card activity-calendar" aria-labelledby="activity-calendar-title">
    <div class="activity-calendar-heading">
        <div>
            <span class="eyebrow">Activity calendar</span>
            <h3 id="activity-calendar-title">{{ $month->format('F Y') }}</h3>
            <p>{{ $description }}</p>
        </div>
        <div class="activity-calendar-navigation" aria-label="Calendar navigation">
            <a href="{{ route($calendarRoute, ['month' => $month->subMonth()->format('Y-m')]) }}#activity-calendar-title" aria-label="Previous month">‹</a>
            <a class="today" href="{{ route($calendarRoute) }}#activity-calendar-title">Today</a>
            <a href="{{ route($calendarRoute, ['month' => $month->addMonth()->format('Y-m')]) }}#activity-calendar-title" aria-label="Next month">›</a>
        </div>
    </div>

    <div class="activity-calendar-layout">
        <div class="activity-calendar-shell">
            <div class="activity-calendar-weekdays" aria-hidden="true">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)<span>{{ $weekday }}</span>@endforeach
            </div>
            <div class="activity-calendar-grid" role="grid" aria-label="Activities for {{ $month->format('F Y') }}">
                @foreach($calendar['days'] as $day)
                    @php($dayEvents = $calendar['events_by_date']->get($day->toDateString(), collect()))
                    <article class="activity-calendar-day {{ $day->month !== $month->month ? 'outside-month' : '' }} {{ $day->isToday() ? 'is-today' : '' }}" role="gridcell" aria-label="{{ $day->format('F j, Y') }}{{ $dayEvents->isNotEmpty() ? ', '.$dayEvents->count().' scheduled items' : '' }}">
                        <time datetime="{{ $day->toDateString() }}">{{ $day->day }}</time>
                        <div class="activity-calendar-events">
                            @foreach($dayEvents->take(3) as $event)
                                <a class="activity-calendar-event {{ $event['type'] }}" href="{{ $event['url'] }}" title="{{ $event['category'] }}: {{ $event['title'] }}">
                                    @if($event['time'])<span>{{ $event['time'] }}</span>@endif
                                    <strong>{{ $event['title'] }}</strong>
                                </a>
                            @endforeach
                            @if($dayEvents->count() > 3)<span class="activity-calendar-more">+{{ $dayEvents->count() - 3 }} more</span>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <aside class="activity-calendar-agenda" aria-label="Monthly activity agenda">
            <div class="activity-calendar-agenda-heading">
                <div><span class="eyebrow">Monthly agenda</span><h4>{{ $monthEvents->count() }} scheduled {{ \Illuminate\Support\Str::plural('item', $monthEvents->count()) }}</h4></div>
                <div class="activity-calendar-legend" aria-label="Activity types"><span class="assessment">Assessment</span><span class="project">Project</span><span class="attendance">Attendance</span></div>
            </div>
            <div class="activity-calendar-agenda-list">
                @forelse($monthEvents as $event)
                    <a href="{{ $event['url'] }}" class="activity-agenda-item {{ $event['type'] }}">
                        <time datetime="{{ $event['date'] }}"><strong>{{ \Carbon\Carbon::parse($event['date'])->format('d') }}</strong><span>{{ \Carbon\Carbon::parse($event['date'])->format('M') }}</span></time>
                        <div><span>{{ $event['category'] }}{{ $event['time'] ? ' · '.$event['time'] : '' }}</span><strong>{{ $event['title'] }}</strong><small>{{ $event['meta'] }}</small></div>
                    </a>
                @empty
                    <div class="activity-calendar-empty"><span aria-hidden="true">○</span><strong>No scheduled activities</strong><p>Add due dates or project activities to place them on this calendar.</p></div>
                @endforelse
            </div>
        </aside>
    </div>
</section>
