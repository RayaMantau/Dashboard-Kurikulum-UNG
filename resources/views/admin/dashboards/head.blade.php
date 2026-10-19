@if(!$missingScope)
    <p><a href="{{ route('kaprodi.submissions.approved') }}">Menunggu publikasi: {{ $metrics['Disetujui'] }} kurikulum</a></p>
@endif
<section class="portal-card dashboard-section">
    <h2>Kurikulum Aktif</h2>
    @if($content['published'])
        <h3>{{ $content['published']->name }}</h3>
        <p>Tahun {{ $content['published']->year }}</p>
        <x-internal.status-badge :status="$content['published']->status" />
    @else
        <p class="dashboard-empty">Belum ada kurikulum yang dipublikasikan.</p>
    @endif
</section>
<div class="dashboard-columns">
    <section class="portal-card dashboard-section">
        <h2>Menunggu Review</h2>
        @if(!$missingScope)<p><a href="{{ route('kaprodi.submissions.index') }}">Lihat semua pengajuan</a></p>@endif
        <p class="dashboard-muted">Maksimal 10 pengajuan, dimulai dari yang paling lama menunggu.</p>
        <ul class="dashboard-records">
            @forelse($content['queue'] as $curriculum)
                <li>
                    <strong>{{ $curriculum->name }}</strong>
                    <p><a href="{{ route('kaprodi.submissions.show', $curriculum) }}">Review Kurikulum</a></p>
                    <p><x-internal.status-badge :status="$curriculum->status" /></p>
                    <p>Diajukan: {{ $curriculum->submitted_at?->format('d/m/Y H:i') ?? 'Waktu pengajuan belum tercatat' }}</p>
                </li>
            @empty
                <li class="dashboard-empty">Tidak ada kurikulum yang menunggu review.</li>
            @endforelse
        </ul>
    </section>
    <section class="portal-card dashboard-section">
        <h2>Riwayat Review Terbaru</h2>
        <p class="dashboard-muted">Maksimal 5 hasil review terbaru pada program studi Anda.</p>
        <ul class="dashboard-records">
            @forelse($content['reviews'] as $review)
                <li>
                    <strong>{{ $review->curriculum->name }}</strong>
                    <p><x-internal.status-badge :status="$review->status" /></p>
                    <time datetime="{{ $review->reviewed_at->toIso8601String() }}">{{ $review->reviewed_at->format('d/m/Y H:i') }}</time>
                </li>
            @empty
                <li class="dashboard-empty">Belum ada riwayat review.</li>
            @endforelse
        </ul>
    </section>
</div>
