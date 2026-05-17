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
<div class="readingprogress-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $style = $data['style'] ?? 'top-bar';
    $color = $data['color'] ?: '#3b82f6';
    $height = $data['height'] ?? '3px';
@endphp
@if($style === 'top-bar')
<div class="reading-progress reading-progress--top-bar" style="position: fixed; top: 0; left: 0; width: 100%; height: {{ e($height) }}; z-index: 9999; pointer-events: none;">
    <div class="reading-progress__bar" style="height: 100%; width: 0%; background-color: {{ e($color) }}; animation: readingProgress linear; animation-timeline: scroll(); will-change: width;"></div>
</div>
<style>
    @keyframes readingProgress { from { width: 0%; } to { width: 100%; } }
    @supports not (animation-timeline: scroll()) {
        .reading-progress__bar {
            animation: none !important;
            transition: width 0.1s linear;
        }
    }
</style>
@if(!isset($__readingProgressScriptLoaded))
@php $__readingProgressScriptLoaded = true; @endphp
<script>
if (!CSS.supports('animation-timeline', 'scroll()')) {
    window.addEventListener('scroll', function() {
        var bar = document.querySelector('.reading-progress__bar');
        if (!bar) return;
        var h = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.width = h > 0 ? (window.scrollY / h * 100) + '%' : '0%';
    }, { passive: true });
}
</script>
@endif
@elseif($style === 'circular')
<div class="reading-progress reading-progress--circular" style="position: fixed; bottom: 2rem; right: 2rem; z-index: 9999; width: 48px; height: 48px;">
    <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg);">
        <circle cx="18" cy="18" r="16" fill="none" stroke="#e5e7eb" stroke-width="2"/>
        <circle class="reading-progress__circle" cx="18" cy="18" r="16" fill="none" stroke="{{ e($color) }}" stroke-width="2" stroke-dasharray="100.53" stroke-dashoffset="100.53" style="animation: readingCircle linear; animation-timeline: scroll();"/>
    </svg>
</div>
<style>
    @keyframes readingCircle { from { stroke-dashoffset: 100.53; } to { stroke-dashoffset: 0; } }
    @supports not (animation-timeline: scroll()) {
        .reading-progress__circle { animation: none !important; }
    }
</style>
@if(!isset($__readingProgressScriptLoaded))
@php $__readingProgressScriptLoaded = true; @endphp
<script>
if (!CSS.supports('animation-timeline', 'scroll()')) {
    window.addEventListener('scroll', function() {
        var circle = document.querySelector('.reading-progress__circle');
        if (!circle) return;
        var h = document.documentElement.scrollHeight - window.innerHeight;
        var pct = h > 0 ? window.scrollY / h : 0;
        circle.style.strokeDashoffset = (100.53 * (1 - pct));
    }, { passive: true });
}
</script>
@endif
@elseif($style === 'side-bar')
<div class="reading-progress reading-progress--side-bar" style="position: fixed; top: 0; right: 0; width: {{ e($height) }}; height: 100vh; z-index: 9999; pointer-events: none;">
    <div class="reading-progress__bar" style="width: 100%; height: 0%; background-color: {{ e($color) }}; animation: readingSidebar linear; animation-timeline: scroll(); will-change: height;"></div>
</div>
<style>
    @keyframes readingSidebar { from { height: 0%; } to { height: 100%; } }
    @supports not (animation-timeline: scroll()) {
        .reading-progress--side-bar .reading-progress__bar { animation: none !important; transition: height 0.1s linear; }
    }
</style>
@if(!isset($__readingProgressScriptLoaded))
@php $__readingProgressScriptLoaded = true; @endphp
<script>
if (!CSS.supports('animation-timeline', 'scroll()')) {
    window.addEventListener('scroll', function() {
        var bar = document.querySelector('.reading-progress--side-bar .reading-progress__bar');
        if (!bar) return;
        var h = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.height = h > 0 ? (window.scrollY / h * 100) + '%' : '0%';
    }, { passive: true });
}
</script>
@endif
@endif

</div>