@extends($layout)

@section('title', 'Create Honorarium Record')
@section('page-title', 'Create Facilitator Honorarium Record')

@section('content')
<section class="welcome-banner"><div><span class="eyebrow">Coordinator / NSTP Admin action</span><h2>Prepare the honorarium record</h2><p>This starts document preparation only. Approval and actual fund disbursement remain under the University Cashier.</p></div><a class="secondary-button" href="{{ route($routePrefix.'.honoraria.index') }}">Back to honoraria</a></section>
<form class="card" style="padding:1.25rem" method="POST" action="{{ route($routePrefix.'.honoraria.store') }}">@csrf
    <div class="report-filter-grid">
        <label class="field-group"><span>Component</span><select name="component_id" required>@foreach($components as $component)<option value="{{ $component->id }}" @selected((string)old('component_id', auth()->user()->nstp_component_id) === (string)$component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
        <label class="field-group"><span>Facilitator</span><select name="facilitator_id" required><option value="">Select facilitator</option>@foreach($facilitators as $facilitator)<option value="{{ $facilitator->id }}" @selected((string)old('facilitator_id') === (string)$facilitator->id)>{{ $facilitator->name }} — {{ $facilitator->facilitatedSections->pluck('component.code')->unique()->filter()->implode('/') }}</option>@endforeach</select></label>
        <label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ old('academic_year', now()->year.'-'.(now()->year + 1)) }}" required pattern="\d{4}-\d{4}"></label>
        <label class="field-group"><span>Semester</span><select name="semester" required>@foreach($semesters as $value => $label)<option value="{{ $value }}" @selected(old('semester') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field-group"><span>Service period start</span><input type="date" name="period_start" value="{{ old('period_start') }}" required></label>
        <label class="field-group"><span>Service period end</span><input type="date" name="period_end" value="{{ old('period_end') }}" required></label>
        <label class="field-group"><span>Gross honorarium</span><input type="number" name="gross_amount" value="{{ old('gross_amount') }}" min="0.01" max="9999999999.99" step="0.01" required></label>
        <label class="field-group"><span>Deductions</span><input type="number" name="deductions" value="{{ old('deductions', 0) }}" min="0" max="9999999999.99" step="0.01" required></label>
        <label class="field-group full"><span>Request notes</span><textarea name="request_notes" rows="4" maxlength="3000">{{ old('request_notes') }}</textarea></label>
        <div><button class="primary-button" type="submit">Send for document preparation</button></div>
    </div>
</form>
@endsection
