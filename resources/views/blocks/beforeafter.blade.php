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
<div class="beforeafter-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $beforeSrc = $data['beforeSrc'] ?? '';
    $afterSrc = $data['afterSrc'] ?? '';
    $beforeLabel = $data['beforeLabel'] ?? 'Before';
    $afterLabel = $data['afterLabel'] ?? 'After';
    $initialPosition = $data['initialPosition'] ?? 50;
@endphp
<div class="beforeafter-block" style="position:relative;overflow:hidden;max-width:100%;">
    @if(!empty($afterSrc))
        <img class="img-filtered" src="{{ e($afterSrc) }}" alt="{{ e($afterLabel) }}" loading="lazy" style="display:block;width:100%;height:auto;{{ $__imageFilter }}">
    @endif
    @if(!empty($beforeSrc))
        <div class="beforeafter-before" style="position:absolute;inset:0;overflow:hidden;width:{{ (int)$initialPosition }}%;">
            <img class="img-filtered" src="{{ e($beforeSrc) }}" alt="{{ e($beforeLabel) }}" loading="lazy" style="display:block;width:100%;height:100%;object-fit:cover;{{ $__imageFilter }}">
        </div>
    @endif
    <input
        type="range"
        min="0"
        max="100"
        value="{{ (int)$initialPosition }}"
        aria-label="Comparison slider"
        style="position:absolute;bottom:0;left:0;width:100%;margin:0;opacity:0.6;cursor:pointer;z-index:2;"
        oninput="this.parentElement.querySelector('.beforeafter-before').style.width=this.value+'%'"
    >
    <span style="position:absolute;top:0.5rem;left:0.5rem;background:rgba(0,0,0,0.5);color:var(--color-text-inverse,#fff);font-size:0.75rem;padding:0.25rem 0.5rem;border-radius:4px;">{{ e($beforeLabel) }}</span>
    <span style="position:absolute;top:0.5rem;right:0.5rem;background:rgba(0,0,0,0.5);color:var(--color-text-inverse,#fff);font-size:0.75rem;padding:0.25rem 0.5rem;border-radius:4px;">{{ e($afterLabel) }}</span>
</div>

</div>