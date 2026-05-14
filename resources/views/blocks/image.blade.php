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
<div class="image-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $url = $data['url'] ?? '';
    $alt = $data['alt'] ?? '';
    $caption = $data['caption'] ?? '';
    $size = $data['size'] ?? 'full';
    $sizeMap = ['small' => '320px', 'medium' => '640px', 'large' => '960px'];
    $maxWidth = $sizeMap[$size] ?? '100%';
    $width = $data['width'] ?? null;
    $height = $data['height'] ?? null;
    $variants = $data['variants'] ?? [];
    $isFirst = $data['_is_first_image'] ?? false;
@endphp
<figure class="image-block"@if($size !== 'full') style="max-width: {{ $maxWidth }};"@endif>
    @if(!empty($url))
        @if(!empty($variants['webp_800']) || !empty($variants['webp_400']))
            <picture>
                <source srcset="{{ $variants['webp_400'] ?? $variants['webp_800'] ?? '' }} 400w@if(!empty($variants['webp_800'])), {{ $variants['webp_800'] }} 800w@endif" type="image/webp">
                <img
                    src="{{ $variants['medium_800'] ?? $url }}"
                    @if(!empty($variants['thumb_200']))srcset="{{ $variants['thumb_200'] }} 200w, {{ $variants['medium_800'] ?? $url }} 800w"@endif
                    alt="{{ $alt }}"
                    @if($width)width="{{ $width }}"@endif
                    @if($height)height="{{ $height }}"@endif
                    loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                    decoding="async"
                >
            </picture>
        @else
            <img
                src="{{ $url }}"
                alt="{{ $alt }}"
                @if($width)width="{{ $width }}"@endif
                @if($height)height="{{ $height }}"@endif
                loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                decoding="async"
            >
        @endif
    @endif
    @if(!empty($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>

</div>