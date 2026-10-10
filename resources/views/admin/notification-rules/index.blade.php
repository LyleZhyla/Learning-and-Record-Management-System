@extends($layout)

@section('title', 'Notification Rules')
@section('page-title', 'Notifications Configuration')

@section('content')
<section class="notification-config-hero">
    <div><span class="eyebrow">Configuration layer</span><h2>Control when and where notifications appear</h2><p>Enable each event, select its portal channels, customize its message template, and choose immediate or delayed delivery.</p></div>
    <div><strong>{{ $rules->where('is_enabled', true)->count() }}</strong><span>active of {{ $rules->count() }} rules</span></div>
</section>

<div class="notification-rule-grid">
@foreach($rules as $rule)
    @php($definition = config('notification_rules.'.$rule->event_key))
    <form method="POST" action="{{ route($routePrefix.'.notification-rules.update', $rule) }}" class="card notification-rule-card">
        @csrf
        @method('PUT')
        <header>
            <div><span class="notification-event-key">{{ str($rule->event_key)->replace('_', ' ') }}</span><input name="name" value="{{ $rule->name }}" maxlength="120" aria-label="Notification rule name" required></div>
            <label class="notification-enabled"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked($rule->is_enabled)><span>Enabled</span></label>
        </header>
        <textarea name="description" rows="2" maxlength="1000" aria-label="Description">{{ $rule->description }}</textarea>

        <fieldset class="notification-channel-picker">
            <legend>Portal channels</legend>
            @foreach($channels as $value => $label)
                <label><input type="checkbox" name="channels[]" value="{{ $value }}" @checked(in_array($value, $rule->channels ?? [], true))><span><strong>{{ $label }}</strong><small>{{ $value === 'bell' ? 'Show the event in the top notification panel.' : 'Show an unread count beside its navigation item.' }}</small></span></label>
            @endforeach
        </fieldset>

        <div class="notification-template-fields">
            <label class="field-group"><span>Title template</span><input name="title_template" value="{{ $rule->title_template }}" maxlength="180" required></label>
            <label class="field-group"><span>Body template</span><textarea name="body_template" rows="3" maxlength="2000" required>{{ $rule->body_template }}</textarea></label>
            <p><strong>Available placeholders</strong>@foreach($definition['placeholders'] as $placeholder)<code>{{ '{'.$placeholder.'}' }}</code>@endforeach</p>
        </div>

        <div class="notification-schedule-row">
            <label class="field-group"><span>Schedule</span><select name="schedule_mode"><option value="immediate" @selected($rule->schedule_mode === 'immediate')>Immediate</option><option value="delayed" @selected($rule->schedule_mode === 'delayed')>Delayed</option></select></label>
            <label class="field-group"><span>Delay in minutes</span><input type="number" name="delay_minutes" min="1" max="43200" value="{{ $rule->delay_minutes ?: 15 }}"></label>
        </div>

        <footer>
            <small>Last updated {{ $rule->updated_at?->diffForHumans() ?? 'from system defaults' }} by {{ $rule->updater?->name ?? 'System' }}</small>
            <button class="primary-button compact" type="submit">Save notification rule</button>
        </footer>
    </form>
@endforeach
</div>
@endsection
