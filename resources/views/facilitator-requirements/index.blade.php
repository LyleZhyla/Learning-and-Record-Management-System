@extends($layout)

@section('title', 'Facilitator Requirements')
@section('page-title', 'Requirements & Compliance')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/facilitator-requirements.css') }}?v={{ filemtime(public_path('css/facilitator-requirements.css')) }}">
@endpush

@section('content')
@php
    $isFacilitator = auth()->user()->isFacilitator();
@endphp

<div class="requirements-workspace">
    @if($isFacilitator)
        <section class="requirements-hero facilitator-hero">
            <div class="requirements-hero-copy">
                <span class="requirements-kicker">Facilitator document center</span>
                <h2>Keep your requirements complete and verified.</h2>
                <p>Upload the required documents, follow reviewer feedback, and monitor your compliance status in one place.</p>
                <div class="hero-legend" aria-label="Compliance status legend">
                    <span><i class="legend-dot verified"></i>{{ $verifiedCount }} verified</span>
                    <span><i class="legend-dot pending"></i>{{ $requiredCount - $verifiedCount }} remaining</span>
                </div>
            </div>
            <div class="compliance-ring" style="--progress: {{ $compliancePercentage }}" data-compliance-percentage="{{ $compliancePercentage }}">
                <div class="compliance-ring-inner">
                    <strong>{{ $compliancePercentage }}%</strong>
                    <span>compliant</span>
                </div>
            </div>
        </section>

        <section class="requirement-stat-grid" aria-label="Your compliance summary">
            <article class="requirement-stat-card blue">
                <span class="stat-icon" aria-hidden="true">📄</span>
                <div><span>Required documents</span><strong>{{ $requiredCount }}</strong><small>Assigned to your component</small></div>
            </article>
            <article class="requirement-stat-card green">
                <span class="stat-icon" aria-hidden="true">✓</span>
                <div><span>Verified</span><strong>{{ $verifiedCount }}</strong><small>Approved by a reviewer</small></div>
            </article>
            <article class="requirement-stat-card amber">
                <span class="stat-icon" aria-hidden="true">↗</span>
                <div><span>Next step</span><strong>{{ $compliancePercentage === 100 ? 'Done' : $requiredCount - $verifiedCount }}</strong><small>{{ $compliancePercentage === 100 ? 'All requirements complete' : 'Document'.($requiredCount - $verifiedCount === 1 ? '' : 's').' to complete' }}</small></div>
            </article>
        </section>

        <section class="workspace-section">
            <div class="requirements-section-heading">
                <div>
                    <span class="requirements-kicker">Your checklist</span>
                    <h3>Assigned requirements</h3>
                    <p>Required items are counted only after they have been verified.</p>
                </div>
                <span class="section-count">{{ $requirements->count() }} {{ str('requirement')->plural($requirements->count()) }}</span>
            </div>

            <div class="facilitator-requirement-grid">
                @forelse($requirements as $requirement)
                    @php
                        $submission = $submissions->get($requirement->id);
                        $status = $submission?->status ?? 'missing';
                        $statusLabel = $submission?->statusLabel() ?? 'Not submitted';
                        $canResubmit = !$submission || in_array($submission->status, ['needs_correction', 'rejected'], true);
                    @endphp
                    <article class="requirement-card status-{{ $status }}">
                        <header class="requirement-card-header">
                            <div class="requirement-title-wrap">
                                <span class="requirement-scope">{{ $requirement->component?->code ?? 'ALL NSTP' }}</span>
                                <h4>{{ $requirement->title }}</h4>
                            </div>
                            <span class="requirement-status status-{{ $status }}">
                                <i aria-hidden="true"></i>{{ $statusLabel }}
                            </span>
                        </header>

                        <div class="requirement-card-body">
                            @if($requirement->description)
                                <p class="requirement-description">{{ $requirement->description }}</p>
                            @endif

                            @if($requirement->instructions)
                                <div class="instruction-callout">
                                    <span aria-hidden="true">i</span>
                                    <div><strong>Submission instructions</strong><p>{{ $requirement->instructions }}</p></div>
                                </div>
                            @endif

                            <div class="requirement-meta-row">
                                <span>{{ $requirement->is_required ? 'Required' : 'Optional' }}</span>
                                <span>{{ $requirement->acceptedTypesLabel() }}</span>
                                <span>Up to {{ number_format($requirement->max_size_kb / 1024) }} MB</span>
                            </div>

                            @if($submission)
                                <div class="submitted-file">
                                    <div class="file-icon" aria-hidden="true">DOC</div>
                                    <div class="file-details">
                                        <strong>{{ $submission->original_name }}</strong>
                                        <span>Submitted {{ $submission->submitted_at->format('M d, Y · g:i A') }}</span>
                                    </div>
                                    <a class="secondary-action" href="{{ route($routePrefix.'.facilitator-requirements.download', $submission) }}">Download</a>
                                </div>
                            @endif

                            @if($submission?->review_notes)
                                <div class="reviewer-note status-{{ $status }}">
                                    <strong>Reviewer feedback</strong>
                                    <p>{{ $submission->review_notes }}</p>
                                </div>
                            @endif
                        </div>

                        <footer class="requirement-card-footer">
                            @if($submission?->status === 'verified')
                                <div class="verified-message"><span aria-hidden="true">✓</span><div><strong>Requirement complete</strong><small>No further action is needed.</small></div></div>
                            @elseif($submission?->status === 'pending')
                                <div class="pending-message"><span aria-hidden="true">◷</span><div><strong>Waiting for review</strong><small>You can replace the file if an update is needed.</small></div></div>
                            @endif

                            <details class="upload-panel" @if(!$submission || $canResubmit) open @endif>
                                <summary>{{ $submission ? ($canResubmit ? 'Upload corrected file' : 'Replace submitted file') : 'Upload requirement' }}</summary>
                                <form method="POST" action="{{ route($routePrefix.'.facilitator-requirements.submit', $requirement) }}" enctype="multipart/form-data" class="requirement-form">
                                    @csrf
                                    <div class="file-upload-field">
                                        <label for="requirement-file-{{ $requirement->id }}">File to submit</label>
                                        <label class="upload-dropzone" for="requirement-file-{{ $requirement->id }}">
                                            <span class="upload-icon" aria-hidden="true">↑</span>
                                            <span class="upload-copy">
                                                <strong>{{ $submission ? 'Select a replacement file' : 'Select a document' }}</strong>
                                                <small id="requirement-file-help-{{ $requirement->id }}">{{ $requirement->acceptedTypesLabel() }} · Maximum {{ number_format($requirement->max_size_kb / 1024) }} MB</small>
                                            </span>
                                        </label>
                                        <input
                                            id="requirement-file-{{ $requirement->id }}"
                                            class="upload-native-input"
                                            type="file"
                                            name="file"
                                            required
                                            aria-describedby="requirement-file-help-{{ $requirement->id }}"
                                            accept="{{ collect($requirement->accepted_extensions)->map(fn($extension) => '.'.$extension)->implode(',') }}"
                                        >
                                        <p class="upload-selection-note">The selected file name will appear beside the button.</p>
                                    </div>
                                    <label class="requirement-field">
                                        <span>Notes for reviewer <small>Optional</small></span>
                                        <textarea name="facilitator_notes" rows="2" maxlength="2000" placeholder="Add context about this document...">{{ old('facilitator_notes', $submission?->facilitator_notes) }}</textarea>
                                    </label>
                                    <button class="requirement-primary-button" type="submit">{{ $submission ? 'Submit updated file' : 'Submit for verification' }}</button>
                                </form>
                            </details>
                        </footer>
                    </article>
                @empty
                    <article class="requirements-empty-state">
                        <span aria-hidden="true">✓</span>
                        <h4>No active requirements</h4>
                        <p>There are currently no facilitator documents assigned to your component.</p>
                    </article>
                @endforelse
            </div>
        </section>
    @else
        @php
            $pendingCount = $submissions->where('status', 'pending')->count();
            $correctionCount = $submissions->whereIn('status', ['needs_correction', 'rejected'])->count();
            $fullyCompliantCount = $complianceRows->where('percentage', 100)->count();
            $overallCompliance = $complianceRows->isEmpty() ? 100 : (int) round($complianceRows->avg('percentage'));
        @endphp

        <section class="requirements-hero reviewer-hero">
            <div class="requirements-hero-copy">
                <span class="requirements-kicker">Compliance workspace</span>
                <h2>Review documents and keep facilitators on track.</h2>
                <p>See who needs assistance, process pending submissions, and manage document requirements from one organized workspace.</p>
                <div class="hero-actions">
                    <a href="#verification-queue" class="hero-primary-action">Review pending files <span>{{ $pendingCount }}</span></a>
                    <a href="#compliance-overview" class="hero-secondary-action">View compliance</a>
                </div>
            </div>
            <div class="reviewer-hero-summary">
                <div class="mini-progress-ring" style="--progress: {{ $overallCompliance }}"><strong>{{ $overallCompliance }}%</strong></div>
                <div><span>Average compliance</span><strong>{{ $fullyCompliantCount }} of {{ $complianceRows->count() }}</strong><small>facilitators fully compliant</small></div>
            </div>
        </section>

        <section class="requirement-stat-grid four-column" aria-label="Compliance overview">
            <article class="requirement-stat-card blue"><span class="stat-icon" aria-hidden="true">👥</span><div><span>Facilitators</span><strong>{{ $complianceRows->count() }}</strong><small>Within your scope</small></div></article>
            <article class="requirement-stat-card green"><span class="stat-icon" aria-hidden="true">✓</span><div><span>Fully compliant</span><strong>{{ $fullyCompliantCount }}</strong><small>All files verified</small></div></article>
            <article class="requirement-stat-card amber"><span class="stat-icon" aria-hidden="true">◷</span><div><span>Pending review</span><strong>{{ $pendingCount }}</strong><small>Awaiting a decision</small></div></article>
            <article class="requirement-stat-card red"><span class="stat-icon" aria-hidden="true">!</span><div><span>Needs attention</span><strong>{{ $correctionCount }}</strong><small>Correction or rejection</small></div></article>
        </section>

        @if($isConfigurator)
            <details class="configuration-panel">
                <summary>
                    <span class="configuration-icon" aria-hidden="true">＋</span>
                    <span><strong>Create a new requirement</strong><small>Define a document and assign it to all facilitators or one component.</small></span>
                    <span class="configuration-toggle">Open setup</span>
                </summary>
                <div class="configuration-body">
                    <form method="POST" action="{{ route($routePrefix.'.facilitator-requirements.store') }}" class="configuration-form">
                        @csrf
                        <label class="requirement-field"><span>Requirement title</span><input name="title" required maxlength="255" placeholder="e.g. Appointment or Designation"></label>
                        <label class="requirement-field"><span>Component scope</span><select name="component_id"><option value="">All NSTP facilitators</option>@foreach($components as $component)<option value="{{ $component->id }}">{{ $component->code }}</option>@endforeach</select></label>
                        <label class="requirement-field"><span>Maximum file size</span><select name="max_size_mb"><option value="5">5 MB</option><option value="10">10 MB</option><option value="15">15 MB</option><option value="25">25 MB</option></select></label>
                        <label class="requirement-field"><span>Display order</span><input type="number" name="sort_order" value="0" min="0" max="999" required></label>
                        <label class="requirement-field full"><span>Description</span><textarea name="description" rows="2" maxlength="1000" placeholder="What is this document for?"></textarea></label>
                        <label class="requirement-field full"><span>Instructions</span><textarea name="instructions" rows="2" maxlength="2000" placeholder="Give facilitators clear submission instructions..."></textarea></label>
                        <fieldset class="requirement-field full option-fieldset">
                            <legend>Accepted file types</legend>
                            <div class="option-row">@foreach($extensionOptions as $extension)<label><input type="checkbox" name="accepted_extensions[]" value="{{ $extension }}" @checked($extension === 'pdf')><span>{{ strtoupper($extension) }}</span></label>@endforeach</div>
                        </fieldset>
                        <div class="toggle-options full">
                            <label><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" checked><span><strong>Required for compliance</strong><small>Must be verified to reach 100%</small></span></label>
                            <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked><span><strong>Active requirement</strong><small>Visible to assigned facilitators</small></span></label>
                        </div>
                        <div class="configuration-actions full"><button class="requirement-primary-button" type="submit">Create requirement</button></div>
                    </form>
                </div>
            </details>
        @endif

        <section class="workspace-section" id="compliance-overview">
            <div class="requirements-section-heading">
                <div><span class="requirements-kicker">Compliance overview</span><h3>Facilitator progress</h3><p>Progress is based on required documents that have been verified.</p></div>
                <span class="section-count">{{ $complianceRows->count() }} facilitators</span>
            </div>

            <div class="compliance-list">
                @forelse($complianceRows as $row)
                    @php
                        $componentCodes = $row['facilitator']->facilitatedSections->pluck('component.code')->unique()->filter()->implode(', ');
                        $initials = collect(explode(' ', $row['facilitator']->name))->filter()->take(2)->map(fn($part) => mb_substr($part, 0, 1))->implode('');
                    @endphp
                    <article class="compliance-person-row">
                        <div class="facilitator-profile">
                            <span class="facilitator-avatar">{{ strtoupper($initials) }}</span>
                            <div><strong>{{ $row['facilitator']->name }}</strong><span>{{ $row['facilitator']->email }}</span><small>{{ $componentCodes ?: 'Unassigned component' }}</small></div>
                        </div>
                        <div class="facilitator-progress">
                            <div class="progress-copy"><span>{{ $row['verified'] }} of {{ $row['required'] }} required verified</span><strong>{{ $row['percentage'] }}%</strong></div>
                            <div class="progress-track" role="progressbar" aria-label="{{ $row['facilitator']->name }} compliance" aria-valuenow="{{ $row['percentage'] }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $row['percentage'] }}%"></span></div>
                            <div class="progress-breakdown">
                                <span class="verified">{{ $row['verified'] }} verified</span>
                                <span class="pending">{{ $row['pending'] }} pending</span>
                                <span class="correction">{{ $row['correction'] }} correction</span>
                                <span class="missing">{{ $row['missing'] }} missing</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <article class="requirements-empty-state compact"><span aria-hidden="true">👥</span><h4>No facilitators in scope</h4><p>Facilitators assigned to your component will appear here.</p></article>
                @endforelse
            </div>
        </section>

        <section class="workspace-section" id="verification-queue">
            <div class="requirements-section-heading">
                <div><span class="requirements-kicker">Verification queue</span><h3>Submitted documents</h3><p>Open a submission, review the file, and record a clear decision.</p></div>
                <span class="section-count attention">{{ $pendingCount }} pending</span>
            </div>

            <div class="review-queue">
                @forelse($submissions as $submission)
                    <article class="review-card status-{{ $submission->status }}">
                        <div class="review-card-summary">
                            <div class="review-document-icon" aria-hidden="true">DOC</div>
                            <div class="review-document-details">
                                <div class="review-title-line">
                                    <h4>{{ $submission->requirement->title }}</h4>
                                    <span class="requirement-status status-{{ $submission->status }}"><i></i>{{ $submission->statusLabel() }}</span>
                                </div>
                                <p>Submitted by <strong>{{ $submission->facilitator->name }}</strong></p>
                                <div class="review-metadata">
                                    <span>{{ $submission->requirement->component?->code ?? 'All NSTP' }}</span>
                                    <span>{{ $submission->submitted_at->format('M d, Y · g:i A') }}</span>
                                    <span>{{ number_format($submission->size_bytes / 1024, 0) }} KB</span>
                                </div>
                                @if($submission->facilitator_notes)<div class="submitter-note"><strong>Facilitator note:</strong> {{ $submission->facilitator_notes }}</div>@endif
                            </div>
                            <a class="secondary-action download-action" href="{{ route($routePrefix.'.facilitator-requirements.download', $submission) }}">Download file</a>
                        </div>
                        <form method="POST" action="{{ route($routePrefix.'.facilitator-requirements.review', $submission) }}" class="review-form">
                            @csrf
                            @method('PUT')
                            <label class="requirement-field"><span>Review decision</span><select name="status" required>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($submission->status === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="requirement-field review-note-field"><span>Reviewer notes</span><textarea name="review_notes" rows="2" maxlength="2000" placeholder="Explain the decision or correction needed...">{{ $submission->review_notes }}</textarea></label>
                            <button class="requirement-primary-button" type="submit">Save decision</button>
                        </form>
                    </article>
                @empty
                    <article class="requirements-empty-state compact"><span aria-hidden="true">✓</span><h4>No submissions yet</h4><p>Uploaded facilitator requirements will appear here for review.</p></article>
                @endforelse
            </div>
        </section>

        <section class="workspace-section">
            <div class="requirements-section-heading">
                <div><span class="requirements-kicker">Requirement library</span><h3>Configured documents</h3><p>Review the scope, file validation, and availability of each requirement.</p></div>
                <span class="section-count">{{ $requirements->count() }} configured</span>
            </div>

            <div class="requirement-library">
                @forelse($requirements as $requirement)
                    <article class="library-card {{ $requirement->is_active ? '' : 'inactive' }}">
                        <div class="library-card-main">
                            <div class="library-icon" aria-hidden="true">{{ strtoupper(substr($requirement->accepted_extensions[0] ?? 'file', 0, 3)) }}</div>
                            <div class="library-details">
                                <div class="library-title-line"><h4>{{ $requirement->title }}</h4><span class="availability-badge {{ $requirement->is_active ? 'active' : 'inactive' }}">{{ $requirement->is_active ? 'Active' : 'Inactive' }}</span></div>
                                <p>{{ $requirement->description ?: 'No description provided.' }}</p>
                                <div class="requirement-meta-row"><span>{{ $requirement->component?->code ?? 'All NSTP' }}</span><span>{{ $requirement->is_required ? 'Required' : 'Optional' }}</span><span>{{ $requirement->acceptedTypesLabel() }}</span><span>{{ number_format($requirement->max_size_kb / 1024) }} MB max</span></div>
                            </div>
                        </div>
                        @if($isConfigurator)
                            <details class="edit-requirement-panel">
                                <summary>Edit requirement</summary>
                                <form method="POST" action="{{ route($routePrefix.'.facilitator-requirements.update', $requirement) }}" class="edit-requirement-form">
                                    @csrf
                                    @method('PUT')
                                    <label class="requirement-field"><span>Title</span><input name="title" value="{{ $requirement->title }}" required></label>
                                    <label class="requirement-field"><span>Component</span><select name="component_id"><option value="">All NSTP</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected($requirement->component_id === $component->id)>{{ $component->code }}</option>@endforeach</select></label>
                                    <label class="requirement-field full"><span>Description</span><textarea name="description" rows="2">{{ $requirement->description }}</textarea></label>
                                    <label class="requirement-field full"><span>Instructions</span><textarea name="instructions" rows="2">{{ $requirement->instructions }}</textarea></label>
                                    <fieldset class="requirement-field full option-fieldset"><legend>Accepted file types</legend><div class="option-row">@foreach($extensionOptions as $extension)<label><input type="checkbox" name="accepted_extensions[]" value="{{ $extension }}" @checked(in_array($extension, $requirement->accepted_extensions, true))><span>{{ strtoupper($extension) }}</span></label>@endforeach</div></fieldset>
                                    <label class="requirement-field"><span>Maximum MB</span><input type="number" name="max_size_mb" value="{{ $requirement->max_size_kb / 1024 }}" min="1" max="25" required></label>
                                    <label class="requirement-field"><span>Display order</span><input type="number" name="sort_order" value="{{ $requirement->sort_order }}" min="0" max="999" required></label>
                                    <div class="toggle-options full"><label><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" @checked($requirement->is_required)><span><strong>Required</strong><small>Included in compliance</small></span></label><label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($requirement->is_active)><span><strong>Active</strong><small>Visible to facilitators</small></span></label></div>
                                    <div class="configuration-actions full"><button class="requirement-primary-button" type="submit">Save changes</button></div>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <article class="requirements-empty-state compact"><span aria-hidden="true">📄</span><h4>No requirements configured</h4><p>Create the first facilitator document requirement above.</p></article>
                @endforelse
            </div>
        </section>
    @endif
</div>
@endsection
