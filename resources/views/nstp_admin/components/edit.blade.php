@extends($routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin')

@section('title', 'Configure '.$component->code)
@section('page-title', 'Configure '.$component->code)

@section('content')
    <div class="back-row"><a href="{{ route($routePrefix.'.components.index') }}">← Back to NSTP components</a></div>
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
@endsection
