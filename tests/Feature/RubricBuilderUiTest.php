<?php

namespace Tests\Feature;

use Tests\TestCase;

class RubricBuilderUiTest extends TestCase
{
    public function test_facilitator_rubric_builder_exposes_clear_structure_and_live_feedback(): void
    {
        $markup = file_get_contents(resource_path('views/learning/assessments/_rubric-builder.blade.php'));

        $this->assertStringContainsString('rubric-criterion-header', $markup);
        $this->assertStringContainsString('rubric-description-field', $markup);
        $this->assertStringContainsString('data-rubric-count', $markup);
        $this->assertStringContainsString('data-rubric-state', $markup);
        $this->assertStringContainsString('data-rubric-progress', $markup);
        $this->assertStringContainsString('data-max-criteria="10"', $markup);
        $this->assertStringContainsString('Allocation summary', $markup);
        $this->assertStringContainsString('data-rubric-library-select', $markup);
        $this->assertStringContainsString('Reuse a saved rubric', $markup);
    }

    public function test_rubric_builder_styles_cover_readability_responsiveness_and_dark_mode(): void
    {
        $styles = file_get_contents(public_path('css/ai-assessment-scoring.css'));

        $this->assertStringContainsString('/* Facilitator rubric criteria editor */', $styles);
        $this->assertStringContainsString('.rubric-description-field{grid-column:1/-1}', $styles);
        $this->assertStringContainsString('@media(max-width:650px){.rubric-builder', $styles);
        $this->assertStringContainsString('html[data-theme=dark] .rubric-summary', $styles);
        $this->assertStringContainsString('.rubric-library-controls', $styles);
    }

    public function test_rubric_builder_script_enforces_the_limit_and_updates_status(): void
    {
        $script = file_get_contents(public_path('js/rubric-builder.js'));

        $this->assertStringContainsString('criteria.children.length >= maxCriteria', $script);
        $this->assertStringContainsString("complete ? 'Ready to save'", $script);
        $this->assertStringContainsString('field.required = rubricRequired', $script);
        $this->assertStringContainsString("row.scrollIntoView({ behavior: 'smooth', block: 'nearest' })", $script);
        $this->assertStringContainsString('result.criteria.forEach((criterion) => addRow(criterion))', $script);
        $this->assertStringContainsString('function applyLibraryTemplate(template)', $script);
        $this->assertStringContainsString('upsertLibraryTemplate(result.template)', $script);
    }
}
