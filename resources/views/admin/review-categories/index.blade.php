@extends($layout)

@section('title', 'Review Categories')
@section('page-title', 'Review Categories Configuration')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/review-categories.css') }}?v={{ filemtime(public_path('css/review-categories.css')) }}">
@endpush

@section('content')
@php
    $allCategories = $groups->flatten();
    $activeCount = $allCategories->where('is_active', true)->count();
    $defaultCount = $allCategories->where('is_default', true)->count();
@endphp

<section class="page-actions review-category-page-heading">
    <div>
        <span class="eyebrow">Review workflow setup</span>
        <h2>Manage the choices reviewers can use</h2>
        <p>Control the labels, colors, and workflow results shown during registration review. Open a category only when you need to change it.</p>
    </div>
    <div class="review-category-summary" aria-label="Review category summary">
        <span><strong>{{ $allCategories->count() }}</strong> total</span>
        <span><strong>{{ $activeCount }}</strong> active</span>
        <span><strong>{{ $defaultCount }}</strong> automatic defaults</span>
    </div>
</section>

<section class="review-category-guide" aria-label="How review categories work">
    <article><span>1</span><div><strong>Choose a review area</strong><p>Registration statuses track the whole application. Document decisions apply to individual registration files.</p></div></article>
    <article><span>2</span><div><strong>Match a workflow outcome</strong><p>The outcome tells the system whether work is pending, underway, approved, or needs correction.</p></div></article>
    <article><span>3</span><div><strong>Set one default when needed</strong><p>The automatic default is the preferred status the system uses for that outcome.</p></div></article>
</section>

<details class="card review-category-create-card" @if($errors->any() && ! $errors->has('review_category')) open @endif>
    <summary class="review-category-create-summary">
        <span class="review-category-add-icon" aria-hidden="true">+</span>
        <span><small>Add a new option</small><strong>Create review category</strong><em>Use this only when the existing choices do not cover a review decision.</em></span>
        <span class="review-category-summary-action">Open form <i aria-hidden="true">⌄</i></span>
    </summary>
    <form method="POST" action="{{ route($routePrefix.'.review-categories.store') }}" class="review-category-create-form">
        @csrf
        <div class="review-category-form-section">
            <div class="review-category-section-copy"><span>01</span><div><strong>Category identity</strong><p>Choose where this option appears and give it a clear reviewer-facing name.</p></div></div>
            <div class="review-category-fields identity-fields">
                <label class="field-group"><span>Review area</span><select name="scope" required>@foreach($scopes as $value => $label)<option value="{{ $value }}" @selected(old('scope') === $value)>{{ $label }}</option>@endforeach</select><small>Where reviewers will see this choice.</small></label>
                <label class="field-group"><span>Display name</span><input name="name" maxlength="100" value="{{ old('name') }}" placeholder="Example: For resubmission" required><small>Use a short, action-oriented label.</small></label>
            </div>
        </div>
        <div class="review-category-form-section">
            <div class="review-category-section-copy"><span>02</span><div><strong>Workflow behavior</strong><p>Connect the label to a system result, then choose how it looks and where it is ordered.</p></div></div>
            <div class="review-category-fields behavior-fields">
                <label class="field-group"><span>Workflow outcome</span><select name="outcome" required>@foreach($outcomes as $value => $label)<option value="{{ $value }}" @selected(old('outcome') === $value)>{{ $label }}</option>@endforeach</select><small>Controls the actions triggered by this category.</small></label>
                <label class="field-group review-category-color-field"><span>Color</span><span class="review-category-color-control"><input type="color" name="color" value="{{ old('color', '#687589') }}" required><b>Badge color</b></span><small>Used wherever this status appears.</small></label>
                <label class="field-group"><span>Display order</span><input type="number" name="sort_order" min="0" max="999" value="{{ old('sort_order', 50) }}" required><small>Lower numbers appear first.</small></label>
            </div>
        </div>
        <div class="review-category-create-footer">
            <div class="review-category-toggle-group">
                <label class="review-category-toggle"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))><span><strong>Active</strong><small>Available to reviewers immediately</small></span></label>
                <label class="review-category-toggle"><input type="checkbox" name="is_default" value="1" @checked(old('is_default'))><span><strong>Automatic default</strong><small>Preferred option for this outcome</small></span></label>
            </div>
            <button class="primary-button" type="submit">Create category</button>
        </div>
    </form>
</details>

<nav class="review-category-scope-nav" aria-label="Review category sections">
    <span>Jump to:</span>
    @foreach($scopes as $scope => $scopeLabel)
        <a href="#review-scope-{{ $scope }}">{{ $scopeLabel }} <b>{{ ($groups[$scope] ?? collect())->count() }}</b></a>
    @endforeach
</nav>

@foreach($scopes as $scope => $scopeLabel)
<section class="card review-category-scope-card" id="review-scope-{{ $scope }}">
    <div class="review-category-scope-heading">
        <div>
            <span class="eyebrow">Review area {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <h3>{{ $scopeLabel }} categories</h3>
            <p>{{ $scope === 'registration' ? 'Choices used for the overall student registration review.' : 'Decisions used when checking individual registration documents.' }}</p>
        </div>
        <span class="review-category-count">{{ ($groups[$scope] ?? collect())->count() }} configured</span>
    </div>

    <div class="review-category-list">
        @forelse($groups[$scope] ?? [] as $category)
            <details class="review-category-item" style="--review-category-color: {{ $category->color }}">
                <summary class="review-category-item-summary">
                    <span class="review-category-swatch" aria-hidden="true"></span>
                    <span class="review-category-item-name"><strong>{{ $category->name }}</strong><small>{{ $category->slug }}</small></span>
                    <span class="review-category-outcome">{{ $outcomes[$category->outcome] ?? str($category->outcome)->headline() }}</span>
                    <span class="review-category-state {{ $category->is_active ? 'active' : 'inactive' }}"><i></i>{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                    @if($category->is_default)<span class="review-category-default">Default</span>@endif
                    <span class="review-category-edit-label">Edit settings <i aria-hidden="true">⌄</i></span>
                </summary>

                <div class="review-category-item-body">
                    <div class="review-category-item-note">
                        <strong>{{ $category->is_system ? 'Built-in category' : 'Custom category' }}</strong>
                        <p>{{ $category->is_system ? 'This category is part of the core workflow. You can rename or deactivate it when another active category covers the same outcome.' : 'This option was added by an administrator and can be deleted when it is not in use or set as a default.' }}</p>
                    </div>

                    <form method="POST" action="{{ route($routePrefix.'.review-categories.update', $category) }}" class="review-category-edit-form">
                        @csrf @method('PUT')
                        <input type="hidden" name="scope" value="{{ $category->scope }}">
                        <div class="review-category-fields edit-fields">
                            <label class="field-group"><span>Display name</span><input name="name" maxlength="100" value="{{ $category->name }}" required><small>Shown to reviewers and students.</small></label>
                            <label class="field-group"><span>Workflow outcome</span><select name="outcome" required>@foreach($outcomes as $value => $label)<option value="{{ $value }}" @selected($category->outcome === $value)>{{ $label }}</option>@endforeach</select><small>Determines how the workflow treats this choice.</small></label>
                            <label class="field-group review-category-color-field"><span>Color</span><span class="review-category-color-control"><input type="color" name="color" value="{{ $category->color }}" required><b>{{ strtoupper($category->color) }}</b></span><small>Badge and indicator color.</small></label>
                            <label class="field-group"><span>Display order</span><input type="number" name="sort_order" min="0" max="999" value="{{ $category->sort_order }}" required><small>Lower numbers appear first.</small></label>
                        </div>
                        <div class="review-category-edit-footer">
                            <div class="review-category-toggle-group">
                                <label class="review-category-toggle"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)><span><strong>Active</strong><small>Available as a review choice</small></span></label>
                                <label class="review-category-toggle"><input type="checkbox" name="is_default" value="1" @checked($category->is_default)><span><strong>Automatic default</strong><small>Preferred for this outcome</small></span></label>
                            </div>
                            <button class="primary-button compact" type="submit">Save changes</button>
                        </div>
                    </form>

                    @unless($category->is_system)
                        <div class="review-category-danger-zone">
                            <div><strong>Delete custom category</strong><p>Deletion is available only when no records use this category and it is not an automatic default.</p></div>
                            <form method="POST" action="{{ route($routePrefix.'.review-categories.destroy', $category) }}" onsubmit="return confirm('Delete this unused review category? This cannot be undone.');">@csrf @method('DELETE')<button class="text-danger-button" type="submit">Delete category</button></form>
                        </div>
                    @endunless
                </div>
            </details>
        @empty
            <div class="empty-state"><strong>No categories in this review area</strong><span>Create one using the form above.</span></div>
        @endforelse
    </div>
</section>
@endforeach

<section class="card review-category-reset-card">
    <div class="review-category-reset-icon" aria-hidden="true">↺</div>
    <div>
        <span class="eyebrow">Recovery option</span>
        <h3>Reset to default settings</h3>
        <p>Restore all built-in category names, outcomes, colors, display order, active states, and automatic defaults. Custom categories and existing review records will not be deleted.</p>
    </div>
    <form method="POST" action="{{ route($routePrefix.'.review-categories.reset-defaults') }}" onsubmit="return confirm('Reset all built-in review categories to their default settings? Custom categories will be kept.');">
        @csrf
        <button class="review-category-reset-button" type="submit">Reset to defaults</button>
    </form>
</section>
@endsection
