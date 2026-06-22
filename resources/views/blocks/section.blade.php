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

    // Section-specific layout
    $legacyPadding = ['none' => '0', 'sm' => '1rem', 'md' => '2rem', 'lg' => '3rem', 'xl' => '4rem'];
    $legacyPreset = $data['padding'] ?? null;
    $padTop = $data['padding_top'] ?? ($legacyPreset ? ($legacyPadding[$legacyPreset] ?? '2rem') : '2rem');
    $padBottom = $data['padding_bottom'] ?? ($legacyPreset ? ($legacyPadding[$legacyPreset] ?? '2rem') : '2rem');
    $maxW = $data['max_width'] ?? '1200px';
    $id = $data['anchor_id'] ?? '';

    // Legacy bg support (only when NO new bg_* fields are present)
    $hasNewBg = (!empty($data['bg_type']) && $data['bg_type'] !== 'none')
        || !empty($data['bg_color'])
        || !empty($data['bg_image'])
        || !empty($data['bg_gradient_stops']);
    $legacyStyle = '';
    if (!$hasNewBg) {
        $legacyBg = $data['background_color'] ?? '';
        $legacyBgImg = $data['background_image'] ?? '';
        if ($legacyBg) $legacyStyle .= "background-color:{$legacyBg};";
        if ($legacyBgImg) $legacyStyle .= "background-image:url('{$legacyBgImg}');background-size:cover;background-position:center;";
    }
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
<section class="section-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" @if($id) id="{{ $id }}" @elseif($__htmlId) id="{{ $__htmlId }}" @endif style="padding-top:{{ $padTop }};padding-bottom:{{ $padBottom }};position:relative;{{ $__sharedStyle }}{{ $legacyStyle }}" @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif @if(!empty($data['experienceTransition'])) data-experience-transition="{{ $data['experienceTransition'] }}" @endif @if(!empty($data['experienceEnter'])) data-experience-enter="{{ $data['experienceEnter'] }}" @endif @if(!empty($data['experiencePin'])) data-experience-pin="true" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
    <div style="max-width:{{ $maxW }};margin:0 auto;position:relative;z-index:1;">
        {!! $children !!}
    </div>
    @if(($data['bg_scroll_effect'] ?? 'none') === 'parallax')
    <script>
    (function(){
        var el = document.currentScript.closest('.section-block');
        if (!el) return;
        var speed = {{ $data['bg_parallax_speed'] ?? 0.5 }};
        window.addEventListener('scroll', function() {
            var rect = el.getBoundingClientRect();
            var offset = rect.top * speed;
            el.style.backgroundPositionY = offset + 'px';
        }, { passive: true });
    })();
    </script>
    @endif
    @if(($data['bg_scroll_effect'] ?? 'none') === 'zoom')
    <script>
    (function(){
        var el = document.currentScript.closest('.section-block');
        if (!el) return;
        window.addEventListener('scroll', function() {
            var rect = el.getBoundingClientRect();
            var vh = window.innerHeight;
            var progress = Math.max(0, Math.min(1, 1 - rect.top / vh));
            el.style.backgroundSize = (100 + progress * 20) + '%';
        }, { passive: true });
    })();
    </script>
    @endif
</section>