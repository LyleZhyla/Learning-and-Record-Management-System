@php
    $rubricCriteria = old('rubric_criteria', $rubricCriteria ?? []);
    if (count($rubricCriteria) === 0) {
        $rubricCriteria = [['title' => '', 'description' => '', 'percentage' => '', 'score' => '']];
    }
    $canSuggestRubric = auth()->user()->isFacilitator()
        && Route::has($routePrefix.'.assessments.rubric.suggest');
@endphp
<div class="rubric-builder" data-rubric-builder
     data-suggestion-url="{{ $canSuggestRubric ? route($routePrefix.'.assessments.rubric.suggest') : '' }}"
     @isset($rubricContext) data-title="{{ $rubricContext['title'] }}" data-type="{{ $rubricContext['type'] }}" data-instructions="{{ $rubricContext['instructions'] }}" data-max-score="{{ $rubricContext['max_score'] }}" @endisset>
    <div class="rubric-builder-heading">
        <div>
            <strong>Rubric criteria</strong>
            <small>Add a title and description, then assign its percentage and maximum score.</small>
        </div>
        @if($canSuggestRubric)
            <button type="button" class="ai-rubric-suggest-button" data-rubric-suggest @disabled(blank(config('services.openai.api_key')))>
                <span>✦</span> Suggest with AI
            </button>
        @endif
    </div>

    @error('rubric_criteria')<div class="rubric-error" role="alert">{{ $message }}</div>@enderror
    <div class="rubric-suggestion-status" data-rubric-status aria-live="polite"></div>
    <div class="rubric-criteria" data-rubric-criteria>
        @foreach($rubricCriteria as $index => $criterion)
            <article class="rubric-criterion" data-rubric-row>
                <div class="rubric-criterion-number"><span data-rubric-number>{{ $index + 1 }}</span></div>
                <div class="rubric-criterion-fields">
                    <label class="field-group"><span>Criterion title</span><input name="rubric_criteria[{{ $index }}][title]" value="{{ $criterion['title'] ?? '' }}" maxlength="120" placeholder="e.g. Understanding of NSTP concepts" {{ ($rubricRequired ?? false) ? 'required' : '' }}></label>
                    <label class="field-group"><span>Description</span><textarea name="rubric_criteria[{{ $index }}][description]" rows="2" maxlength="1000" placeholder="Describe what a complete, accurate response should demonstrate." {{ ($rubricRequired ?? false) ? 'required' : '' }}>{{ $criterion['description'] ?? '' }}</textarea></label>
                    <label class="field-group"><span>Percentage</span><div class="rubric-number-input"><input type="number" step="0.01" min="0.01" max="100" name="rubric_criteria[{{ $index }}][percentage]" value="{{ $criterion['percentage'] ?? '' }}" data-rubric-percentage {{ ($rubricRequired ?? false) ? 'required' : '' }}><b>%</b></div></label>
                    <label class="field-group"><span>Maximum score</span><div class="rubric-number-input"><input type="number" step="0.01" min="0.01" name="rubric_criteria[{{ $index }}][score]" value="{{ $criterion['score'] ?? '' }}" data-rubric-score {{ ($rubricRequired ?? false) ? 'required' : '' }}><b>pts</b></div></label>
                </div>
                <button type="button" class="rubric-remove-button" data-rubric-remove aria-label="Remove criterion">×</button>
            </article>
        @endforeach
    </div>

    <template data-rubric-template>
        <article class="rubric-criterion" data-rubric-row>
            <div class="rubric-criterion-number"><span data-rubric-number></span></div>
            <div class="rubric-criterion-fields">
                <label class="field-group"><span>Criterion title</span><input data-name="title" maxlength="120" placeholder="e.g. Understanding of NSTP concepts"></label>
                <label class="field-group"><span>Description</span><textarea data-name="description" rows="2" maxlength="1000" placeholder="Describe what a complete, accurate response should demonstrate."></textarea></label>
                <label class="field-group"><span>Percentage</span><div class="rubric-number-input"><input type="number" step="0.01" min="0.01" max="100" data-name="percentage" data-rubric-percentage><b>%</b></div></label>
                <label class="field-group"><span>Maximum score</span><div class="rubric-number-input"><input type="number" step="0.01" min="0.01" data-name="score" data-rubric-score><b>pts</b></div></label>
            </div>
            <button type="button" class="rubric-remove-button" data-rubric-remove aria-label="Remove criterion">×</button>
        </article>
    </template>

    <div class="rubric-builder-footer">
        <button type="button" class="secondary-outline-button compact" data-rubric-add>+ Add criterion</button>
        <div class="rubric-totals" data-rubric-totals>
            <span>Percentage <strong data-rubric-percentage-total>0%</strong></span>
            <span>Score <strong><b data-rubric-score-total>0</b> / <b data-rubric-max-label>{{ $rubricMaxScore ?? 0 }}</b></strong></span>
        </div>
    </div>
    <small class="form-help rubric-help">Percentages must total 100%, and criterion scores must total the assessment maximum score. AI suggestions are drafts—review and edit them before saving.</small>
</div>
