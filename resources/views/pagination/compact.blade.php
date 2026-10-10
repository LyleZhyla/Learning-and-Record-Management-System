@php
    $selectedPageSize = request()->query('per_page') === 'all'
        ? 'all'
        : (string) $paginator->perPage();
@endphp
<nav class="pagination-controls" role="navigation" aria-label="Pagination navigation">
    <label class="page-size-control">
        <span>Show</span>
        <select aria-label="Items per page" onchange="const url = new URL(window.location.href); url.searchParams.set('per_page', this.value); url.searchParams.delete('{{ $paginator->getPageName() }}'); window.location.assign(url.toString());">
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

    @if ($paginator->hasPages())
        @if ($paginator->onFirstPage())
            <span class="page-button disabled" aria-disabled="true">Previous</span>
        @else
            <a class="page-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        <span class="page-current">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="page-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="page-button disabled" aria-disabled="true">Next</span>
        @endif
    @else
        <span class="page-current">{{ $paginator->total() }} {{ Str::plural('item', $paginator->total()) }}</span>
    @endif
</nav>
