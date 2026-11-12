@props(['programs', 'internal' => false])
<div class="monitoring-table-wrap" tabindex="0" role="region" aria-label="Monitoring kurikulum program studi">
    <table class="monitoring-table">
        <caption class="sr-only">Monitoring kurikulum yang sedang dipublikasikan per Program Studi</caption>
        <thead><tr>
            <th scope="col">Program Studi</th><th scope="col">Tahun Perbaikan Kurikulum</th>
            <th scope="col">Tanggal Pengesahan</th><th scope="col">Dokumen</th>
            <th scope="col">Link File Kurikulum</th><th scope="col">Monev Kurikulum</th>
            @if($internal)<th scope="col">Status Versi Terbaru</th>@endif
        </tr></thead>
        <tbody>
            @foreach($programs as $program)
                @php
                    $current = $program->curricula->first();
                    $public = $program->is_active && $program->faculty?->is_active;
                    $externalUrl = $current?->safeExternalDocumentUrl();
                @endphp
                <tr>
                    <th scope="row">
                        {{ $program->name }}
                        <small>{{ $program->faculty?->name }}</small>
                        @if($current)<small>{{ $current->name }}</small>
                        @else<small>Belum ada kurikulum yang dipublikasikan.</small>@endif
                    </th>
                    <td>{{ $current?->year ?? 'Belum tersedia' }}</td>
                    <td>{{ $current?->ratified_at?->locale('id')->translatedFormat('j F Y') ?? 'Belum ditetapkan' }}</td>
                    <td>
                        @if($current && $current->documents_count > 0)
                            @if($public)
                                <a href="{{ route('study-programs.curricula.show', [$program->slug, $current->id]) }}#documents-title">Lihat Dokumen ({{ $current->documents_count }})</a>
                            @else
                                {{ $current->documents_count }} dokumen (akses publik tidak tersedia)
                            @endif
                        @else
                            Belum tersedia
                        @endif
                    </td>
                    <td>
                        @if($externalUrl)<a href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer">Buka File Kurikulum</a>
                        @else Belum tersedia @endif
                    </td>
                    <td><x-monitoring-status :curriculum="$current" /></td>
                    @if($internal)<td><x-internal.status-badge :status="$program->latest_curriculum_status" /></td>@endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
