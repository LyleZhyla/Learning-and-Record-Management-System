@extends($routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin')

@section('title', 'Configure '.$component->code)
@section('page-title', 'Configure '.$component->code)

@section('content')
    <div class="back-row"><a href="{{ route($routePrefix.'.sections.index') }}">← Back to sectioning</a></div>
    <div class="editor-grid">
        <section class="card">
            <div class="account-heading"><span class="large-avatar small">{{ substr($component->code, 0, 1) }}</span><div><span class="eyebrow">NSTP component</span><h2>{{ $component->code }}</h2><p>{{ $component->name }}</p></div></div>
            <form method="POST" action="{{ route($routePrefix.'.components.update', $component) }}" class="account-form">
                @csrf @method('PUT')
                <div class="form-grid">
                    <label class="field-group full"><span>Official component name</span><input name="name" value="{{ old('name', $component->name) }}" maxlength="150" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field-group full"><span>Description</span><textarea name="description" maxlength="1000" rows="5">{{ old('description', $component->description) }}</textarea>@error('description')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field-group"><span>Default section capacity</span><input type="number" name="default_section_capacity" value="{{ old('default_section_capacity', $component->default_section_capacity) }}" min="1" max="200" required>@error('default_section_capacity')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field-group"><span>Component status</span><select name="is_active" required><option value="1" @selected((string) old('is_active', (int) $component->is_active) === '1')>Active</option><option value="0" @selected((string) old('is_active', (int) $component->is_active) === '0')>Inactive</option></select>@error('is_active')<small class="field-error">{{ $message }}</small>@enderror</label>
                </div>
                <div class="form-actions"><button class="primary-button compact" type="submit">Save component settings</button></div>
            </form>
        </section>
        <aside class="card account-summary">
            <div class="card-heading"><div><span class="eyebrow">Current usage</span><h3>{{ $component->code }} overview</h3></div></div>
            <dl><div><dt>Existing sections</dt><dd>{{ $component->sections_count }}</dd></div><div><dt>Student enrollments</dt><dd>{{ $component->enrollments_count }}</dd></div><div><dt>Component code</dt><dd>{{ $component->code }}</dd></div></dl>
            <a class="text-link" href="{{ route($routePrefix.'.sections.create', ['component' => $component->id]) }}">Create a {{ $component->code }} section →</a>
        </aside>
    </div>

    @php($enabledAssessmentTypes = old('allowed_types', $assessmentProfile->allowed_types ?? []))
    @php($requiredRubricTypes = old('rubric_required_types', $assessmentProfile->rubric_required_types ?? []))
    <section class="card component-assessment-profile">
        <div class="card-heading"><div><span class="eyebrow">Assessment profile</span><h3>{{ $component->code }}-specific assessment settings</h3><p>These defaults automatically initialize new {{ $component->code }} section gradebooks and control which assessment types facilitators may create.</p></div><span class="component-profile-badge">{{ $component->code }}</span></div>
        <form method="POST" action="{{ route($routePrefix.'.components.assessment-profile.update', $component) }}">
            @csrf @method('PUT')

            <div class="component-profile-grid">
                <fieldset class="component-profile-types">
                    <legend>Enabled assessment types</legend>
                    @foreach($assessmentTypes as $type => $label)
                        <label><input type="checkbox" name="allowed_types[]" value="{{ $type }}" @checked(in_array($type, $enabledAssessmentTypes, true))><span><strong>{{ $label }}</strong><small>Allow {{ strtolower($label) }} assessments in {{ $component->code }} sections.</small></span></label>
                    @endforeach
                    @error('allowed_types')<small class="field-error">{{ $message }}</small>@enderror
                </fieldset>

                <div class="component-profile-defaults">
                    <label class="field-group"><span>Default assessment type</span><select name="default_type" required>@foreach($assessmentTypes as $type => $label)<option value="{{ $type }}" @selected(old('default_type', $assessmentProfile->default_type) === $type)>{{ $label }}</option>@endforeach</select>@error('default_type')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field-group"><span>Default maximum score</span><input type="number" name="default_max_score" min="1" max="10000" step="0.01" value="{{ old('default_max_score', $assessmentProfile->default_max_score) }}" required></label>
                    <fieldset class="component-rubric-types"><legend>Require a scoring rubric for</legend>@foreach($assessmentTypes as $type => $label)<label><input type="checkbox" name="rubric_required_types[]" value="{{ $type }}" @checked(in_array($type, $requiredRubricTypes, true))>{{ $label }}</label>@endforeach</fieldset>
                </div>
            </div>

            <div class="component-category-heading"><div><h4>Gradebook category templates</h4><p>Each enabled type must have one category. Enabled category weights must total exactly 100%.</p></div></div>
            <div class="component-category-templates">
                @foreach($assessmentTypes as $type => $label)
                    @php($category = $assessmentCategoryRows[$type])
                    <article class="component-category-template">
                        <input type="hidden" name="categories[{{ $type }}][assessment_type]" value="{{ $type }}">
                        <div class="component-category-type"><span style="--category-color: {{ old('categories.'.$type.'.color', $category['color']) }}"></span><strong>{{ $label }}</strong></div>
                        <label class="field-group"><span>Category name</span><input name="categories[{{ $type }}][name]" value="{{ old('categories.'.$type.'.name', $category['name']) }}" maxlength="80" required></label>
                        <label class="field-group"><span>Weight</span><div class="component-weight-input"><input type="number" name="categories[{{ $type }}][weight]" value="{{ old('categories.'.$type.'.weight', $category['weight']) }}" min="0.01" max="100" step="0.01" required><strong>%</strong></div></label>
                        <label class="field-group color"><span>Color</span><input type="color" name="categories[{{ $type }}][color]" value="{{ old('categories.'.$type.'.color', $category['color']) }}" required></label>
                    </article>
                @endforeach
            </div>
            @error('categories')<div class="alert danger" role="alert">{{ $message }}</div>@enderror

            <div class="component-grade-policy">
                <label class="field-group"><span>Passing percentage</span><input type="number" name="passing_percentage" min="1" max="99.99" step="0.01" value="{{ old('passing_percentage', $assessmentProfile->passing_percentage) }}" required></label>
                <label class="field-group"><span>Highest grade</span><input type="number" name="highest_grade" min="0" max="5" step="0.01" value="{{ old('highest_grade', $assessmentProfile->highest_grade) }}" required></label>
                <label class="field-group"><span>Passing grade</span><input type="number" name="passing_grade" min="0" max="5" step="0.01" value="{{ old('passing_grade', $assessmentProfile->passing_grade) }}" required></label>
                <label class="field-group"><span>Failing grade</span><input type="number" name="failing_grade" min="0" max="5" step="0.01" value="{{ old('failing_grade', $assessmentProfile->failing_grade) }}" required></label>
            </div>

            <label class="component-profile-apply"><input type="hidden" name="apply_to_empty_sections" value="0"><input type="checkbox" name="apply_to_empty_sections" value="1"><span><strong>Apply this profile to existing empty sections</strong><small>Sections without assessments will be reset to this profile. Sections with assessments keep their manual category and gradebook overrides.</small></span></label>
            <div class="component-profile-footer"><small>Last updated by {{ $assessmentProfile->updater?->name ?? 'System default' }}{{ $assessmentProfile->updated_at ? ' · '.$assessmentProfile->updated_at->format('M j, Y g:i A') : '' }}</small><button class="primary-button compact" type="submit">Save assessment profile</button></div>
        </form>
    </section>
@endsection
