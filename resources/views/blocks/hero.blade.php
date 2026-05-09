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

    // Section height
    $sectionHeightMap = ['auto' => 'auto', 'sm' => '300px', 'md' => '400px', 'lg' => '600px', 'fullscreen' => '100vh'];
    $sectionHeightVal = $data['sectionHeight'] ?? 'md';
    $minHeight = $sectionHeightMap[$sectionHeightVal] ?? '400px';

    // Vertical position
    $vertMap = ['top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end'];
    $vertAlign = $vertMap[$data['verticalPosition'] ?? 'center'] ?? 'center';

    // Text alignment
    $textAlign = in_array($data['textAlignment'] ?? 'center', ['left', 'center', 'right']) ? ($data['textAlignment'] ?? 'center') : 'center';

    // Content max width
    $maxWidth = $cssDim($data['contentMaxWidth'] ?? '800px') ?: '800px';

    // Heading tag
    $headlineTag = in_array($data['headlineTag'] ?? 'h1', ['h1', 'h2', 'h3']) ? ($data['headlineTag'] ?? 'h1') : 'h1';

    // Headline size and weight
    $headlineSize = $cssDim($data['headlineSize'] ?? '2.5rem') ?: '2.5rem';
    $headlineWeight = in_array((string)($data['headlineWeight'] ?? '700'), ['400','500','600','700','800','900']) ? ($data['headlineWeight'] ?? '700') : '700';

    // Headline color
    $adaptiveTextColor = ($data['adaptiveTextColor'] ?? true) !== false;
    $headlineColor = '';
    if (!empty($data['headlineColor'])) {
        $headlineColor = $cssVal($data['headlineColor']);
    }
    if (!$headlineColor && $adaptiveTextColor) {
        $headlineColor = $hasBg ? '#fff' : '#333';
    }

    // Subheadline size
    $subSize = $cssDim($data['subheadlineSize'] ?? '1.25rem') ?: '1.25rem';

    $style = "position:relative;min-height:{$minHeight};display:flex;align-items:{$vertAlign};justify-content:center;color:{$textColor};{$baseBg}";

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

    // Responsive hideOn
    $bResp = $blockResponsive ?? [];
    $hideOn = $bResp['hideOn'] ?? [];
    $hideStyle = '';
    $scopeClass = '';
    if (!empty($hideOn)) {
        $scopeClass = 'blk-' . substr(md5($htmlId ?: uniqid('', true)), 0, 8);
        if (in_array('desktop', $hideOn)) {
            $hideStyle .= '@media(min-width:1025px){.' . $scopeClass . '{display:none!important}}';
        }
        if (in_array('tablet', $hideOn)) {
            $hideStyle .= '@media(min-width:769px) and (max-width:1024px){.' . $scopeClass . '{display:none!important}}';
        }
        if (in_array('mobile', $hideOn)) {
            $hideStyle .= '@media(max-width:768px){.' . $scopeClass . '{display:none!important}}';
        }
    }
@endphp
@if($hideStyle)
<style>{{ $hideStyle }}</style>
@endif
<section
    class="hero-section {{ $customClass }} {{ $scopeClass }}"
    style="{{ $style }}{{ $animStyle }}"
    @if($htmlId) id="{{ $htmlId }}" @endif
    @if($animAttr) data-animation="{{ $animAttr }}" @endif
    @if($bgType === 'image' && !empty($data['alt']) && empty($badv['ariaLabel'])) role="img" aria-label="{{ $data['alt'] }}" @endif
    @if(!empty($badv['ariaLabel'])) role="img" aria-label="{{ $badv['ariaLabel'] }}" @endif
>
    @if($bgType === 'image' && $overlayOpacity > 0)
    <div style="position:absolute;inset:0;background-color:{{ $overlayColor }};opacity:{{ $overlayOpacity }};pointer-events:none;z-index:0;"></div>
    @endif
    {{-- Media loading: bg_type=image uses CSS background; loading/fetchpriority attrs reserved for future <picture> element --}}
    <div class="hero-content" style="position:relative;z-index:1;text-align:{{ $textAlign }};max-width:{{ $maxWidth }};padding:2rem;">
        <{{ $headlineTag }} style="font-size:{{ $headlineSize }};font-weight:{{ $headlineWeight }};margin-bottom:1rem;@if($headlineColor)color:{{ $headlineColor }};@endif">{{ $data['title'] ?? '' }}</{{ $headlineTag }}>
        @if(!empty($data['subtitle']))
            <p style="font-size:{{ $subSize }};opacity:0.9;margin-bottom:2rem;">{{ $data['subtitle'] }}</p>
        @endif
        @if(!empty($data['ctaText']) && !empty($data['ctaUrl']))
            @php $ctaBg = $hasBg ? 'background:rgba(255,255,255,0.2);color:#fff;border:2px solid #fff;' : 'background:#333;color:#fff;border:2px solid #333;'; @endphp
            <a href="{{ $safeUrl($data['ctaUrl']) }}" style="display:inline-block;padding:0.75rem 2rem;{{ $ctaBg }}border-radius:0.375rem;text-decoration:none;font-weight:600;">{{ $data['ctaText'] }}</a>
        @endif
    </div>
</section>
