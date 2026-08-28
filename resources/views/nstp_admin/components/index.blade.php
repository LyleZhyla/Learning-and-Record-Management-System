@extends('layouts.nstp-admin')

@section('title', 'NSTP Components')
@section('page-title', 'NSTP Components')

@section('content')
    <section class="page-actions">
        <div><span class="eyebrow">Program configuration</span><h2>Configure CWTS, LTS, and ROTC</h2><p>Manage component availability, descriptions, and the default capacity used by automated sectioning.</p></div>
        <a class="primary-button compact" href="{{ route('nstp_admin.sectioning.index') }}">Open automated sectioning</a>
    </section>

    <section class="management-component-grid">
        @foreach ($components as $component)
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
                    <a class="secondary-outline-button" href="{{ route('nstp_admin.components.edit', $component) }}">Configure</a>
                    <a class="table-action" href="{{ route('nstp_admin.sections.index', ['component' => $component->id]) }}">View sections →</a>
                </div>
            </article>
        @endforeach
    </section>

    <section class="card information-strip">
        <span class="metric-icon blue">i</span>
        <div><strong>How default capacity works</strong><p>When automated sectioning cannot find enough available seats, it creates a new section using the component's default capacity. Existing section assignments are always preserved.</p></div>
    </section>
@endsection
