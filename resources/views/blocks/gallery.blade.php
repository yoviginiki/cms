@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba);
    $__customClass = BlockStyle::safeClass($__adv['customClass'] ?? '');
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="gallery-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
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

</div>