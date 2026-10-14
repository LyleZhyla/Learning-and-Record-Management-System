@extends('layouts.student')

@section('title', 'Materials')
@section('page-title', 'Learning Materials')

@section('page-styles')
    <link rel="stylesheet" href="{{ asset('css/student-materials.css') }}?v={{ filemtime(public_path('css/student-materials.css')) }}">
@endsection

@section('content')
    <section class="materials-hero" aria-labelledby="materials-heading">
        <div class="materials-hero-copy">
            <span class="materials-eyebrow">Student resource library</span>
            <h2 id="materials-heading">Everything you need for your NSTP class</h2>
            <p>Open files and references shared for your component and section, all in one organized space.</p>
            @permission('ai.use')
                <a class="materials-ai-button" href="{{ route('student.recommendations.index') }}">
                    <span aria-hidden="true">✦</span>
                    Get AI recommendations
                    <span aria-hidden="true">→</span>
                </a>
            @endpermission
        </div>

        <div class="materials-class-summary" aria-label="Current class details">
            <div class="materials-summary-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14H6.5A2.5 2.5 0 0 0 4 20.5v-14Z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20M8 8h8M8 12h6"/></svg>
            </div>
            <div class="materials-summary-copy">
                <span>Current learning space</span>
                @if($enrollment)
                    <strong>{{ $enrollment->component->code }} · {{ $enrollment->section->code }}</strong>
                    <small>{{ $enrollment->section->name }}</small>
                @else
                    <strong>No active enrollment</strong>
                    <small>Your class details will appear after enrollment.</small>
                @endif
            </div>
            <div class="materials-total">
                <strong>{{ $materials->total() }}</strong>
                <span>{{ str('material')->plural($materials->total()) }}</span>
            </div>
        </div>
    </section>

    @if($materials->total() > 0)
        <div class="materials-toolbar">
            <div>
                <span class="materials-toolbar-icon" aria-hidden="true">▤</span>
                <div>
                    <strong>Available resources</strong>
                    <span>Newest materials are shown first.</span>
                </div>
            </div>
            @include('pagination.page-size', ['paginator' => $materials])
        </div>
    @endif

    <div class="materials-grid">
        @forelse($materials as $material)
            @php
                $extension = strtolower(pathinfo($material->original_filename ?? '', PATHINFO_EXTENSION));
                $typeLabel = $material->file_path ? (strtoupper($extension) ?: 'FILE') : 'LINK';
                $typeClass = match ($extension) {
                    'pdf' => 'pdf',
                    'doc', 'docx' => 'document',
                    'ppt', 'pptx' => 'presentation',
                    'xls', 'xlsx', 'csv' => 'spreadsheet',
                    'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
                    default => $material->file_path ? 'file' : 'link',
                };
            @endphp

            <article class="material-card">
                <div class="material-card-topline" aria-hidden="true"></div>
                <div class="material-card-header">
                    <div class="material-type-icon {{ $typeClass }}" aria-hidden="true">
                        @if($material->file_path)
                            <svg viewBox="0 0 24 24" fill="none"><path d="M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none"><path d="m9 15 6-6M7.5 17.5l-1 1a3.54 3.54 0 0 1-5-5l3-3a3.54 3.54 0 0 1 5 0M16.5 6.5l1-1a3.54 3.54 0 0 1 5 5l-3 3a3.54 3.54 0 0 1-5 0"/></svg>
                        @endif
                    </div>
                    <div class="material-badges">
                        <span class="material-type-badge {{ $typeClass }}">{{ $typeLabel }}</span>
                        <span class="material-scope-badge">{{ $material->section?->code ?? 'All sections' }}</span>
                    </div>
                </div>

                <div class="material-card-body">
                    <span class="material-component">{{ $material->component->code }}</span>
                    <h3>{{ $material->title }}</h3>
                    <p>{{ $material->description ?: 'Open this resource to view the learning material shared with your class.' }}</p>
                </div>

                <div class="material-source">
                    <span aria-hidden="true">{{ $material->file_path ? '↓' : '↗' }}</span>
                    <div>
                        <small>{{ $material->file_path ? 'Attached file' : 'External resource' }}</small>
                        <strong>{{ $material->file_path ? ($material->original_filename ?: 'Downloadable file') : 'Opens in a new tab' }}</strong>
                    </div>
                </div>

                <div class="material-card-footer">
                    <div class="material-published">
                        <span class="material-publisher-avatar" aria-hidden="true">{{ str($material->creator?->name ?? 'NSTP')->substr(0, 1)->upper() }}</span>
                        <span>
                            <small>Published {{ $material->published_at?->format('M d, Y') ?? 'recently' }}</small>
                            <strong>{{ $material->creator?->name ?? 'NSTP Office' }}</strong>
                        </span>
                    </div>

                    @if($material->file_path)
                        <a class="material-action" href="{{ route('student.materials.download', $material) }}">
                            Download <span aria-hidden="true">↓</span>
                        </a>
                    @elseif($material->external_url)
                        <a class="material-action" target="_blank" rel="noopener" href="{{ $material->external_url }}">
                            Open link <span aria-hidden="true">↗</span>
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <section class="materials-empty" aria-live="polite">
                <div class="materials-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14H6.5A2.5 2.5 0 0 0 4 20.5v-14Z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20"/></svg>
                </div>
                @if($enrollment)
                    <strong>No published materials yet</strong>
                    <p>Your facilitator has not published resources for {{ $enrollment->component->code }} · {{ $enrollment->section->code }} yet. Check back soon.</p>
                @else
                    <strong>Complete your enrollment first</strong>
                    <p>Learning materials will appear here once you have an active NSTP component and section.</p>
                    <a href="{{ route('student.component.edit') }}">Go to NSTP Selection →</a>
                @endif
            </section>
        @endforelse
    </div>

    @if($materials->total() > 0)
        <div class="materials-pagination">
            <span>Showing {{ $materials->firstItem() }}–{{ $materials->lastItem() }} of {{ $materials->total() }} materials</span>
            {{ $materials->links('pagination.compact', ['showPageSize' => false]) }}
        </div>
    @endif
@endsection
