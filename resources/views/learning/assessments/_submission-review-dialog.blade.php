@php
    $preview = $submissionPreviews[$submission->id] ?? ['exists' => false, 'too_large' => false, 'preview_type' => null, 'size_label' => null];
    $hasAttachment = filled($submission->file_path);
    $manualOnly = $hasAttachment && ($preview['too_large'] || !$preview['exists']);
    $needsReview = ($submission->ai_breakdown['needs_manual_review'] ?? false) || (float) $submission->ai_confidence < 70;
@endphp
<dialog class="submission-review-dialog" id="submission-review-{{ $submission->id }}" data-submission-dialog @if(session('open_submission_modal') === $submission->id) data-auto-open @endif>
    <div class="submission-dialog-shell">
        <header class="submission-dialog-header">
            <div><span class="eyebrow">Student submission</span><h3>{{ $enrollment->student->name }}</h3><p>{{ $assessment->title }} · Submitted {{ $submission->submitted_at?->format('M d, Y g:i A') }}</p></div>
            <button type="button" data-submission-close aria-label="Close submission review">×</button>
        </header>

        <div class="submission-dialog-content">
            <section class="submission-preview-pane">
                <div class="submission-pane-heading"><div><span class="eyebrow">Step 1</span><h4>Review student work</h4></div>@if($hasAttachment && $preview['exists'])<span class="submission-size-badge">{{ $preview['size_label'] }}</span>@endif</div>

                @if(filled($submission->answer_text))
                    <article class="submission-text-preview"><strong>Written response</strong><p>{{ $submission->answer_text }}</p></article>
                @endif

                @if($hasAttachment)
                    <article class="submission-file-preview">
                        <div class="submission-file-heading"><div><strong>{{ $submission->original_filename ?: 'Attached file' }}</strong><small>{{ $preview['size_label'] ?: 'File size unavailable' }}</small></div></div>
                        @if(!$preview['exists'])
                            <div class="submission-manual-notice"><span>!</span><div><strong>Attachment unavailable</strong><p>The stored file could not be found. Continue with manual checking using the written response, if available.</p></div></div>
                        @elseif($preview['too_large'])
                            <div class="submission-manual-notice"><span>↓</span><div><strong>Large file—manual checking required</strong><p>This attachment is over 5 MB. For a stable and secure review, download it instead of loading it inside the pop-up. AI scoring is disabled for this file.</p></div></div>
                            <a class="primary-button compact submission-download-button" href="{{ route($routePrefix.'.assessments.submissions.download', [$assessment, $submission]) }}">Download student file</a>
                        @elseif($preview['preview_type'] === 'image')
                            <img class="submission-image-preview" src="{{ route($routePrefix.'.assessments.submissions.file', [$assessment, $submission]) }}" alt="Preview of {{ $submission->original_filename }}">
                            <a class="submission-secondary-download" href="{{ route($routePrefix.'.assessments.submissions.download', [$assessment, $submission]) }}">Download original</a>
                        @elseif(in_array($preview['preview_type'], ['pdf', 'text'], true))
                            <iframe class="submission-document-preview" src="{{ route($routePrefix.'.assessments.submissions.file', [$assessment, $submission]) }}" title="Preview of {{ $submission->original_filename }}"></iframe>
                            <a class="submission-secondary-download" href="{{ route($routePrefix.'.assessments.submissions.download', [$assessment, $submission]) }}">Download original</a>
                        @else
                            <div class="submission-file-fallback"><strong>Preview is not supported by this browser</strong><p>Download this Office file to inspect its formatting and complete your review. AI can still provide an advisory score because the file is within the safe size limit.</p></div>
                            <a class="primary-button compact submission-download-button" href="{{ route($routePrefix.'.assessments.submissions.download', [$assessment, $submission]) }}">Open downloaded file</a>
                        @endif
                    </article>
                @endif
            </section>

            <aside class="submission-scoring-pane">
                <div class="submission-pane-heading"><div><span class="eyebrow">Step 2</span><h4>Review and score</h4></div></div>

                @if(!$manualOnly)
                    <section class="modal-ai-assistant">
                        <div class="modal-ai-heading"><span>✦</span><div><strong>AI scoring assistant</strong><small>Advisory only—your review remains final.</small></div></div>
                        <form method="POST" action="{{ route($routePrefix.'.assessments.ai-score.generate', [$assessment, $submission]) }}">
                            @csrf
                            <button class="ai-score-button" type="submit" @disabled(blank($assessment->rubric) || blank(config('services.openai.api_key')))>{{ $submission->ai_generated_at ? 'Regenerate AI suggestion' : 'Ask AI to suggest a score' }}</button>
                        </form>

                        @if($submission->ai_generated_at)
                            <div class="modal-ai-result">
                                <div class="modal-ai-score"><div><small>AI suggestion</small><strong>{{ number_format((float) $submission->ai_suggested_score, 2) }} / {{ number_format((float) $assessment->max_score, 2) }}</strong></div><span class="status-badge {{ $needsReview ? 'inactive' : 'active' }}"><i></i>{{ $needsReview ? 'Check manually' : number_format((float) $submission->ai_confidence, 0).'% confidence' }}</span></div>
                                <div class="ai-criteria-list">@foreach(($submission->ai_breakdown['criteria'] ?? []) as $criterion)<article><div><strong>{{ $criterion['criterion'] }}</strong><b>{{ number_format((float) $criterion['points_awarded'], 2) }} / {{ number_format((float) $criterion['points_possible'], 2) }}</b></div><p>{{ $criterion['evidence'] }}</p></article>@endforeach</div>
                                <form class="ai-approval-form modal-approval-form" method="POST" action="{{ route($routePrefix.'.assessments.ai-score.approve', [$assessment, $submission]) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="suggestion_generated_at" value="{{ $submission->ai_generated_at->toIso8601String() }}">
                                    <label><span>Reviewed score</span><input type="number" step="0.01" min="0" max="{{ $assessment->max_score }}" name="score" value="{{ $submission->ai_suggested_score }}" required></label>
                                    <label><span>Reviewed feedback</span><textarea name="feedback" rows="4">{{ $submission->ai_feedback }}</textarea></label>
                                    <small>Edit anything that does not match your own review before approval.</small>
                                    <button class="primary-button compact" type="submit">Approve as official score</button>
                                </form>
                            </div>
                        @elseif(blank($assessment->rubric))
                            <small class="ai-score-help">Save a rubric first to enable AI assistance.</small>
                        @elseif(blank(config('services.openai.api_key')))
                            <small class="ai-score-help">Configure OPENAI_API_KEY to enable AI assistance.</small>
                        @endif
                    </section>
                @else
                    <div class="modal-manual-only"><strong>Manual scoring mode</strong><p>AI assistance is unavailable because this attachment cannot be safely previewed and processed.</p></div>
                @endif

                <form class="modal-manual-score-form" method="POST" action="{{ route($routePrefix.'.assessments.score', [$assessment, $enrollment->student]) }}">
                    @csrf @method('PUT')
                    <div><span class="eyebrow">Manual score</span><p>Use this form when you prefer to score without accepting the AI suggestion.</p></div>
                    <label class="field-group"><span>Score out of {{ number_format((float) $assessment->max_score, 2) }}</span><input type="number" step="0.01" min="0" max="{{ $assessment->max_score }}" name="score" value="{{ $submission->score }}" required></label>
                    <label class="field-group"><span>Feedback</span><textarea name="feedback" rows="4" placeholder="Feedback for the student">{{ $submission->feedback }}</textarea></label>
                    <button class="secondary-outline-button" type="submit">Save manual score</button>
                </form>
            </aside>
        </div>
    </div>
</dialog>
