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
<div class="fullbleed-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $src = $data['src'] ?? '';
    $alt = $data['alt'] ?? '';
    $overlayText = $data['overlayText'] ?? '';
    $overlayPosition = $data['overlayPosition'] ?? 'center';
    $scrimOpacity = $data['scrimOpacity'] ?? 0.4;
    $minHeight = $data['minHeight'] ?? '60vh';

    $positionStyles = [
        'center' => 'align-items:center;justify-content:center;text-align:center;',
        'bottom-left' => 'align-items:flex-end;justify-content:flex-start;text-align:left;',
        'bottom-right' => 'align-items:flex-end;justify-content:flex-end;text-align:right;',
    ];
    $posStyle = $positionStyles[$overlayPosition] ?? $positionStyles['center'];
@endphp
<section class="fullbleed-block" style="position:relative;min-height:{{ e($minHeight) }};@if(!empty($src))background-image:url('{{ e($src) }}');background-size:cover;background-position:center;@endif">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,{{ $scrimOpacity }});"></div>
    @if(!empty($overlayText))
        <div style="position:relative;z-index:1;display:flex;{{ $posStyle }}min-height:{{ e($minHeight) }};padding:2rem;">
            <p style="color:var(--color-text-inverse,#fff);font-size:1.5rem;font-weight:700;max-width:42rem;">{{ e($overlayText) }}</p>
        </div>
    @endif
</section>

</div>