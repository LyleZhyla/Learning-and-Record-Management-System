@php
    $selectedPageSize = request()->query('per_page') === 'all'
        ? 'all'
        : (string) $paginator->perPage();
@endphp
<label class="page-size-control">
    <span>Show</span>
    <select name="per_page" aria-label="Items per page" onchange="const url = new URL(window.location.href); url.searchParams.set('per_page', this.value); url.searchParams.delete('{{ $paginator->getPageName() }}'); window.location.assign(url.toString());">
        @if ($selectedPageSize !== 'all' && ! in_array((int) $selectedPageSize, [10, 15, 20, 25, 50], true))
            <option value="{{ $selectedPageSize }}" selected>{{ $selectedPageSize }}</option>
        @endif
        @foreach ([10, 15, 20, 25, 50] as $pageSize)
            <option value="{{ $pageSize }}" @selected($selectedPageSize === (string) $pageSize)>{{ $pageSize }}</option>
        @endforeach
        <option value="all" @selected($selectedPageSize === 'all')>All</option>
    </select>
    <span>items</span>
</label>
