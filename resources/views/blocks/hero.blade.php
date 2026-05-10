@use('App\Support\Blocks\BlockStyle')
@php
    // Sanitize CSS values to prevent style injection
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw|auto|0)$/i', trim((string) $v)) ? trim((string) $v) : '';
    $cssUrl = fn($v) => preg_match('#^(https?://|/|\.\.?/)[^\'"<>]*$#i', (string) $v) ? (string) $v : '';
    // Sanitize href values: block javascript:, data:, vbscript: schemes
    // Strip control chars and whitespace before checking to prevent obfuscation (e.g. "java script:")
    $safeUrl = fn($v) => preg_match('/^(javascript|data|vbscript)\s*:/i', preg_replace('/[\x00-\x1f\x7f\s]/', '', (string) $v)) ? '#' : (string) $v;

    // ── Background (block-specific, from block.data) ──
    $bgType = $data['bg_type'] ?? 'none';
    // hasBg must check actual usable values, not just bg_type
    $hasBg = match($bgType) {
        'color'    => !empty($data['bg_color']),
        'gradient' => is_array($data['bg_gradient_stops'] ?? null) && !empty($data['bg_gradient_stops']),
        'image'    => !empty($data['bg_image']),
        default    => false,
    } || !empty($data['backgroundImage']); // legacy fallback
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
    } elseif ($bgType === 'gradient' && is_array($data['bg_gradient_stops'] ?? null) && !empty($data['bg_gradient_stops'])) {
        $stops = collect($data['bg_gradient_stops'])
            ->map(fn($s) => ['color' => $cssVal($s['color'] ?? ''), 'position' => (int) ($s['position'] ?? 0)])
            ->filter(fn($s) => $s['color'] !== '')
            ->map(fn($s) => $s['color'] . ' ' . $s['position'] . '%')
            ->join(', ');
        if ($stops) {
            $type = in_array($data['bg_gradient_type'] ?? 'linear', ['linear', 'radial']) ? ($data['bg_gradient_type'] ?? 'linear') : 'linear';
            $angle = (int) ($data['bg_gradient_angle'] ?? 180);
            $gradient = $type === 'radial' ? "radial-gradient(circle, {$stops})" : "linear-gradient({$angle}deg, {$stops})";
            $style .= "background:{$gradient};";
        }
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

    // ── Shared properties (via BlockStyle helper) ──
    $bs = $blockStyle ?? [];
    $ba = $blockAnimation ?? [];
    $badv = $blockAdvanced ?? [];
    $bResp = $blockResponsive ?? [];

    $sharedStyle = BlockStyle::buildStyle($bs, $ba);
    $customClass = BlockStyle::safeClass($badv['customClass'] ?? '');
    $htmlId = BlockStyle::safeId($badv['htmlId'] ?? '');
    $animAttr = BlockStyle::animationAttr($ba);
    $hideOn = BlockStyle::buildHideOnCss($bResp, $htmlId);
@endphp
@if($hideOn['css'])
<style>{{ $hideOn['css'] }}</style>
@endif
<section
    class="hero-section {{ $customClass }} {{ $hideOn['scopeClass'] }}"
    style="{{ $style }}{{ $sharedStyle ? ";{$sharedStyle}" : '' }}"
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
            @php
                // CTA style properties (all optional, backward compatible)
                $ctaVariant = in_array($data['ctaVariant'] ?? 'filled', ['filled','outline','ghost','link']) ? ($data['ctaVariant'] ?? 'filled') : 'filled';
                $ctaSize = in_array($data['ctaSize'] ?? 'md', ['sm','md','lg']) ? ($data['ctaSize'] ?? 'md') : 'md';
                $ctaAlignVal = in_array($data['ctaAlign'] ?? '', ['left','center','right','']) ? ($data['ctaAlign'] ?? '') : '';
                $ctaBgColorVal = $cssVal($data['ctaBgColor'] ?? '');
                $ctaTextColorVal = $cssVal($data['ctaTextColor'] ?? '');
                $ctaBorderColorVal = $cssVal($data['ctaBorderColor'] ?? '');
                $ctaBorderWidthVal = $cssDim($data['ctaBorderWidth'] ?? '');
                $ctaBorderRadiusVal = $cssDim($data['ctaBorderRadius'] ?? '');

                // Size → padding
                $ctaSizeMap = ['sm' => '0.375rem 1rem', 'md' => '0.75rem 2rem', 'lg' => '1rem 2.5rem'];
                $ctaPadding = $ctaSizeMap[$ctaSize] ?? $ctaSizeMap['md'];

                // Size → font-size
                $ctaFontMap = ['sm' => '0.75rem', 'md' => '0.875rem', 'lg' => '1rem'];
                $ctaFontSize = $ctaFontMap[$ctaSize] ?? $ctaFontMap['md'];

                // Build CTA inline style
                $ctaStyle = "display:inline-block;padding:{$ctaPadding};font-size:{$ctaFontSize};font-weight:600;text-decoration:none;";

                // Border radius
                $ctaStyle .= 'border-radius:' . ($ctaBorderRadiusVal ?: '0.375rem') . ';';

                // Variant-specific defaults
                if ($ctaVariant === 'outline') {
                    $ctaStyle .= 'background:' . ($ctaBgColorVal ?: 'transparent') . ';';
                    $ctaStyle .= 'color:' . ($ctaTextColorVal ?: ($hasBg ? '#fff' : '#333')) . ';';
                    $ctaStyle .= 'border:' . ($ctaBorderWidthVal ?: '2px') . ' solid ' . ($ctaBorderColorVal ?: ($hasBg ? '#fff' : '#333')) . ';';
                } elseif ($ctaVariant === 'ghost') {
                    $ctaStyle .= 'background:' . ($ctaBgColorVal ?: 'transparent') . ';';
                    $ctaStyle .= 'color:' . ($ctaTextColorVal ?: ($hasBg ? '#fff' : '#333')) . ';';
                    $ctaStyle .= 'border:none;';
                } elseif ($ctaVariant === 'link') {
                    $ctaStyle .= 'background:transparent;';
                    $ctaStyle .= 'color:' . ($ctaTextColorVal ?: ($hasBg ? '#fff' : '#333')) . ';';
                    $ctaStyle .= 'border:none;text-decoration:underline;padding:0;';
                } else {
                    // filled (default + backward compatible)
                    $defaultBg = $hasBg ? 'rgba(255,255,255,0.2)' : '#333';
                    $defaultColor = '#fff';
                    $defaultBorder = $hasBg ? '#fff' : '#333';
                    $ctaStyle .= 'background:' . ($ctaBgColorVal ?: $defaultBg) . ';';
                    $ctaStyle .= 'color:' . ($ctaTextColorVal ?: $defaultColor) . ';';
                    if ($ctaBorderWidthVal || $ctaBorderColorVal) {
                        $ctaStyle .= 'border:' . ($ctaBorderWidthVal ?: '2px') . ' solid ' . ($ctaBorderColorVal ?: $defaultBorder) . ';';
                    } else {
                        $ctaStyle .= 'border:2px solid ' . $defaultBorder . ';';
                    }
                }

                // CTA alignment wrapper
                $ctaWrapAlign = $ctaAlignVal ?: $textAlign;
            @endphp
            <div style="text-align:{{ $ctaWrapAlign }};">
                <a href="{{ $safeUrl($data['ctaUrl']) }}" style="{{ $ctaStyle }}">{{ $data['ctaText'] }}</a>
            </div>
        @endif
    </div>
</section>
