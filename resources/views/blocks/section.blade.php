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
<div class="section-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    // Padding: new px fields take priority, legacy preset as fallback
    $legacyPadding = ['none' => '0', 'sm' => '1rem', 'md' => '2rem', 'lg' => '3rem', 'xl' => '4rem'];
    $legacyPreset = $data['padding'] ?? null;
    $padTop = $data['padding_top'] ?? ($legacyPreset ? ($legacyPadding[$legacyPreset] ?? '2rem') : '2rem');
    $padBottom = $data['padding_bottom'] ?? ($legacyPreset ? ($legacyPadding[$legacyPreset] ?? '2rem') : '2rem');
    $maxW = $data['max_width'] ?? '1200px';
    $id = $data['anchor_id'] ?? '';

    // Background system
    $bgType = $data['bg_type'] ?? 'none';
    $style = "padding-top: {$padTop}; padding-bottom: {$padBottom}; position: relative;";

    // Legacy support (old bg fields)
    $legacyBg = $data['background_color'] ?? '';
    $legacyBgImg = $data['background_image'] ?? '';

    if ($bgType === 'color' && !empty($data['bg_color'])) {
        $style .= " background-color: {$data['bg_color']};";
    } elseif ($bgType === 'gradient' && !empty($data['bg_gradient_stops'])) {
        $stops = collect($data['bg_gradient_stops'])->map(fn($s) => "{$s['color']} {$s['position']}%")->join(', ');
        $type = $data['bg_gradient_type'] ?? 'linear';
        $angle = $data['bg_gradient_angle'] ?? 180;
        $gradient = $type === 'radial' ? "radial-gradient(circle, {$stops})" : "linear-gradient({$angle}deg, {$stops})";
        $style .= " background: {$gradient};";
    } elseif ($bgType === 'image' && !empty($data['bg_image'])) {
        $size = $data['bg_image_size'] ?? 'cover';
        $pos = $data['bg_image_position'] ?? 'center center';
        $repeat = $data['bg_image_repeat'] ?? 'no-repeat';
        $scroll = $data['bg_scroll_effect'] ?? 'none';
        $style .= " background-image: url('{$data['bg_image']}'); background-size: {$size}; background-position: {$pos}; background-repeat: {$repeat};";
        if ($scroll === 'fixed') $style .= " background-attachment: fixed;";
    } elseif ($legacyBg) {
        $style .= " background-color: {$legacyBg};";
        if ($legacyBgImg) $style .= " background-image: url('{$legacyBgImg}'); background-size: cover; background-position: center;";
    }

    // Overlay
    $overlayOpacity = (float) ($data['bg_overlay_opacity'] ?? 0);
    $overlayColor = $data['bg_overlay_color'] ?? '#000';
@endphp
<section class="section-block"@if($id) id="{{ $id }}"@endif style="{{ $style }}">
    @if($bgType === 'image' && $overlayOpacity > 0)
    <div style="position:absolute;inset:0;background-color:{{ $overlayColor }};opacity:{{ $overlayOpacity }};pointer-events:none;z-index:0;"></div>
    @endif
    <div style="max-width: {{ $maxW }}; margin: 0 auto; position: relative; z-index: 1;">
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

</div>