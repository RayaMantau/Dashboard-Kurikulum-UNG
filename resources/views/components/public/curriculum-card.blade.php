@props(['curriculum', 'studyProgram'])

<article class="curriculum-card">
    <span class="public-badge {{ $curriculum->status === \App\Enums\CurriculumStatus::ARCHIVED ? 'public-badge-archive' : '' }}">{{ $curriculum->status === \App\Enums\CurriculumStatus::ARCHIVED ? 'Arsip' : 'Kurikulum Aktif' }}</span>
    <h3>{{ $curriculum->name }}</h3>
    <p>Tahun: {{ $curriculum->year }}</p>
    @if($curriculum->effective_year !== null)<p>Tahun berlaku: {{ $curriculum->effective_year }}</p>@endif
    <p>Total SKS: {{ $curriculum->total_sks }}</p>
    <p>Jumlah semester: {{ $curriculum->total_semesters }}</p>
    <a class="public-card-link" href="{{ route('study-programs.curricula.show', [$studyProgram->slug, $curriculum->id]) }}" aria-label="Lihat Kurikulum: {{ $curriculum->name }}">Lihat Kurikulum <span aria-hidden="true">&rarr;</span></a>
</article>
