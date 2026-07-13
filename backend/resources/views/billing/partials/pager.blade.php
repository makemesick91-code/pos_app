@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
@endphp
@if($paginator->lastPage() > 1)
    <nav class="pager" aria-label="Navigasi halaman">
        @if($paginator->onFirstPage())
            <span aria-disabled="true">Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
        @endif

        <span class="current" aria-current="page">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>

        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
        @else
            <span aria-disabled="true">Berikutnya</span>
        @endif
    </nav>
@endif
