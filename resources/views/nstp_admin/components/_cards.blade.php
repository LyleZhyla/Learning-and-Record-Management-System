<section class="management-component-grid">
    @foreach ($componentCards as $nstpComponent)
        <article class="management-component-card">
            <div class="component-card-top">
                <x-component-logo :component-code="$nstpComponent->code" class="component-code-mark" />
                <span class="status-badge {{ $nstpComponent->is_active ? 'active' : 'inactive' }}"><i></i>{{ $nstpComponent->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <span class="eyebrow">{{ $nstpComponent->name }}</span>
            <h3>{{ $nstpComponent->code }}</h3>
            <p>{{ $nstpComponent->description }}</p>
            <dl class="component-stat-list">
                <div><dt>Default capacity</dt><dd>{{ $nstpComponent->default_section_capacity }}</dd></div>
                <div><dt>Sections</dt><dd>{{ $nstpComponent->sections_count }}</dd></div>
                <div><dt>Enrollments</dt><dd>{{ $nstpComponent->enrollments_count }}</dd></div>
            </dl>
            <div class="component-actions">
                <a class="secondary-outline-button" href="{{ route($routePrefix.'.components.edit', $nstpComponent) }}">Configure</a>
                <a class="table-action" href="{{ route($routePrefix.'.sections.index', ['component_id' => $nstpComponent->id]) }}">Open in sectioning →</a>
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
