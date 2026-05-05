@php
    $capSize = $data['capSize'] ?? 3;
    $capColor = $data['capColor'] ?? null;
    $content = $data['content'] ?? '';
@endphp

<div class="dropcap" style="--cap-size: {{ $capSize }}; {{ $capColor ? '--cap-color: ' . $capColor . ';' : '' }}">
    <style>
        .dropcap::first-letter {
            float: left;
            font-size: calc(var(--cap-size) * 1em);
            line-height: 0.8;
            padding-right: 0.1em;
            font-weight: bold;
            color: var(--cap-color, var(--color-text));
        }
    </style>
    {!! $content !!}
</div>
