@php
    $offsetY = $data['offsetY'] ?? '-40px';
    $offsetX = $data['offsetX'] ?? '0';
    $zIndex = $data['zIndex'] ?? 1;
@endphp
<div style="margin-top: {{ $offsetY }}; margin-left: {{ $offsetX }}; position: relative; z-index: {{ $zIndex }};">
    {!! $children !!}
</div>
