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

    // Section-specific layout
    $legacyPadding = ['none' => '0', 'sm' => '1rem', 'md' => '2rem', 'lg' => '3rem', 'xl' => '4rem'];
    $legacyPreset = $data['padding'] ?? null;
    $padTop = $data['padding_top'] ?? ($legacyPreset ? ($legacyPadding[$legacyPreset] ?? '2rem') : '2rem');
    $padBottom = $data['padding_bottom'] ?? ($legacyPreset ? ($legacyPadding[$legacyPreset] ?? '2rem') : '2rem');
    $maxW = $data['max_width'] ?? '1200px';
    $id = $data['anchor_id'] ?? '';

    // Legacy bg support (for blocks without bg_type set)
    $bgType = $data['bg_type'] ?? 'none';
    $legacyStyle = '';
    if ($bgType === 'none') {
        $legacyBg = $data['background_color'] ?? '';
        $legacyBgImg = $data['background_image'] ?? '';
        if ($legacyBg) $legacyStyle .= "background-color:{$legacyBg};";
        if ($legacyBgImg) $legacyStyle .= "background-image:url('{$legacyBgImg}');background-size:cover;background-position:center;";
    }
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<section class="section-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" @if($id) id="{{ $id }}" @elseif($__htmlId) id="{{ $__htmlId }}" @endif style="padding-top:{{ $padTop }};padding-bottom:{{ $padBottom }};position:relative;{{ $__sharedStyle }}{{ $legacyStyle }}" @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
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