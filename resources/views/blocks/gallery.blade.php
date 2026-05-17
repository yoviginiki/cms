@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba, $data ?? []);
    $__customClass = BlockStyle::buildClasses($__adv, $__ba);
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
    @foreach($images as $img)
        @php
            $src = is_array($img) ? ($img['src'] ?? $img['url'] ?? '') : (string) $img;
            $alt = is_array($img) ? ($img['alt'] ?? '') : '';
        @endphp
        @if(!empty($src))
            <img src="{{ e($src) }}" alt="{{ e($alt) }}" loading="lazy" style="width:100%;height:auto;object-fit:cover;border-radius:4px;">
        @endif
    @endforeach
</div>

</div>