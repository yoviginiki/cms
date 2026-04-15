@php
    $count = $data['column_count'] ?? 2;
    $gapMap = ['none' => '0', 'small' => '1rem', 'medium' => '2rem', 'large' => '3rem'];
    $gap = $gapMap[$data['gap'] ?? 'medium'] ?? '2rem';
@endphp
<div class="columns-block" style="display: grid; grid-template-columns: repeat({{ $count }}, 1fr); gap: {{ $gap }};">
    {!! $children !!}
</div>
