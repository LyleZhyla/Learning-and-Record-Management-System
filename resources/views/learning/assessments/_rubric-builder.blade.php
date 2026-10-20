@php
    $rubricCriteria = old('rubric_criteria', $rubricCriteria ?? []);
    $rubricTemplates = $rubricTemplates ?? [];
    if (count($rubricCriteria) === 0) {
        $rubricCriteria = [['title' => '', 'description' => '', 'percentage' => '', 'score' => '']];
    }
    $canSuggestRubric = auth()->user()->isFacilitator()
        && Route::has($routePrefix.'.assessments.rubric.suggest');
@endphp
<div class="rubric-builder" data-rubric-builder
     data-max-criteria="10"
     data-rubric-required="{{ ($rubricRequired ?? false) ? 'true' : 'false' }}"
     data-suggestion-url="{{ $canSuggestRubric ? route($routePrefix.'.assessments.rubric.suggest') : '' }}"
     @isset($rubricContext) data-title="{{ $rubricContext['title'] }}" data-type="{{ $rubricContext['type'] }}" data-instructions="{{ $rubricContext['instructions'] }}" data-max-score="{{ $rubricContext['max_score'] }}" @endisset>
    <div class="rubric-builder-heading">
        <div class="rubric-heading-copy">
            <span class="rubric-heading-icon" aria-hidden="true">▦</span>
            <div>
                <strong>Rubric criteria</strong>
                <small>Define what you will evaluate, then distribute the assessment score across the criteria.</small>
            </div>
        </div>
        <div class="rubric-heading-actions">
            <span class="rubric-requirement {{ ($rubricRequired ?? false) ? 'required' : 'optional' }}">{{ ($rubricRequired ?? false) ? 'Required' : 'Optional' }}</span>
            <span class="rubric-limit"><b data-rubric-count>{{ count($rubricCriteria) }}</b> / 10 criteria</span>
            @if($canSuggestRubric)
                <button type="button" class="ai-rubric-suggest-button" data-rubric-suggest @disabled(blank(config('services.openai.api_key')))>
                    <span>✦</span> Suggest with AI
                </button>
            @endif
        </div>
    </div>

    @if(auth()->user()->isFacilitator())
        <section class="rubric-library" data-rubric-library>
            <div class="rubric-library-copy">
                <span aria-hidden="true">↻</span>
                <div><strong>Reuse a saved rubric</strong><small>AI-generated and previously saved rubrics are kept in your personal library.</small></div>
            </div>
            <div class="rubric-library-controls">
                <select data-rubric-library-select aria-label="Select a saved rubric" @disabled(count($rubricTemplates) === 0)>
                    <option value="">{{ count($rubricTemplates) === 0 ? 'No saved rubrics yet' : 'Choose a saved rubric' }}</option>
                    @foreach($rubricTemplates as $template)
                        <option value="{{ $template['id'] }}">{{ str($template['assessment_type'])->headline() }} · {{ $template['name'] }}</option>
                    @endforeach
                </select>
                <button type="button" class="secondary-outline-button compact" data-rubric-library-apply disabled>Use rubric</button>
            </div>
        </section>
        <script type="application/json" data-rubric-library-data>@json($rubricTemplates)</script>
    @endif

    @error('rubric_criteria')<div class="rubric-error" role="alert">{{ $message }}</div>@enderror
    <div class="rubric-suggestion-status" data-rubric-status aria-live="polite"></div>
    <div class="rubric-criteria" data-rubric-criteria>
        @foreach($rubricCriteria as $index => $criterion)
            <article class="rubric-criterion" data-rubric-row>
                <header class="rubric-criterion-header">
                    <div class="rubric-criterion-identity">
                        <div class="rubric-criterion-number"><span data-rubric-number>{{ $index + 1 }}</span></div>
                        <div><strong>Scoring criterion</strong><small>Describe one measurable part of the student’s work.</small></div>
                    </div>
                    <button type="button" class="rubric-remove-button" data-rubric-remove aria-label="Remove criterion {{ $index + 1 }}"><span aria-hidden="true">×</span> Remove</button>
                </header>
                <div class="rubric-criterion-fields">
                    <label class="field-group rubric-title-field"><span>Criterion title</span><input name="rubric_criteria[{{ $index }}][title]" value="{{ $criterion['title'] ?? '' }}" maxlength="120" placeholder="e.g. Understanding of NSTP concepts" {{ ($rubricRequired ?? false) ? 'required' : '' }}></label>
                    <label class="field-group rubric-percentage-field"><span>Percentage weight</span><div class="rubric-number-input"><input type="number" step="0.01" min="0.01" max="100" name="rubric_criteria[{{ $index }}][percentage]" value="{{ $criterion['percentage'] ?? '' }}" data-rubric-percentage {{ ($rubricRequired ?? false) ? 'required' : '' }}><b>%</b></div></label>
                    <label class="field-group rubric-score-field"><span>Maximum points</span><div class="rubric-number-input"><input type="number" step="0.01" min="0.01" name="rubric_criteria[{{ $index }}][score]" value="{{ $criterion['score'] ?? '' }}" data-rubric-score {{ ($rubricRequired ?? false) ? 'required' : '' }}><b>pts</b></div></label>
                    <label class="field-group rubric-description-field"><span>What should the student demonstrate?</span><textarea name="rubric_criteria[{{ $index }}][description]" rows="3" maxlength="1000" placeholder="Describe the evidence expected for full or partial credit." {{ ($rubricRequired ?? false) ? 'required' : '' }}>{{ $criterion['description'] ?? '' }}</textarea><small>Use observable and specific grading evidence.</small></label>
                </div>
            </article>
        @endforeach
    </div>

    <template data-rubric-template>
        <article class="rubric-criterion" data-rubric-row>
            <header class="rubric-criterion-header">
                <div class="rubric-criterion-identity">
                    <div class="rubric-criterion-number"><span data-rubric-number></span></div>
                    <div><strong>Scoring criterion</strong><small>Describe one measurable part of the student’s work.</small></div>
                </div>
                <button type="button" class="rubric-remove-button" data-rubric-remove aria-label="Remove criterion"><span aria-hidden="true">×</span> Remove</button>
            </header>
            <div class="rubric-criterion-fields">
                <label class="field-group rubric-title-field"><span>Criterion title</span><input data-name="title" maxlength="120" placeholder="e.g. Understanding of NSTP concepts"></label>
                <label class="field-group rubric-percentage-field"><span>Percentage weight</span><div class="rubric-number-input"><input type="number" step="0.01" min="0.01" max="100" data-name="percentage" data-rubric-percentage><b>%</b></div></label>
                <label class="field-group rubric-score-field"><span>Maximum points</span><div class="rubric-number-input"><input type="number" step="0.01" min="0.01" data-name="score" data-rubric-score><b>pts</b></div></label>
                <label class="field-group rubric-description-field"><span>What should the student demonstrate?</span><textarea data-name="description" rows="3" maxlength="1000" placeholder="Describe the evidence expected for full or partial credit."></textarea><small>Use observable and specific grading evidence.</small></label>
            </div>
        </article>
    </template>

    <div class="rubric-builder-footer">
        <button type="button" class="secondary-outline-button compact" data-rubric-add>+ Add criterion</button>
        <div class="rubric-summary" data-rubric-totals aria-live="polite">
            <div class="rubric-summary-heading"><strong>Allocation summary</strong><span data-rubric-state>Adjust totals before saving</span></div>
            <div class="rubric-progress" role="progressbar" aria-label="Rubric percentage allocated" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-rubric-progress><i></i></div>
            <div class="rubric-totals">
                <span><small>Total weight</small><strong data-rubric-percentage-total>0%</strong></span>
                <span><small>Allocated points</small><strong><b data-rubric-score-total>0</b> / <b data-rubric-max-label>{{ $rubricMaxScore ?? 0 }}</b></strong></span>
            </div>
        </div>
    </div>
    <small class="form-help rubric-help"><span aria-hidden="true">ⓘ</span> Weight must total 100%, while allocated points must equal the assessment maximum. Editing either value automatically updates the other.</small>
</div>
