@extends($layout)
@section('title', 'Grades')
@section('page-title', 'Dynamic Grading Sheet')
@section('content')
<div class="page-actions">
    <div><h2>NSTP grading and class record</h2><p>@if(auth()->user()->isFacilitator())Enter scores for the students assigned to your section. Grading categories and formulas are managed by administrators.@else Edit raw scores and configure categories. Weighted percentages and the 1.00–5.00 grade are computed automatically.@endif</p></div>
</div>

<section class="card term-panel">
    <form class="term-form grade-section-picker {{ $section?->component?->code === 'ROTC' ? 'has-ms-level' : '' }}" method="GET" data-grade-section-picker>
        <label class="field-group"><span>Component</span><select name="component" data-grade-component>@foreach($components as $component)<option value="{{ $component->id }}" data-component-code="{{ $component->code }}" @selected((int) $selectedComponentId === $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
        <label class="field-group"><span>Section</span><select name="section" data-grade-section>@foreach($sections as $item)<option value="{{ $item->id }}" data-component-id="{{ $item->component_id }}" @selected($section?->id === $item->id)>{{ $item->code }} · {{ $item->name }}</option>@endforeach</select></label>
        <label class="field-group" data-ms-level-field @if($section?->component?->code !== 'ROTC') hidden @endif><span>MS Level</span><select name="ms_level" data-ms-level @disabled($section?->component?->code !== 'ROTC')><option value="">All MS levels</option>@foreach($rotcLevels as $value => $label)<option value="{{ $value }}" @selected($selectedMsLevel === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="filter-button">Open grading sheet</button>
    </form>
</section>

@if($section)
<section class="progress-metric-grid" aria-label="Gradebook progress summary">
    <article class="progress-metric"><span>Students</span><strong>{{ $gradebookMetrics['students'] }}</strong><small>Enrolled in {{ $section->code }}</small></article>
    <article class="progress-metric on-track"><span>On track</span><strong>{{ $gradebookMetrics['on_track'] }}</strong><small>Current standing meets the target</small></article>
    <article class="progress-metric attention"><span>Needs attention</span><strong>{{ $gradebookMetrics['needs_attention'] }}</strong><small>Below target or incomplete</small></article>
    <article class="progress-metric completed"><span>Completed</span><strong>{{ $gradebookMetrics['completed'] }}</strong><small>All score items recorded</small></article>
    <article class="progress-metric"><span>Encoding progress</span><strong>{{ number_format($gradebookMetrics['average_completion'], 1) }}%</strong><small>Average score-item completion</small></article>
</section>
@if(! auth()->user()->isFacilitator())
<details class="card grade-settings-card">
    <summary><span><strong>Grading setup</strong><small>Edit category percentages and the transmutation scale</small></span><span class="settings-chevron">⌄</span></summary>
    <form method="POST" action="{{ route($routePrefix.'.grades.structure', $section) }}">@csrf @method('PUT')
        <div class="grade-settings-grid">
            <div>
                <div class="settings-heading"><h3>Categories</h3><strong class="weight-total">Total: {{ number_format($categories->sum('weight'), 2) }}%</strong></div>
                <div class="category-editor-list">
                    @foreach($categories as $category)
                    <div class="category-editor-row">
                        <input class="category-color" type="color" name="categories[{{ $category->id }}][color]" value="{{ $category->color }}" aria-label="Category color">
                        <input name="categories[{{ $category->id }}][name]" value="{{ $category->name }}" required aria-label="Category name">
                        <label><input class="category-weight-input" type="number" step="0.01" min="0" max="100" name="categories[{{ $category->id }}][weight]" value="{{ $category->weight }}" required><span>%</span></label>
                        @if($categories->count() > 1)<button class="icon-delete-button" type="submit" form="delete-category-{{ $category->id }}" title="Delete category">×</button>@endif
                    </div>
                    @endforeach
                    <div class="category-editor-row new-category-row">
                        <input class="category-color" type="color" name="new_category[color]" value="#64748b" aria-label="New category color">
                        <input name="new_category[name]" placeholder="New category (optional)" aria-label="New category name">
                        <label><input class="category-weight-input" type="number" step="0.01" min="0" max="100" name="new_category[weight]" placeholder="0"><span>%</span></label>
                        <span></span>
                    </div>
                </div>
            </div>
            <div>
                <h3>1.00–5.00 transmutation</h3>
                <div class="grade-scale-grid">
                    <label class="field-group"><span>Passing percentage</span><input type="number" step="0.01" min="1" max="99.99" name="passing_percentage" value="{{ $settings->passing_percentage }}" required></label>
                    <label class="field-group"><span>Highest grade</span><input type="number" step="0.01" min="0" max="5" name="highest_grade" value="{{ $settings->highest_grade }}" required></label>
                    <label class="field-group"><span>Passing grade</span><input type="number" step="0.01" min="0" max="5" name="passing_grade" value="{{ $settings->passing_grade }}" required></label>
                    <label class="field-group"><span>Failing grade</span><input type="number" step="0.01" min="0" max="5" name="failing_grade" value="{{ $settings->failing_grade }}" required></label>
                </div>
                <p class="grading-formula">Default: below 75% = 5.00; 75% = 3.00; 100% = 1.00.</p>
            </div>
        </div>
        <div class="form-actions"><button class="primary-button compact">Save grading setup</button></div>
    </form>
    @foreach($categories as $category)
    <form id="delete-category-{{ $category->id }}" method="POST" action="{{ route($routePrefix.'.grades.categories.destroy', $category) }}" onsubmit="return confirm('Delete this empty category?')">@csrf @method('DELETE')</form>
    @endforeach
</details>

<details class="card grade-items-card">
    <summary><span><strong>Score items</strong><small>Add or edit activities, requirements, tests, and quizzes</small></span><span class="settings-chevron">⌄</span></summary>
    <div class="grade-item-manager">
        @foreach($categories as $category)
            @foreach($category->assessments as $assessment)
            <form class="grade-item-row" method="POST" action="{{ route($routePrefix.'.grades.items.update', $assessment) }}">@csrf @method('PUT')
                <select name="grading_category_id" aria-label="Category">@foreach($categories as $option)<option value="{{ $option->id }}" @selected($option->id === $category->id)>{{ $option->name }}</option>@endforeach</select>
                <input name="title" value="{{ $assessment->title }}" required aria-label="Score item title">
                <label><input type="number" step="0.01" min="0.01" name="max_score" value="{{ $assessment->max_score }}" required><span>pts</span></label>
                <button class="filter-button">Update</button>
                <button class="icon-delete-button" type="submit" form="delete-item-{{ $assessment->id }}" title="Delete score item">×</button>
            </form>
            @endforeach
        @endforeach
        <form class="grade-item-row new-item-row" method="POST" action="{{ route($routePrefix.'.grades.items.store', $section) }}">@csrf
            <select name="grading_category_id" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
            <input name="title" placeholder="New score item" required>
            <label><input type="number" step="0.01" min="0.01" name="max_score" value="100" required><span>pts</span></label>
            <button class="primary-button compact">Add item</button><span></span>
        </form>
    </div>
    @foreach($categories as $category)@foreach($category->assessments as $assessment)
    <form id="delete-item-{{ $assessment->id }}" method="POST" action="{{ route($routePrefix.'.grades.items.destroy', $assessment) }}" onsubmit="return confirm('Delete this score item and all recorded scores?')">@csrf @method('DELETE')</form>
    @endforeach @endforeach
</details>
@endif

<section class="card gradebook-card">
    <div class="gradebook-toolbar"><div><h3>{{ $section->code }} class record</h3><p>Click a score cell, type the raw score, then press Enter or click outside to save.</p></div><span id="grade-save-status" class="autosave-status">Ready</span></div>
    <div class="gradebook-scroll">
        <table class="gradebook-table">
            <thead>
                <tr>
                    <th class="student-column" rowspan="3">Student</th>
                    @foreach($categories as $category)<th class="category-band" style="--category-color:{{ $category->color }}" colspan="{{ $category->assessments->count() + 2 }}">{{ $category->name }} · {{ number_format($category->weight, 2) }}%</th>@endforeach
                    <th class="total-band" rowspan="3">Recorded<br>Weighted</th><th class="final-band" rowspan="3">Computed<br>Grade</th><th class="progress-band" rowspan="3">Progress</th>
                </tr>
                <tr>
                    @foreach($categories as $category)
                        @foreach($category->assessments as $assessment)<th class="item-heading" title="{{ $assessment->title }}">{{ $assessment->title }}</th>@endforeach
                        <th>TS</th><th>Weighted</th>
                    @endforeach
                </tr>
                <tr class="max-score-row">
                    @foreach($categories as $category)
                        @foreach($category->assessments as $assessment)<th>{{ number_format($assessment->max_score, 2) }}</th>@endforeach
                        <th>{{ number_format($category->assessments->sum('max_score'), 2) }}</th><th>{{ number_format($category->weight, 2) }}%</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($summaries as $row)
                <tr data-student-row="{{ $row['student']->id }}">
                    <td class="student-column"><strong>{{ $row['student']->studentRecordName() }}</strong><small>{{ $row['student']->studentRecordNumber() ?? 'No student number' }}</small></td>
                    @foreach($categories as $category)
                        @php($categorySummary = $row['categories']->first(fn($item) => $item['category']->id === $category->id))
                        @foreach($category->assessments as $assessment)
                            @php($submission = $assessment->submissions->firstWhere('student_id', $row['student']->id))
                            <td class="score-cell"><input class="grade-score-input" type="number" step="0.01" min="0" max="{{ $assessment->max_score }}" value="{{ $submission?->score }}" data-assessment="{{ $assessment->id }}" data-student="{{ $row['student']->id }}" aria-label="{{ $assessment->title }} score for {{ $row['student']->name }}"></td>
                        @endforeach
                        <td class="computed-cell" data-category-total="{{ $category->id }}">{{ number_format($categorySummary['earned'] ?? 0, 2) }} / {{ number_format($categorySummary['maximum'] ?? 0, 2) }}</td>
                        <td class="computed-cell category-result" data-category-result="{{ $category->id }}">{{ number_format($categorySummary['weighted_score'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="grand-total-cell" data-percentage>{{ $row['percentage'] === null ? '—' : number_format($row['percentage'], 2).'%' }}</td>
                    <td class="final-grade-cell" data-final-grade>{{ $row['grade'] === null ? '—' : number_format($row['grade'], 2) }}</td>
                    <td class="grade-progress-cell" data-progress-cell><span class="progress-status {{ $row['progress_status'] }}" data-progress-status>{{ $row['progress_label'] }}</span><small data-progress-detail>{{ number_format($row['completion_percentage'], 0) }}% encoded · {{ $row['pending_count'] }} pending</small></td>
                </tr>
                @empty
                <tr><td colspan="{{ $categories->sum(fn($category) => $category->assessments->count() + 2) + 4 }}"><div class="empty-state"><strong>No enrolled students</strong><span>Add students to this section to begin encoding scores.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($summaries->hasPages())<div class="pagination-row"><span>Showing {{ $summaries->firstItem() }}–{{ $summaries->lastItem() }} of {{ $summaries->total() }}</span>{{ $summaries->links() }}</div>@endif
</section>

<script>
(() => {
    const picker = document.querySelector('[data-grade-section-picker]');
    const componentSelect = picker?.querySelector('[data-grade-component]');
    const sectionSelect = picker?.querySelector('[data-grade-section]');
    const msLevelField = picker?.querySelector('[data-ms-level-field]');
    const msLevelSelect = picker?.querySelector('[data-ms-level]');

    const syncGradeFilters = () => {
        if (! componentSelect || ! sectionSelect || ! msLevelField || ! msLevelSelect) return;

        const componentId = componentSelect.value;
        const componentCode = componentSelect.selectedOptions[0]?.dataset.componentCode;
        const availableSections = [...sectionSelect.options].filter((option) => option.dataset.componentId === componentId);

        [...sectionSelect.options].forEach((option) => {
            const matchesComponent = option.dataset.componentId === componentId;
            option.hidden = ! matchesComponent;
            option.disabled = ! matchesComponent;
        });

        if (! availableSections.some((option) => option.selected)) {
            sectionSelect.value = availableSections[0]?.value ?? '';
        }

        const isRotc = componentCode === 'ROTC';
        msLevelField.hidden = ! isRotc;
        msLevelSelect.disabled = ! isRotc;
        if (! isRotc) msLevelSelect.value = '';
        picker.classList.toggle('has-ms-level', isRotc);
    };

    componentSelect?.addEventListener('change', syncGradeFilters);
    syncGradeFilters();

    const endpoint = @json(route($routePrefix.'.grades.scores.update', $section));
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const status = document.getElementById('grade-save-status');
    let activeRequest = 0;

    document.querySelectorAll('.grade-score-input').forEach((input) => {
        input.dataset.savedValue = input.value;
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); input.blur(); }
        });
        input.addEventListener('change', async () => {
            if (input.value === input.dataset.savedValue) return;
            const requestId = ++activeRequest;
            input.classList.add('saving'); status.textContent = 'Saving…'; status.className = 'autosave-status saving';
            try {
                const response = await fetch(endpoint, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                    body: JSON.stringify({assessment_id: input.dataset.assessment, student_id: input.dataset.student, score: input.value === '' ? null : Number(input.value)}),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to save score.');
                input.dataset.savedValue = input.value;
                const row = input.closest('tr');
                row.querySelector('[data-percentage]').textContent = data.percentage === null ? '—' : Number(data.percentage).toFixed(2) + '%';
                row.querySelector('[data-final-grade]').textContent = data.grade === null ? '—' : Number(data.grade).toFixed(2);
                const progress = row.querySelector('[data-progress-status]');
                progress.textContent = data.progress_label;
                progress.className = `progress-status ${data.progress_status}`;
                row.querySelector('[data-progress-detail]').textContent = `${Number(data.completion_percentage).toFixed(0)}% encoded · ${data.pending_count} pending`;
                Object.entries(data.categories).forEach(([id, value]) => {
                    const cell = row.querySelector(`[data-category-result="${id}"]`);
                    const totalCell = row.querySelector(`[data-category-total="${id}"]`);
                    if (cell) cell.textContent = Number(value.weighted).toFixed(2);
                    if (totalCell) totalCell.textContent = Number(value.earned).toFixed(2) + ' / ' + Number(value.maximum).toFixed(2);
                });
                input.classList.add('saved');
                setTimeout(() => input.classList.remove('saved'), 900);
                if (requestId === activeRequest) { status.textContent = 'All changes saved'; status.className = 'autosave-status saved'; }
            } catch (error) {
                input.value = input.dataset.savedValue;
                status.textContent = error.message; status.className = 'autosave-status error';
            } finally { input.classList.remove('saving'); }
        });
    });

    const weights = document.querySelectorAll('.category-weight-input');
    const total = document.querySelector('.weight-total');
    weights.forEach(input => input.addEventListener('input', () => {
        const sum = [...weights].reduce((value, item) => value + Number(item.value || 0), 0);
        total.textContent = `Total: ${sum.toFixed(2)}%`;
        total.classList.toggle('invalid', Math.abs(sum - 100) > .001);
    }));
})();
</script>
@else
<section class="card empty-state"><strong>No manageable section available</strong><span>Create or assign a section before setting up a grading sheet.</span></section>
@endif
@endsection
