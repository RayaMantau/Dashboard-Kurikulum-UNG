<div class="dashboard-columns">
    <section class="portal-card dashboard-section">
        <h2>Ringkasan Status Kurikulum</h2>
        <p class="dashboard-muted">Seluruh versi kurikulum di lingkungan universitas.</p>
        <dl class="dashboard-status-list">
            @foreach(\App\Enums\CurriculumStatus::cases() as $status)
                <div><dt><x-internal.status-badge :status="$status" /></dt><dd>{{ number_format($statusCounts[$status->value], 0, ',', '.') }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="portal-card dashboard-section">
        <h2>Aktivitas Terbaru</h2>
        <p class="dashboard-muted">Maksimal 5 aktivitas workflow kurikulum terbaru.</p>
        <ul class="dashboard-records">
            @forelse($content['activities'] as $activity)
                <li>
                    <strong>{{ $content['activityLabels'][$activity->action] }}</strong>
                    <p>{{ $activity->user?->name ?? 'Pengguna tidak tersedia' }}</p>
                    <time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('d/m/Y H:i') }}</time>
                </li>
            @empty
                <li class="dashboard-empty">Belum ada aktivitas terbaru.</li>
            @endforelse
        </ul>
    </section>
</div>
