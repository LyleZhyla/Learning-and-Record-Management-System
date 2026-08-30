@if ($paginator->hasPages())
    <nav class="pagination-controls" role="navigation" aria-label="Pagination navigation">
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
    </nav>
@endif
