@extends($layout)
@section('title', 'Grades')
@section('page-title', 'Dynamic Grading Sheet')
@section('content')
<div class="page-actions">
    <div><h2>Excel-style class record</h2><p>Edit raw scores directly. Category totals, weighted percentages, and the 1.00–5.00 grade are computed automatically.</p></div>
</div>

<section class="card term-panel">
    <form class="term-form grade-section-picker" method="GET">
        <label class="field-group"><span>Section</span><select name="section">@foreach($sections as $item)<option value="{{ $item->id }}" @selected($section?->id === $item->id)>{{ $item->code }} · {{ $item->component->code }}</option>@endforeach</select></label>
        <button class="filter-button">Open grading sheet</button>
    </form>
</section>

@if($section)
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

<section class="card gradebook-card">
    <div class="gradebook-toolbar"><div><h3>{{ $section->code }} class record</h3><p>Click a score cell, type the raw score, then press Enter or click outside to save.</p></div><span id="grade-save-status" class="autosave-status">Ready</span></div>
    <div class="gradebook-scroll">
        <table class="gradebook-table">
            <thead>
                <tr>
                    <th class="student-column" rowspan="3">Student</th>
                    @foreach($categories as $category)<th class="category-band" style="--category-color:{{ $category->color }}" colspan="{{ $category->assessments->count() + 2 }}">{{ $category->name }} · {{ number_format($category->weight, 2) }}%</th>@endforeach
                    <th class="total-band" rowspan="3">Grand<br>Total</th><th class="final-band" rowspan="3">Final<br>Grade</th>
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
                    <td class="student-column"><strong>{{ $row['student']->name }}</strong><small>{{ $row['student']->email }}</small></td>
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
                </tr>
                @empty
                <tr><td colspan="{{ $categories->sum(fn($category) => $category->assessments->count() + 2) + 3 }}"><div class="empty-state"><strong>No enrolled students</strong><span>Add students to this section to begin encoding scores.</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<script>
(() => {
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
