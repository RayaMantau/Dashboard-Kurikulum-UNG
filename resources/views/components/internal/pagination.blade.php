@props(['paginator'])

@if($paginator->total() > 0)
    <nav class="management-pagination" aria-label="Navigasi halaman">
        <p>Menampilkan {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }} data. Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}.</p>
        <div>
            @if($paginator->previousPageUrl())
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
            @else
                <span aria-disabled="true">Sebelumnya</span>
            @endif
            @if($paginator->nextPageUrl())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
            @else
                <span aria-disabled="true">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
