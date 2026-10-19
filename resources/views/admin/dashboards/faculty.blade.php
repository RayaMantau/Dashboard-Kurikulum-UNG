<section class="portal-card dashboard-section">
    <h2>Ikhtisar Kurikulum Program Studi</h2>
    <p class="dashboard-muted">Menampilkan maksimal 10 program studi berdasarkan nama. Statistik mencakup seluruh program studi dalam fakultas Anda.</p>
    @if($content['programs']->isEmpty())
        <p class="dashboard-empty">Belum ada program studi dalam fakultas ini.</p>
    @else
        <div class="dashboard-table-wrap" tabindex="0" role="region" aria-label="Ikhtisar kurikulum program studi">
            <table class="dashboard-table">
                <caption class="sr-only">Kurikulum yang dipublikasikan dan status versi terbaru per program studi</caption>
                <thead><tr><th scope="col">Program Studi</th><th scope="col">Kurikulum Dipublikasikan</th><th scope="col">Status Versi Terbaru</th></tr></thead>
                <tbody>
                    @foreach($content['programs'] as $program)
                        <tr>
                            <th scope="row">{{ $program->name }}</th>
                            <td>{{ $program->published_curriculum_name ?? 'Belum ada kurikulum yang dipublikasikan.' }}</td>
                            <td><x-internal.status-badge :status="$program->latest_curriculum_status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
