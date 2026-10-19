<div class="dashboard-columns">
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
    <section class="portal-card dashboard-section">
        <h2>Versi Terbaru &amp; Status Workflow</h2>
        @if($content['latest'])
            <h3>{{ $content['latest']->name }}</h3>
            <p>Tahun {{ $content['latest']->year }}</p>
            <x-internal.status-badge :status="$content['latest']->status" />
        @endif
        <p class="dashboard-empty">{{ $content['workflowMessage'] }}</p>
    </section>
</div>
