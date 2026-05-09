@php
    // Sanitize CSS values to prevent style injection
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw|auto|0)$/i', trim((string) $v)) ? trim((string) $v) : '';
    $cssUrl = fn($v) => preg_match('#^(https?://|/)[^\'"<>]*$#i', (string) $v) ? (string) $v : '';
    // Sanitize href values: block javascript:, data:, vbscript: schemes
    $safeUrl = fn($v) => preg_match('/^(javascript|data|vbscript):/i', trim((string) $v)) ? '#' : (string) $v;

    // ── Background (block-specific, from block.data) ──
    $bgType = $data['bg_type'] ?? 'none';
    $hasBg = $bgType !== 'none' || !empty($data['backgroundImage']);
    $textColor = $hasBg ? '#fff' : '#333';
    $baseBg = $hasBg ? '' : 'background-color:#f5f5f5;';
    $style = "position:relative;min-height:400px;display:flex;align-items:center;justify-content:center;color:{$textColor};{$baseBg}";

    if ($bgType === 'color' && !empty($data['bg_color'])) {
        $style .= "background-color:{$cssVal($data['bg_color'])};";
    } elseif ($bgType === 'gradient' && !empty($data['bg_gradient_stops'])) {
        $stops = collect($data['bg_gradient_stops'])->map(fn($s) => $cssVal($s['color']) . ' ' . ((int) $s['position']) . '%')->join(', ');
        $type = in_array($data['bg_gradient_type'] ?? 'linear', ['linear', 'radial']) ? ($data['bg_gradient_type'] ?? 'linear') : 'linear';
        $angle = (int) ($data['bg_gradient_angle'] ?? 180);
        $gradient = $type === 'radial' ? "radial-gradient(circle, {$stops})" : "linear-gradient({$angle}deg, {$stops})";
        $style .= "background:{$gradient};";
    } elseif ($bgType === 'image' && !empty($data['bg_image'])) {
        $imgUrl = $cssUrl($data['bg_image']);
        $size = in_array($data['bg_image_size'] ?? 'cover', ['cover', 'contain', 'auto']) ? ($data['bg_image_size'] ?? 'cover') : 'cover';
        $pos = $cssVal($data['bg_image_position'] ?? 'center center');
        $scroll = $data['bg_scroll_effect'] ?? 'none';
        $repeat = in_array($data['bg_image_repeat'] ?? 'no-repeat', ['no-repeat', 'repeat', 'repeat-x', 'repeat-y']) ? ($data['bg_image_repeat'] ?? 'no-repeat') : 'no-repeat';
        if ($imgUrl) {
            $style .= "background-image:url('{$imgUrl}');background-size:{$size};background-position:{$pos};background-repeat:{$repeat};";
            if ($scroll === 'fixed') $style .= "background-attachment:fixed;";
        }
    } elseif (!empty($data['backgroundImage'])) {
        $legacyUrl = $cssUrl($data['backgroundImage']);
        if ($legacyUrl) {
            $style .= "background-image:url('{$legacyUrl}');background-size:cover;background-position:center;";
        }
    }

    $overlayOpacity = max(0, min(1, (float) ($data['bg_overlay_opacity'] ?? 0)));
    $overlayColor = $cssVal($data['bg_overlay_color'] ?? '#000');

    // ── Shared properties (from block.style/animation/advanced via BuildPageService) ──
    $bs = $blockStyle ?? [];
    $ba = $blockAnimation ?? [];
    $badv = $blockAdvanced ?? [];

    // Spacing
    $sp = $bs['spacing'] ?? [];
    foreach (['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft'] as $prop) {
        $v = $cssDim($sp[$prop] ?? '');
        if ($v) {
            $kebab = strtolower(preg_replace('/[A-Z]/', '-$0', $prop));
            $style .= "{$kebab}:{$v};";
        }
    }

    // Border
    $vis = $bs['visual'] ?? [];
    if (!empty($vis['borderWidth']) && !empty($vis['borderColor'])) {
        $bw = $cssDim($vis['borderWidth']);
        $bc = $cssVal($vis['borderColor']);
        $bst = in_array($vis['borderStyle'] ?? 'solid', ['solid', 'dashed', 'dotted']) ? ($vis['borderStyle'] ?? 'solid') : 'solid';
        if ($bw && $bc) $style .= "border:{$bw} {$bst} {$bc};";
    }
    if (!empty($vis['borderRadius'])) {
        $br = $cssDim($vis['borderRadius']);
        if ($br) $style .= "border-radius:{$br};overflow:hidden;";
    }

    // Shadow
    $shadowMap = ['sm' => '0 1px 2px rgba(0,0,0,0.04)', 'md' => '0 4px 12px rgba(0,0,0,0.06)', 'lg' => '0 12px 32px rgba(0,0,0,0.10)'];
    if (!empty($vis['boxShadow']) && isset($shadowMap[$vis['boxShadow']])) {
        $style .= "box-shadow:{$shadowMap[$vis['boxShadow']]};";
    }

    // Opacity
    if (isset($vis['opacity']) && (float) $vis['opacity'] < 1) {
        $op = max(0, min(1, (float) $vis['opacity']));
        $style .= "opacity:{$op};";
    }

    // Animation
    $animEntrance = $ba['entrance'] ?? 'none';
    $animNames = ['fade' => 'block-fade', 'slide-up' => 'block-slide-up', 'slide-left' => 'block-slide-left', 'slide-right' => 'block-slide-right', 'zoom' => 'block-zoom'];
    $animStyle = '';
    $animAttr = '';
    if ($animEntrance !== 'none' && isset($animNames[$animEntrance])) {
        $dur = max(50, min(3000, (int) ($ba['duration'] ?? 400)));
        $del = max(0, min(5000, (int) ($ba['delay'] ?? 0)));
        $animStyle = "animation-name:{$animNames[$animEntrance]};animation-duration:{$dur}ms;animation-delay:{$del}ms;animation-fill-mode:both;";
        $animAttr = $animEntrance;
    }

    // Custom class
    $customClass = '';
    if (!empty($badv['customClass'])) {
        $customClass = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $badv['customClass']);
    }

    // HTML ID
    $htmlId = '';
    if (!empty($badv['htmlId'])) {
        $htmlId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $badv['htmlId']);
    }
@endphp
<section
    class="hero-section {{ $customClass }}"
    style="{{ $style }}{{ $animStyle }}"
    @if($htmlId) id="{{ $htmlId }}" @endif
    @if($animAttr) data-animation="{{ $animAttr }}" @endif
    @if($bgType === 'image' && !empty($data['alt']) && empty($badv['ariaLabel'])) role="img" aria-label="{{ $data['alt'] }}" @endif
    @if(!empty($badv['ariaLabel'])) role="img" aria-label="{{ $badv['ariaLabel'] }}" @endif
>
    @if($bgType === 'image' && $overlayOpacity > 0)
    <div style="position:absolute;inset:0;background-color:{{ $overlayColor }};opacity:{{ $overlayOpacity }};pointer-events:none;z-index:0;"></div>
    @endif
    <div class="hero-content" style="position:relative;z-index:1;text-align:center;max-width:800px;padding:2rem;">
        <h1 style="font-size:2.5rem;font-weight:700;margin-bottom:1rem;">{{ $data['title'] ?? '' }}</h1>
        @if(!empty($data['subtitle']))
            <p style="font-size:1.25rem;opacity:0.9;margin-bottom:2rem;">{{ $data['subtitle'] }}</p>
        @endif
        @if(!empty($data['ctaText']) && !empty($data['ctaUrl']))
            @php $ctaBg = $hasBg ? 'background:rgba(255,255,255,0.2);color:#fff;border:2px solid #fff;' : 'background:#333;color:#fff;border:2px solid #333;'; @endphp
            <a href="{{ $safeUrl($data['ctaUrl']) }}" style="display:inline-block;padding:0.75rem 2rem;{{ $ctaBg }}border-radius:0.375rem;text-decoration:none;font-weight:600;">{{ $data['ctaText'] }}</a>
        @endif
    </div>
</section>
