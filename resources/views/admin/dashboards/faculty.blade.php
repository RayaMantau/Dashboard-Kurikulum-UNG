<section class="portal-card dashboard-section">
    <h2>Ikhtisar Kurikulum Program Studi</h2>
    <p class="dashboard-muted">Monitoring kurikulum yang sedang dipublikasikan. Seluruh Program Studi dalam fakultas Anda tersedia melalui navigasi halaman; status versi terbaru tetap ditampilkan.</p>
    @if($content['programs']->isEmpty())
        <p class="dashboard-empty">Belum ada program studi dalam fakultas ini.</p>
    @else
        <x-monitoring-table :programs="$content['programs']" :internal="true" />
        <x-internal.pagination :paginator="$content['programs']" />
    @endif
</section>
