@php
    $logos = $data['logos'] ?? [];
    $grayscale = $data['grayscale'] ?? true;
    $columns = $data['columns'] ?? 4;
    $gap = $data['gap'] ?? '32px';
@endphp
<div class="logostrip-block" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:{{ e($gap) }};">
    @foreach($logos as $url)
        @if(!empty($url))
            <img src="{{ e($url) }}" alt="" loading="lazy" style="height:3rem;object-fit:contain;@if($grayscale)filter:grayscale(100%);@endif">
        @endif
    @endforeach
</div>
