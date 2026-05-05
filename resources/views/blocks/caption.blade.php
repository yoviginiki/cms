@php
    $text = $data['text'] ?? '';
    $prefix = $data['prefix'] ?? 'Fig.';
@endphp

<figcaption style="font-size: var(--font-size-sm); color: var(--color-text-muted);">
    {{ $prefix }} {{ $text }}
</figcaption>
