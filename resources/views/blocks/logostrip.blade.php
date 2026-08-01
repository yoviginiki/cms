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
<div class="logostrip-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $logos = $data['logos'] ?? [];
    $grayscale = $data['grayscale'] ?? true;
    $columns = $data['columns'] ?? 4;
    $gap = $data['gap'] ?? '32px';
    // Uniform logo sizing: constrain by height OR width (in px); the other axis
    // is auto so aspect ratio is preserved. Defaults to 48px height (legacy 3rem).
    $sizeMode = ($data['sizeMode'] ?? 'height') === 'width' ? 'width' : 'height';
    $logoSize = max(8, min(600, (int) ($data['logoSize'] ?? 48)));
    $sizeStyle = $sizeMode === 'width' ? "width:{$logoSize}px;height:auto;" : "height:{$logoSize}px;width:auto;";
@endphp
<div class="logostrip-block" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:{{ e($gap) }};">
    @foreach($logos as $i => $url)
        @if(!empty($url))
            <img{!! sp_editable($__blockId ?? '', "logos.{$i}", 'image') !!} class="img-filtered" src="{{ e($url) }}" alt="" loading="lazy" style="{{ $sizeStyle }}object-fit:contain;@if($grayscale)filter:grayscale(100%);@endif{{ $__imageFilter }}">
        @endif
    @endforeach
</div>

</div>