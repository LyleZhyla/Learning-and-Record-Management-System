<section class="management-component-grid">
    @foreach ($componentCards as $component)
        <article class="management-component-card">
            <div class="component-card-top">
                <span class="component-code-mark">{{ substr($component->code, 0, 1) }}</span>
                <span class="status-badge {{ $component->is_active ? 'active' : 'inactive' }}"><i></i>{{ $component->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <span class="eyebrow">{{ $component->name }}</span>
            <h3>{{ $component->code }}</h3>
            <p>{{ $component->description }}</p>
            <dl class="component-stat-list">
                <div><dt>Default capacity</dt><dd>{{ $component->default_section_capacity }}</dd></div>
                <div><dt>Sections</dt><dd>{{ $component->sections_count }}</dd></div>
                <div><dt>Enrollments</dt><dd>{{ $component->enrollments_count }}</dd></div>
            </dl>
            <div class="component-actions">
                <a class="secondary-outline-button" href="{{ route($routePrefix.'.components.edit', ['component' => $component, 'return_to' => $componentReturnTo ?? 'components']) }}">Configure</a>
                <a class="table-action" href="{{ route($routePrefix.'.sections.index', ['component_id' => $component->id]) }}">Open in sectioning →</a>
            </div>
        </article>
    @endforeach
</section>

@if ($showCapacityInfo ?? true)
    <section class="card information-strip">
        <span class="metric-icon blue">i</span>
        <div><strong>How default capacity works</strong><p>When automated sectioning cannot find enough available seats, it creates a new section using the component's default capacity. Existing section assignments are always preserved.</p></div>
    </section>
@endif
