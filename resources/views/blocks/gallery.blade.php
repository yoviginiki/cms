@php
    $images = $data['images'] ?? [];
    $layout = $data['layout'] ?? 'grid';
    $columns = $data['columns'] ?? 3;
    $gap = $data['gap'] ?? '8px';
@endphp
<div class="gallery-block gallery-block--{{ $layout }}" style="display:grid;grid-template-columns:repeat({{ (int)$columns }}, 1fr);gap:{{ e($gap) }};">
    @foreach($images as $url)
        @if(!empty($url))
            <img src="{{ e($url) }}" alt="" loading="lazy" style="width:100%;height:auto;object-fit:cover;border-radius:4px;">
        @endif
    @endforeach
</div>
