@props(['status'])

@php
    $curriculumStatus = $status instanceof \App\Enums\CurriculumStatus ? $status : \App\Enums\CurriculumStatus::tryFrom((string) $status);
@endphp
@if($curriculumStatus)
    <span class="status-badge status-{{ $curriculumStatus->value }}">{{ $curriculumStatus->label() }}</span>
@else
    <span class="dashboard-muted">Belum ada kurikulum</span>
@endif
