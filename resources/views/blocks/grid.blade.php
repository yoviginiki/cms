@php
    $cols = $data['templateColumns'] ?? '1fr 1fr';
    $rows = $data['templateRows'] ?? 'auto';
    $gap = $data['gap'] ?? '16px';
    $flow = $data['autoFlow'] ?? 'row';
@endphp
<div style="display: grid; grid-template-columns: {{ $cols }}; grid-template-rows: {{ $rows }}; gap: {{ $gap }}; grid-auto-flow: {{ $flow }};">
    {!! $children !!}
</div>
