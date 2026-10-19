@props(['paginator', 'label' => 'fakultas'])

@if($paginator->hasPages())
    <nav class="public-pagination" aria-label="Navigasi halaman {{ $label }}">
        <p>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}. Total {{ $paginator->total() }} {{ $label }}.</p>
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
