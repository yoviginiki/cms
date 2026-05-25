@use('App\Support\Blocks\BlockStyle')
@use('App\Support\Blocks\BlockEffects')
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
@php
    // Card effects
    $__effectsEnabled = BlockEffects::isEnabled($data ?? []);
    $__imageFilter = BlockEffects::imageFilterStyle($data ?? []);
    $__overlayHtml = BlockEffects::overlayHtml($data ?? []);
    $__effectScope = $__effectsEnabled ? 'bfx-' . substr(md5($__htmlId ?: uniqid('', true)), 0, 8) : '';
    $__hoverCss = $__effectScope ? BlockEffects::cardHoverCss($data ?? [], $__effectScope) : '';
    $__revealEnabled = BlockEffects::isRevealEnabled($data ?? []);
    $__revealMode = in_array(($data['effects']['imageHoverReveal']['mode'] ?? 'fade'), ['none','fade','reveal-left','reveal-right','reveal-top','reveal-bottom','circle','diagonal']) ? ($data['effects']['imageHoverReveal']['mode'] ?? 'fade') : 'fade';
    $__isFadeReveal = $__revealMode === 'fade' || $__revealMode === 'none';
    $__revealDuration = max(150, min(1500, intval($data['effects']['imageHoverReveal']['duration'] ?? 500)));
    $__revealEasing = in_array($data['effects']['imageHoverReveal']['easing'] ?? 'ease-out', ['ease','ease-out','ease-in-out']) ? ($data['effects']['imageHoverReveal']['easing'] ?? 'ease-out') : 'ease-out';
    if ($__revealEnabled && $__effectScope && $__isFadeReveal) {
        $__revealImgCss = ".{$__effectScope}:hover .img-filtered{filter:none!important}.{$__effectScope} .img-filtered{transition:filter {$__revealDuration}ms {$__revealEasing}}@media(prefers-reduced-motion:reduce){.{$__effectScope} .img-filtered{transition:none!important}}";
    } elseif ($__revealEnabled && $__effectScope) {
        $__revealImgCss = BlockEffects::revealCss($data ?? [], $__effectScope);
    } else {
        $__revealImgCss = '';
    }
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
@if($__hoverCss || $__revealImgCss)<style>{{ $__hoverCss }}{{ $__revealImgCss }}</style>@endif
<div class="image-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
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
                    class="img-filtered"
                    src="{{ $variants['medium_800'] ?? $url }}"
                    @if(!empty($variants['thumb_200']))srcset="{{ $variants['thumb_200'] }} 200w, {{ $variants['medium_800'] ?? $url }} 800w"@endif
                    alt="{{ $alt }}"
                    @if($width)width="{{ $width }}"@endif
                    @if($height)height="{{ $height }}"@endif
                    loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                    decoding="async"
                    @if($__imageFilter)style="{{ $__imageFilter }}"@endif
                >
            </picture>
        @else
            <img
                class="img-filtered"
                src="{{ $url }}"
                alt="{{ $alt }}"
                @if($width)width="{{ $width }}"@endif
                @if($height)height="{{ $height }}"@endif
                loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                decoding="async"
                @if($__imageFilter)style="{{ $__imageFilter }}"@endif
            >
        @endif
    @endif
    @if(!empty($caption))
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>

</div>