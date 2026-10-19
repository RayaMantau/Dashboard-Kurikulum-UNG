@props(['label', 'value'])

<div class="dashboard-stat">
    <p>{{ $label }}</p>
    <strong>{{ number_format($value, 0, ',', '.') }}</strong>
</div>
