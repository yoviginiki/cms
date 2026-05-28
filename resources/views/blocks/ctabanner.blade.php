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
<div class="ctabanner-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $heading = $data['heading'] ?? 'Ready to get started?';
    $text = $data['text'] ?? '';
    $buttonText = $data['buttonText'] ?? 'Get started';
    $buttonUrl = $data['buttonUrl'] ?? '#';
    $bgStyle = $data['backgroundStyle'] ?? 'solid';
    $bgColor = $data['backgroundColor'] ?? '#3b82f6';
    $bgImage = $data['backgroundImage'] ?? '';

    $inlineStyle = match($bgStyle) {
        'gradient' => "background: linear-gradient(135deg, {$bgColor}, {$bgColor}cc);",
        'image' => "background-image: url('" . e($bgImage) . "'); background-size: cover; background-position: center;",
        default => "background-color: {$bgColor};",
    };
@endphp
<div class="ctabanner-block" style="{{ $inlineStyle }} padding: 3rem 1.5rem; text-align: center; color:var(--color-text-inverse,#fff); margin-bottom: var(--space-6, 1.5rem);">
    <div style="max-width: 640px; margin: 0 auto;">
        @php
            $tsShadowPresets = ['sm' => '0 1px 2px rgba(0,0,0,0.15)', 'md' => '0 2px 4px rgba(0,0,0,0.25)', 'lg' => '0 4px 8px rgba(0,0,0,0.4)', 'outline' => '-1px -1px 0 rgba(0,0,0,0.3),1px -1px 0 rgba(0,0,0,0.3),-1px 1px 0 rgba(0,0,0,0.3),1px 1px 0 rgba(0,0,0,0.3)', 'glow' => '0 0 10px rgba(255,255,255,0.8),0 0 20px rgba(255,255,255,0.4)'];
            $headingTextShadow = $tsShadowPresets[$data['headingTextShadow'] ?? ''] ?? '';
        @endphp
        <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.75rem;@if($headingTextShadow) text-shadow:{{ $headingTextShadow }};@endif">{{ e($heading) }}</h2>
        @if($text)
            <p style="font-size: 1rem; opacity: 0.9; margin-bottom: 1.5rem;">{{ e($text) }}</p>
        @endif
        <a href="{{ e($buttonUrl) }}" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); color:var(--color-text-inverse,#fff); padding: 0.75rem 2rem; border-radius:var(--border-radius-md,0.5rem); text-decoration: none; font-weight: 600;">
            {{ e($buttonText) }}
        </a>
    </div>
</div>

</div>