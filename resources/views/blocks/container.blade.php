@php
    $maxW = $data['maxWidth'] ?? '1200';
    $centered = $data['centered'] ?? true;
@endphp
<div style="max-width: {{ $maxW }}px;{{ $centered ? ' margin: 0 auto;' : '' }}">
    {!! $children !!}
</div>
