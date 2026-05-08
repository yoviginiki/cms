@php
    // Sanitize CSS values to prevent style injection
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssUrl = fn($v) => preg_match('#^(https?://|/)[^\'"<>]*$#i', (string) $v) ? (string) $v : '';
    // Sanitize href values: block javascript:, data:, vbscript: schemes
    $safeUrl = fn($v) => preg_match('/^(javascript|data|vbscript):/i', trim((string) $v)) ? '#' : (string) $v;

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
@endphp
<section class="hero-section" style="{{ $style }}"@if($bgType === 'image' && !empty($data['alt'])) role="img" aria-label="{{ $data['alt'] }}"@endif>
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
