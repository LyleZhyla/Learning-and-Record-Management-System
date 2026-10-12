@if($paginator->total() > 0)
    <div class="list-page-size-toolbar">
        <div>
            <strong>{{ $title ?? 'Items per page' }}</strong>
            <span>{{ $description ?? 'Choose how many records to show.' }}</span>
        </div>
        @include('pagination.page-size', ['paginator' => $paginator])
    </div>
@endif
