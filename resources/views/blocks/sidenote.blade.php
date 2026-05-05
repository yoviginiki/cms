@php
    $content = $data['content'] ?? '';
    $side = $data['side'] ?? 'right';
@endphp

<aside class="sidenote sidenote--{{ $side }}" style="float: {{ $side }}; max-width: 200px; margin: {{ $side === 'left' ? '0 1rem 0.5rem 0' : '0 0 0.5rem 1rem' }}; font-size: var(--font-size-sm); color: var(--color-text-muted);">
    {{ $content }}
</aside>
