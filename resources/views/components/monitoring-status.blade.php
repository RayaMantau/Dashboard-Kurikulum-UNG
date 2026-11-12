@props(['curriculum'])
@php($schedule = app(\App\Services\Curriculum\CurriculumMonitoringService::class)->schedule($curriculum))
<span class="monitoring-status monitoring-{{ $schedule['state'] }}">{{ $schedule['label'] }}</span>
@if($schedule['minor'])
    <small>Minor: {{ $schedule['minor']->locale('id')->translatedFormat('j F Y') }}</small>
    <small>Rekonstruksi: {{ $schedule['reconstruction']->locale('id')->translatedFormat('j F Y') }}</small>
@else
    <small>Belum dapat dihitung</small>
@endif
