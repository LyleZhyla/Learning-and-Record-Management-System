@extends($layout)

@section('title', 'Review Categories')
@section('page-title', 'Review Categories Configuration')

@section('content')
<section class="page-actions">
    <div><span class="eyebrow">Configuration layer</span><h2>Review categories and workflow statuses</h2><p>Rename, color, order, deactivate, or extend registration and document review decisions without changing code.</p></div>
</section>

<section class="card review-category-create-card">
    <div class="card-heading"><div><span class="eyebrow">New category</span><h3>Add a review status</h3><p>The workflow outcome keeps automatic approval and correction behavior intact.</p></div></div>
    <form method="POST" action="{{ route($routePrefix.'.review-categories.store') }}" class="review-category-form create">
        @csrf
        <label class="field-group"><span>Review area</span><select name="scope" required>@foreach($scopes as $value => $label)<option value="{{ $value }}" @selected(old('scope') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field-group"><span>Display name</span><input name="name" maxlength="100" value="{{ old('name') }}" placeholder="For resubmission" required></label>
        <label class="field-group"><span>Workflow outcome</span><select name="outcome" required>@foreach($outcomes as $value => $label)<option value="{{ $value }}" @selected(old('outcome') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field-group"><span>Color</span><input type="color" name="color" value="{{ old('color', '#687589') }}" required></label>
        <label class="field-group"><span>Order</span><input type="number" name="sort_order" min="0" max="999" value="{{ old('sort_order', 50) }}" required></label>
        <label class="review-category-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Active</label>
        <label class="review-category-check"><input type="checkbox" name="is_default" value="1" @checked(old('is_default'))> Automatic default for outcome</label>
        <button class="primary-button compact" type="submit">Add category</button>
    </form>
</section>

@foreach($scopes as $scope => $scopeLabel)
<section class="card review-category-scope-card">
    <div class="card-heading"><div><span class="eyebrow">{{ $scopeLabel }}</span><h3>{{ $scopeLabel }} categories</h3><p>{{ ($groups[$scope] ?? collect())->count() }} configured statuses</p></div></div>
    <div class="review-category-list">
        @forelse($groups[$scope] ?? [] as $category)
            <article class="review-category-row">
                <span class="review-category-swatch" style="--review-category-color: {{ $category->color }}"></span>
                <form method="POST" action="{{ route($routePrefix.'.review-categories.update', $category) }}" class="review-category-form edit">
                    @csrf @method('PUT')
                    <input type="hidden" name="scope" value="{{ $category->scope }}">
                    <label class="field-group"><span>Display name</span><input name="name" maxlength="100" value="{{ $category->name }}" required></label>
                    <label class="field-group"><span>Workflow outcome</span><select name="outcome" required>@foreach($outcomes as $value => $label)<option value="{{ $value }}" @selected($category->outcome === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Color</span><input type="color" name="color" value="{{ $category->color }}" required></label>
                    <label class="field-group"><span>Order</span><input type="number" name="sort_order" min="0" max="999" value="{{ $category->sort_order }}" required></label>
                    <label class="review-category-check"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Active</label>
                    <label class="review-category-check"><input type="checkbox" name="is_default" value="1" @checked($category->is_default)> Automatic default</label>
                    <button class="secondary-outline-button" type="submit">Save</button>
                </form>
                <div class="review-category-meta"><code>{{ $category->slug }}</code>@if($category->is_system)<span>Built-in</span>@else<form method="POST" action="{{ route($routePrefix.'.review-categories.destroy', $category) }}" onsubmit="return confirm('Delete this unused review category?');">@csrf @method('DELETE')<button class="text-danger-button" type="submit">Delete</button></form>@endif</div>
            </article>
        @empty
            <div class="empty-state"><strong>No categories in this review area</strong><span>Add one using the form above.</span></div>
        @endforelse
    </div>
</section>
@endforeach
@endsection
