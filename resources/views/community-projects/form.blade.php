@extends($layout)

@section('title', $project->exists ? 'Edit Community Project' : 'New Community Project')
@section('page-title', $project->exists ? 'Edit Community Project' : 'New Community Project')

@section('content')
    <section class="welcome-banner"><div><span class="eyebrow">{{ $project->exists ? $project->reference_number : 'Project proposal' }}</span><h2>{{ $project->exists ? 'Update the project plan' : 'Create a community project record' }}</h2><p>Use verified community needs. Avoid placing unnecessary private beneficiary information in the record.</p></div></section>
    @if($errors->any())<div class="alert error"><strong>Please correct the project details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $project->exists ? route($routePrefix.'.community-projects.update', $project) : route($routePrefix.'.community-projects.store') }}" class="card" style="padding: 1.25rem">
        @csrf @if($project->exists) @method('PUT') @endif
        <div class="form-grid">
            <label class="field-group full"><span>Project title</span><input name="title" value="{{ old('title', $project->title) }}" maxlength="255" required></label>
            @if(auth()->user()->isStudent())
                <div class="field-group"><span>NSTP component</span><strong>{{ $enrollment?->component?->code }}</strong><input type="hidden" name="component_id" value="{{ $enrollment?->component_id }}"></div>
                <div class="field-group"><span>Section</span><strong>{{ $enrollment?->section?->code ?? 'Not yet sectioned' }}</strong><input type="hidden" name="section_id" value="{{ $enrollment?->section_id }}"></div>
            @else
                <label class="field-group"><span>NSTP component</span><select name="component_id" required><option value="">Select component</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected((string) old('component_id', $project->component_id) === (string) $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
                <label class="field-group"><span>Section</span><select name="section_id"><option value="">Component-wide project</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected((string) old('section_id', $project->section_id) === (string) $section->id)>{{ $section->code }} · {{ $section->component->code }} · {{ $section->academic_year }}</option>@endforeach</select></label>
            @endif
            <label class="field-group full"><span>Project description / community need</span><textarea name="description" rows="5" maxlength="5000" required>{{ old('description', $project->description) }}</textarea></label>
            <label class="field-group full"><span>Objectives</span><textarea name="objectives" rows="4" maxlength="5000" required placeholder="List the measurable objectives of the project.">{{ old('objectives', $project->objectives) }}</textarea></label>
            <label class="field-group"><span>Target beneficiaries</span><textarea name="beneficiaries" rows="3" maxlength="2000" required placeholder="Describe the beneficiary group, not private individual names.">{{ old('beneficiaries', $project->beneficiaries) }}</textarea></label>
            <label class="field-group"><span>Estimated beneficiary count</span><input type="number" name="beneficiary_count" min="1" max="1000000" value="{{ old('beneficiary_count', $project->beneficiary_count) }}"></label>
            <label class="field-group"><span>Project location</span><input name="location" value="{{ old('location', $project->location) }}" maxlength="255" required></label>
            <label class="field-group"><span>Proposed budget (PHP)</span><input type="number" name="budget" min="0" max="9999999999.99" step="0.01" value="{{ old('budget', $project->budget ?? 0) }}" required></label>
            <label class="field-group"><span>Planned start date</span><input type="date" name="start_date" value="{{ old('start_date', $project->start_date?->toDateString()) }}"></label>
            <label class="field-group"><span>Planned end date</span><input type="date" name="end_date" value="{{ old('end_date', $project->end_date?->toDateString()) }}"></label>
        </div>
        <div class="form-actions"><a class="secondary-button" href="{{ $project->exists ? route($routePrefix.'.community-projects.show', $project) : route($routePrefix.'.community-projects.index') }}">Cancel</a><button class="primary-button" type="submit">{{ $project->exists ? 'Save project changes' : 'Submit project proposal' }}</button></div>
    </form>
@endsection
