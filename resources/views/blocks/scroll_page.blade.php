@php
    $d = $data;
    $uid = 'sl-' . substr(md5($data['pages'][0]['id'] ?? uniqid()), 0, 8);
    $pal = $d['palette'] ?? [];
    $typo = $d['typography'] ?? [];
    $lay = $d['layout'] ?? [];
    $back = $d['backdrop'] ?? [];
    $me = $d['mouseEffect'] ?? [];
    $rev = $d['reveal'] ?? [];
    $resp = $d['responsive'] ?? [];
    $hint = $d['scrollHint'] ?? [];
    $mouseEnabled = ($me['enabled'] ?? false);
    $preset = $mouseEnabled ? ($me['preset'] ?? 'just-clouds') : 'none';
    $imageEnabled = ($back['image']['enabled'] ?? false) && !empty($back['image']['url']);
    if ($preset === 'water-ink' && !$imageEnabled) $preset = 'just-water';
    $cursor = $me['cursor'] ?? [];
    $cursorShape = $mouseEnabled ? ($cursor['shape'] ?? 'none') : 'none';

    // Water-ink tuning
    $wi = $me['water-ink'] ?? [];
    $wiScale = $wi['displacementScale'] ?? 50;
    $wiFreq = $wi['turbulenceFreq'] ?? 0.014;
    $wiOctaves = $wi['turbulenceOctaves'] ?? 2;
    $overlayOpacity = $back['image']['overlayOpacity'] ?? 0.55;
    $imageBlur = $back['image']['baseBlur'] ?? '6px';
    $imageSaturate = $back['image']['baseSaturate'] ?? 0.9;
@endphp
{{-- Google Fonts --}}
@if(!empty($typo['googleFontsUrl']))
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="{{ $typo['googleFontsUrl'] }}" rel="stylesheet">
@endif

<style>
#{{ $uid }} {
    --sl-paper: {{ $pal['paper'] ?? '#EFE7D5' }};
    --sl-paper-deep: {{ $pal['paperDeep'] ?? '#E6DCC6' }};
    --sl-ink: {{ $pal['ink'] ?? '#2A2117' }};
    --sl-ink-soft: {{ $pal['inkSoft'] ?? '#4A3F32' }};
    --sl-rust: {{ $pal['rust'] ?? '#9B5A3E' }};
    --sl-ochre: {{ $pal['ochre'] ?? '#C8A97E' }};
    --sl-terracotta: {{ $pal['terracotta'] ?? '#C4846A' }};
    --sl-sage: {{ $pal['sage'] ?? '#9DA58F' }};
    --sl-umber: {{ $pal['umber'] ?? '#7A5E47' }};
    --sl-font-display: '{{ $typo['fontDisplay'] ?? 'Fraunces' }}', {{ $typo['fontDisplayFallback'] ?? 'Georgia, serif' }};
    --sl-font-body: '{{ $typo['fontBody'] ?? 'EB Garamond' }}', {{ $typo['fontBodyFallback'] ?? 'Georgia, serif' }};
    --sl-max-reading: {{ $typo['maxReading'] ?? '36rem' }};
    --sl-max-wide: {{ $typo['maxWide'] ?? '52rem' }};
    --sl-base-font-size: {{ $typo['baseFontSize'] ?? '18px' }};
    --sl-body-line-height: {{ $typo['bodyLineHeight'] ?? 1.7 }};
    --sl-section-padding: {{ $lay['sectionPadding'] ?? '8rem 1.5rem' }};
    --sl-section-min-height: {{ $lay['sectionMinHeight'] ?? '100vh' }};
    --sl-section-tall-min-height: {{ $lay['tallSectionMinHeight'] ?? '120vh' }};
    --sl-mask-radius: {{ $me['radius'] ?? '340px' }};
    --sl-overlay-opacity: {{ $overlayOpacity }};
}
#{{ $uid }} { position:relative; background:var(--sl-paper); color:var(--sl-ink); font-family:var(--sl-font-body); font-size:var(--sl-base-font-size); line-height:var(--sl-body-line-height); overflow-x:hidden; -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility; }
@if($cursorShape !== 'none' && $cursorShape !== 'os-default' && $mouseEnabled)
#{{ $uid }} { cursor:none; }
@endif

/* Sections */
#{{ $uid }} .sl-section { min-height:var(--sl-section-min-height); display:flex; align-items:center; justify-content:center; padding:var(--sl-section-padding); text-align:{{ $lay['defaultTextAlign'] ?? 'center' }}; position:relative; z-index:5; }
#{{ $uid }} .sl-section.sl-tall { min-height:var(--sl-section-tall-min-height); }

/* Typography */
#{{ $uid }} .sl-masthead { font-family:var(--sl-font-display); font-size:clamp(3.5rem,9vw,7rem); font-weight:300; letter-spacing:0.35em; line-height:1; color:var(--sl-ink); padding-left:0.35em; }
#{{ $uid }} .sl-eyebrow { font-family:var(--sl-font-body); font-style:italic; font-size:0.85rem; letter-spacing:0.3em; text-transform:uppercase; color:var(--sl-ink-soft); }
#{{ $uid }} .sl-subtitle { font-family:var(--sl-font-display); font-size:clamp(1.4rem,3.5vw,2.2rem); font-style:italic; font-weight:300; color:var(--sl-ink); line-height:1.4; }
#{{ $uid }} .sl-hook { font-family:var(--sl-font-body); font-style:italic; font-size:1rem; color:var(--sl-ink-soft); letter-spacing:0.08em; }
#{{ $uid }} .sl-chapter-label { font-family:var(--sl-font-body); font-style:italic; font-size:0.9rem; letter-spacing:0.3em; text-transform:uppercase; color:var(--sl-ink-soft); }
#{{ $uid }} .sl-chapter-title { font-family:var(--sl-font-display); font-size:clamp(2.8rem,7vw,5rem); font-weight:300; line-height:1.15; color:var(--sl-ink); margin-top:0.5rem; letter-spacing:-0.01em; }
#{{ $uid }} .sl-body-text { font-family:var(--sl-font-body); font-size:1.25rem; line-height:1.75; color:var(--sl-ink); max-width:var(--sl-max-reading); margin:0 auto 1.8rem; }
#{{ $uid }} .sl-body-text.sl-lead { font-style:italic; font-size:1.4rem; color:var(--sl-ink); }
#{{ $uid }} .sl-pull-quote { font-family:var(--sl-font-display); font-weight:300; font-style:italic; font-size:clamp(1.8rem,4vw,2.8rem); line-height:1.4; color:var(--sl-ink); text-align:center; max-width:28rem; margin:0 auto; position:relative; padding:3rem 0; }
#{{ $uid }} .sl-pull-lines { position:relative; }
#{{ $uid }} .sl-pull-lines::before, #{{ $uid }} .sl-pull-lines::after { content:''; display:block; width:40px; height:1px; background:var(--sl-rust); opacity:0.5; margin:0 auto 2rem; }
#{{ $uid }} .sl-pull-lines::after { margin:2rem auto 0; }
#{{ $uid }} .sl-quote-line { font-family:var(--sl-font-display); font-size:clamp(1.8rem,4.5vw,3rem); font-weight:300; line-height:1.5; color:var(--sl-ink); }
#{{ $uid }} .sl-quote-line em, #{{ $uid }} .sl-em { font-style:italic; color:var(--sl-rust); }
#{{ $uid }} .sl-closing-line { font-family:var(--sl-font-display); font-size:clamp(1.6rem,3.5vw,2.4rem); font-style:italic; font-weight:300; color:var(--sl-ink); letter-spacing:0.01em; }
#{{ $uid }} .sl-mark { display:block; width:60px; height:1px; background:var(--sl-rust); opacity:0.5; margin:3rem auto; }
#{{ $uid }} .sl-divider { color:var(--sl-rust); opacity:0.6; font-size:1.5rem; letter-spacing:2em; padding-left:2em; }
#{{ $uid }} .sl-dot-mark::after { content:'· · ·'; display:block; margin-top:2.5rem; color:var(--sl-rust); font-size:1rem; opacity:0.6; }
#{{ $uid }} .sl-footer-section { padding:6rem 1.5rem 4rem; text-align:center; color:var(--sl-ink-soft); font-size:0.85rem; letter-spacing:0.08em; position:relative; z-index:5; }
#{{ $uid }} .sl-footer-meta { font-size:0.75rem; letter-spacing:0.2em; text-transform:uppercase; opacity:0.6; margin-top:2rem; }

/* Reveal */
@if($rev['enabled'] ?? true)
#{{ $uid }} .sl-reveal { opacity:0; transform:translateY({{ $rev['initialTranslateY'] ?? '30px' }}); transition:opacity {{ $rev['duration'] ?? '2.4s' }} {{ $rev['easing'] ?? 'cubic-bezier(0.16,1,0.3,1)' }}, transform {{ $rev['duration'] ?? '2.4s' }} {{ $rev['easing'] ?? 'cubic-bezier(0.16,1,0.3,1)' }}; }
#{{ $uid }} .sl-reveal.sl-visible { opacity:1; transform:translateY(0); }
/* First section visible immediately — ensures LCP and above-fold content is instant */
#{{ $uid }} .sl-section:first-child .sl-reveal { opacity:1; transform:translateY(0); }
@endif

/* Scroll hint */
#{{ $uid }} .sl-scroll-hint { position:absolute; bottom:3rem; left:50%; transform:translateX(-50%); font-family:var(--sl-font-body); font-style:italic; font-size:{{ $hint['fontSize'] ?? '0.8rem' }}; letter-spacing:{{ $hint['letterSpacing'] ?? '0.25em' }}; text-transform:uppercase; color:var(--sl-ink); animation:{{ $uid }}-breathe {{ $hint['breatheDuration'] ?? '4s' }} ease-in-out infinite; }
@keyframes {{ $uid }}-breathe { 0%,100%{opacity:0.35;transform:translateX(-50%) translateY(0)} 50%{opacity:0.7;transform:translateX(-50%) translateY(6px)} }

/* ── Background stage ── */
#{{ $uid }} .sl-bg-stage { position:fixed; inset:0; z-index:0; overflow:hidden; pointer-events:none; }
#{{ $uid }} .sl-bg-layer { position:absolute; inset:0; width:100%; height:100%; background-size:{{ $back['image']['fit'] ?? 'cover' }}; background-position:{{ $back['image']['position'] ?? 'center' }}; background-repeat:no-repeat; pointer-events:none; will-change:transform,filter; }
#{{ $uid }} .sl-bg-clean { filter:blur({{ $imageBlur }}) saturate({{ $imageSaturate }}); }
@if($preset === 'water-ink' && $imageEnabled)
#{{ $uid }} .sl-bg-distorted { filter:url(#{{ $uid }}-wc-distort) blur(1.5px) saturate(1.05); -webkit-mask-image:radial-gradient(circle var(--sl-mask-radius) at var(--sl-mx,50%) var(--sl-my,50%),rgba(0,0,0,1) 0%,rgba(0,0,0,0.85) 40%,rgba(0,0,0,0.3) 75%,transparent 100%); mask-image:radial-gradient(circle var(--sl-mask-radius) at var(--sl-mx,50%) var(--sl-my,50%),rgba(0,0,0,1) 0%,rgba(0,0,0,0.85) 40%,rgba(0,0,0,0.3) 75%,transparent 100%); }
#{{ $uid }} .sl-bg-overlay-masked { position:absolute; inset:0; background:var(--sl-paper); opacity:var(--sl-overlay-opacity); pointer-events:none; -webkit-mask-image:radial-gradient(circle var(--sl-mask-radius) at var(--sl-mx,50%) var(--sl-my,50%),rgba(0,0,0,0.45) 0%,rgba(0,0,0,0.7) 40%,rgba(0,0,0,0.92) 75%,rgba(0,0,0,1) 100%); mask-image:radial-gradient(circle var(--sl-mask-radius) at var(--sl-mx,50%) var(--sl-my,50%),rgba(0,0,0,0.45) 0%,rgba(0,0,0,0.7) 40%,rgba(0,0,0,0.92) 75%,rgba(0,0,0,1) 100%); }
@else
#{{ $uid }} .sl-bg-overlay-flat { position:absolute; inset:0; background:var(--sl-paper); opacity:var(--sl-overlay-opacity); pointer-events:none; }
@endif

/* Backdrop texture layers */
#{{ $uid }} .sl-grain { position:fixed; inset:0; z-index:2; pointer-events:none; mix-blend-mode:{{ $back['grain']['blendMode'] ?? 'multiply' }}; opacity:{{ $back['grain']['opacity'] ?? 0.25 }}; }
#{{ $uid }} .sl-grain svg { width:100%; height:100%; display:block; }
#{{ $uid }} .sl-vignette { position:fixed; inset:0; z-index:4; pointer-events:none; }
#{{ $uid }} .sl-blob-layer { position:fixed; inset:0; z-index:1; pointer-events:none; overflow:hidden; }

/* Cursor */
#{{ $uid }} .sl-cursor-circle { position:fixed; top:0; left:0; width:{{ $cursor['circleSize'] ?? '48px' }}; height:{{ $cursor['circleSize'] ?? '48px' }}; border:{{ $cursor['circleStrokeWidth'] ?? '1px' }} solid var(--sl-{{ $cursor['circleColor'] ?? 'rust' }}); border-radius:50%; opacity:0; pointer-events:none; mix-blend-mode:multiply; z-index:9999; transform:translate(-50%,-50%); transition:opacity {{ $cursor['fadeInMs'] ?? 300 }}ms ease; }
#{{ $uid }} .sl-cursor-dot { position:fixed; top:0; left:0; width:{{ $cursor['dotSize'] ?? '6px' }}; height:{{ $cursor['dotSize'] ?? '6px' }}; background:var(--sl-{{ $cursor['dotColor'] ?? 'rust' }}); border-radius:50%; opacity:0; pointer-events:none; z-index:9999; transform:translate(-50%,-50%); transition:opacity {{ $cursor['fadeInMs'] ?? 300 }}ms ease; }
#{{ $uid }}.sl-cursor-active .sl-cursor-circle { opacity:{{ $cursor['circleOpacity'] ?? 0.65 }}; }
#{{ $uid }}.sl-cursor-active .sl-cursor-dot { opacity:{{ $cursor['dotOpacity'] ?? 0.9 }}; }

/* Clouds lens */
@if($preset === 'just-clouds')
#{{ $uid }} .sl-clouds-lens { position:fixed; inset:0; pointer-events:none; z-index:3; mix-blend-mode:soft-light; animation:{{ $uid }}-clouds-pulse {{ $me['just-clouds']['pulseSeconds'] ?? 4 }}s ease-in-out infinite; }
@keyframes {{ $uid }}-clouds-pulse { 0%,100%{opacity:1} 50%{opacity:{{ 1 - ($me['just-clouds']['pulseAmplitude'] ?? 0.08) }}} }
@endif

/* Just-water — CSS mask ripple on paper */
@if($preset === 'just-water')
#{{ $uid }} .sl-water-ripple { position:fixed; inset:0; pointer-events:none; z-index:3; background:var(--sl-paper-deep); filter:url(#{{ $uid }}-water-distort); opacity:0.3; -webkit-mask-image:radial-gradient(circle var(--sl-mask-radius) at var(--sl-mx,50%) var(--sl-my,50%),rgba(0,0,0,0.8) 0%,rgba(0,0,0,0.4) 50%,transparent 100%); mask-image:radial-gradient(circle var(--sl-mask-radius) at var(--sl-mx,50%) var(--sl-my,50%),rgba(0,0,0,0.8) 0%,rgba(0,0,0,0.4) 50%,transparent 100%); }
@endif

/* Just-swirls — CSS mask swirl on paper */
@if($preset === 'just-swirls')
#{{ $uid }} .sl-swirl-layer { position:fixed; inset:0; pointer-events:none; z-index:3; background:var(--sl-paper-deep); filter:url(#{{ $uid }}-swirl-distort); mix-blend-mode:multiply; opacity:0.25; -webkit-mask-image:radial-gradient(circle var(--sl-mask-radius) at var(--sl-mx,50%) var(--sl-my,50%),rgba(0,0,0,0.9) 0%,rgba(0,0,0,0.5) 40%,transparent 100%); mask-image:radial-gradient(circle var(--sl-mask-radius) at var(--sl-mx,50%) var(--sl-my,50%),rgba(0,0,0,0.9) 0%,rgba(0,0,0,0.5) 40%,transparent 100%); }
@endif

/* SVG blob drift */
@foreach(($back['svgBlobs']['blobs'] ?? []) as $i => $blob)
@keyframes {{ $uid }}-drift-{{ $i }} { 0%,100%{transform:translate(0,0)} 33%{transform:translate(30px,-20px)} 66%{transform:translate(-20px,25px)} }
#{{ $uid }} .sl-blob-{{ $i }} { animation:{{ $uid }}-drift-{{ $i }} {{ $blob['driftDurationSec'] ?? 45 }}s ease-in-out infinite; }
@endforeach

/* Responsive */
@media (max-width: {{ $resp['mobileBreakpoint'] ?? '640px' }}) {
    #{{ $uid }} { font-size:{{ $resp['mobileBaseFontSize'] ?? '16px' }}; --sl-mask-radius:240px; }
    #{{ $uid }} .sl-section { padding:{{ $resp['mobileSectionPadding'] ?? '5rem 1.25rem' }}; min-height:{{ $resp['mobileSectionMinHeight'] ?? '95vh' }}; }
    #{{ $uid }} .sl-body-text { font-size:{{ $resp['mobileBodyFontSize'] ?? '1.1rem' }}; max-width:{{ $resp['mobileMaxReading'] ?? '100%' }}; }
    #{{ $uid }} .sl-masthead { font-size:clamp(2.4rem,13vw,4rem); letter-spacing:0.2em; padding-left:0.2em; }
    #{{ $uid }} .sl-subtitle { font-size:clamp(1.2rem,5vw,1.6rem); }
    #{{ $uid }} .sl-quote-line { font-size:clamp(1.4rem,6vw,2rem); }
    #{{ $uid }} .sl-chapter-title { font-size:clamp(2rem,10vw,3rem); }
    #{{ $uid }} .sl-pull-quote { font-size:clamp(1.4rem,6vw,2rem); padding:2rem 0; }
    #{{ $uid }} .sl-closing-line { font-size:clamp(1.3rem,5.5vw,1.8rem); }
    #{{ $uid }} .sl-eyebrow { font-size:0.7rem; letter-spacing:0.2em; }
    #{{ $uid }} .sl-divider { letter-spacing:1.2em; padding-left:1.2em; font-size:1.2rem; }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    #{{ $uid }} .sl-reveal { opacity:1 !important; transform:none !important; transition:none !important; }
    #{{ $uid }} .sl-scroll-hint { animation:none !important; }
    #{{ $uid }} .sl-clouds-lens { animation:none !important; }
    #{{ $uid }} .sl-bg-distorted { display:none; }
    @foreach(($back['svgBlobs']['blobs'] ?? []) as $i => $blob)
    #{{ $uid }} .sl-blob-{{ $i }} { animation:none !important; }
    @endforeach
}
@media (hover: none) {
    #{{ $uid }} .sl-bg-distorted { opacity:0.5; }
}
</style>

<div id="{{ $uid }}" class="sl-root sl-preset-{{ $preset }}">
    <noscript><style>#{{ $uid }} .sl-reveal { opacity:1 !important; transform:none !important; }</style></noscript>

    {{-- ── WATERCOLOR BACKGROUND ── --}}
    @if($imageEnabled)
    <div class="sl-bg-stage" aria-hidden="true">
        {{-- SVG filter definitions (hidden, zero-size) --}}
        @if($preset === 'water-ink')
        <svg style="position:absolute;width:0;height:0;overflow:hidden;" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <filter id="{{ $uid }}-wc-distort" x="-5%" y="-5%" width="110%" height="110%">
                    <feTurbulence id="{{ $uid }}-turb" type="fractalNoise" baseFrequency="{{ $wiFreq }}" numOctaves="{{ $wiOctaves }}" seed="3" result="turb" />
                    <feDisplacementMap in="SourceGraphic" in2="turb" xChannelSelector="R" yChannelSelector="G" scale="{{ $wiScale }}" />
                </filter>
            </defs>
        </svg>
        @endif

        {{-- Layer 1: Clean blurred image (always visible, base layer) --}}
        <div class="sl-bg-layer sl-bg-clean" style="background-image:url('{{ $back['image']['url'] }}');"></div>

        {{-- Layer 2: Distorted image (only visible near cursor via CSS mask) --}}
        @if($preset === 'water-ink')
        <div class="sl-bg-layer sl-bg-distorted" style="background-image:url('{{ $back['image']['url'] }}');"></div>
        @endif

        {{-- Layer 3: Paper overlay (masked to be less opaque near cursor for water-ink) --}}
        @if($preset === 'water-ink')
        <div class="sl-bg-overlay-masked"></div>
        @else
        <div class="sl-bg-overlay-flat"></div>
        @endif
    </div>
    @endif

    {{-- SVG blob backdrop --}}
    @if($back['svgBlobs']['enabled'] ?? false)
    <div class="sl-blob-layer" aria-hidden="true">
        <svg width="100%" height="100%" style="position:absolute;inset:0;" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <filter id="{{ $uid }}-blob-filter"><feTurbulence type="fractalNoise" baseFrequency="{{ $back['svgBlobs']['filter']['baseFrequency'] ?? 0.009 }}" numOctaves="{{ $back['svgBlobs']['filter']['numOctaves'] ?? 3 }}" seed="{{ $back['svgBlobs']['filter']['seed'] ?? 7 }}" /><feDisplacementMap in="SourceGraphic" scale="{{ $back['svgBlobs']['filter']['displacementScale'] ?? 120 }}" /><feGaussianBlur stdDeviation="{{ $back['svgBlobs']['filter']['gaussianBlur'] ?? 30 }}" /></filter>
                <filter id="{{ $uid }}-blob-filter-soft"><feTurbulence type="fractalNoise" baseFrequency="{{ $back['svgBlobs']['filterSoft']['baseFrequency'] ?? 0.015 }}" numOctaves="{{ $back['svgBlobs']['filterSoft']['numOctaves'] ?? 2 }}" seed="{{ $back['svgBlobs']['filterSoft']['seed'] ?? 13 }}" /><feDisplacementMap in="SourceGraphic" scale="{{ $back['svgBlobs']['filterSoft']['displacementScale'] ?? 80 }}" /><feGaussianBlur stdDeviation="{{ $back['svgBlobs']['filterSoft']['gaussianBlur'] ?? 22 }}" /></filter>
            </defs>
            @foreach(($back['svgBlobs']['blobs'] ?? []) as $i => $blob)
            <ellipse class="sl-blob-{{ $i }}" cx="{{ $blob['cx'] }}" cy="{{ $blob['cy'] }}" rx="{{ $blob['rx'] }}" ry="{{ $blob['ry'] }}" fill="var(--sl-{{ $blob['color'] }})" opacity="{{ $back['svgBlobs']['blobOpacity'] ?? 0.22 }}" style="mix-blend-mode:{{ $back['svgBlobs']['blobBlendMode'] ?? 'multiply' }};filter:url(#{{ $uid }}-blob-filter{{ $blob['softFilter'] ? '-soft' : '' }})" />
            @endforeach
        </svg>
    </div>
    @endif

    {{-- Grain --}}
    @if($back['grain']['enabled'] ?? false)
    @php $g = $back['grain']; @endphp
    <div class="sl-grain" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice" viewBox="0 0 800 800"><filter id="{{ $uid }}-grain"><feTurbulence type="fractalNoise" baseFrequency="{{ $g['baseFrequency'] ?? 0.9 }}" numOctaves="{{ $g['numOctaves'] ?? 2 }}" seed="{{ $g['seed'] ?? 5 }}" stitchTiles="stitch" /><feColorMatrix type="matrix" values="{{ $g['colorMatrix']['r'] ?? 0.16 }} 0 0 0 0 0 {{ $g['colorMatrix']['g'] ?? 0.13 }} 0 0 0 0 0 {{ $g['colorMatrix']['b'] ?? 0.09 }} 0 0 0 0 0 {{ $g['colorMatrix']['alpha'] ?? 0.35 }} 0" /></filter><rect width="100%" height="100%" filter="url(#{{ $uid }}-grain)" /></svg>
    </div>
    @endif

    {{-- Vignette --}}
    @if($back['vignette']['enabled'] ?? false)
    <div class="sl-vignette" aria-hidden="true" style="background:radial-gradient(ellipse at center, transparent {{ $back['vignette']['innerTransparent'] ?? '45%' }}, {{ $back['vignette']['outerColor'] ?? 'rgba(42,33,23,0.12)' }} 100%);"></div>
    @endif

    {{-- Clouds lens --}}
    @if($preset === 'just-clouds')
    <div class="sl-clouds-lens" aria-hidden="true" style="background:radial-gradient(circle {{ $me['just-clouds']['softness'] ?? '180px' }} at var(--sl-mx,50%) var(--sl-my,50%), rgba(255,255,255,{{ $me['just-clouds']['lightenAmount'] ?? 0.15 }}) 0%, rgba(255,255,255,{{ ($me['just-clouds']['lightenAmount'] ?? 0.15) * 0.5 }}) 40%, transparent 80%);"></div>
    @endif

    {{-- Just-water: SVG filter def + CSS-masked div --}}
    @if($preset === 'just-water')
    @php $jw = $me['just-water'] ?? []; @endphp
    <svg style="position:absolute;width:0;height:0;overflow:hidden;" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <filter id="{{ $uid }}-water-distort" x="-10%" y="-10%" width="120%" height="120%">
                <feTurbulence id="{{ $uid }}-water-turb" type="turbulence" baseFrequency="{{ $jw['turbulenceFreq'] ?? 0.008 }}" numOctaves="2" seed="5" result="turb" />
                <feDisplacementMap in="SourceGraphic" in2="turb" xChannelSelector="R" yChannelSelector="G" scale="{{ $jw['displacementScale'] ?? 30 }}" />
            </filter>
        </defs>
    </svg>
    <div class="sl-water-ripple" aria-hidden="true"></div>
    @endif

    {{-- Just-swirls: SVG filter def + CSS-masked div --}}
    @if($preset === 'just-swirls')
    @php $js = $me['just-swirls'] ?? []; @endphp
    <svg style="position:absolute;width:0;height:0;overflow:hidden;" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <filter id="{{ $uid }}-swirl-distort" x="-10%" y="-10%" width="120%" height="120%">
                <feTurbulence id="{{ $uid }}-swirl-turb" type="fractalNoise" baseFrequency="{{ $js['turbulenceFreq'] ?? 0.02 }}" numOctaves="4" seed="9" result="turb" />
                <feDisplacementMap in="SourceGraphic" in2="turb" xChannelSelector="R" yChannelSelector="B" scale="{{ $js['displacementScale'] ?? 60 }}" />
            </filter>
        </defs>
    </svg>
    <div class="sl-swirl-layer" aria-hidden="true"></div>
    @endif

    {{-- Cursor --}}
    @if($cursorShape === 'circle' || $cursorShape === 'circle-dot')
    <div class="sl-cursor-circle" aria-hidden="true"></div>
    @endif
    @if($cursorShape === 'dot' || $cursorShape === 'circle-dot')
    <div class="sl-cursor-dot" aria-hidden="true"></div>
    @endif

    {{-- Content --}}
    <main class="sl-main" style="position:relative;z-index:5;">
        @foreach(($d['pages'] ?? []) as $pi => $page)
            @include('blocks.scroll_page.pages.dispatch', ['page' => $page, 'pal' => $pal, 'uid' => $uid, 'hint' => $hint, 'rev' => $rev])
        @endforeach
    </main>

    {{-- Runtime JS --}}
    <script>
    (function(){
        var root = document.getElementById('{{ $uid }}');
        if (!root) return;
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var isTouch = window.matchMedia('(pointer:coarse)').matches;

        @if(($rev['enabled'] ?? true))
        /* Scroll reveal */
        if (reduceMotion) {
            root.querySelectorAll('.sl-reveal').forEach(function(el){ el.classList.add('sl-visible'); });
        } else {
            var obs = new IntersectionObserver(function(entries){
                entries.forEach(function(e){
                    if(e.isIntersecting){ e.target.classList.add('sl-visible'); obs.unobserve(e.target); }
                });
            }, { threshold:{{ $rev['observerThreshold'] ?? 0.15 }}, rootMargin:'{{ $rev['observerRootMargin'] ?? '0px 0px -10% 0px' }}' });
            root.querySelectorAll('.sl-reveal').forEach(function(el,i){
                el.style.transitionDelay = (i * {{ $rev['staggerMs'] ?? 250 }}) + 'ms';
                obs.observe(el);
            });
        }
        @endif

        @if($mouseEnabled)
        if (reduceMotion) return;

        var mx = window.innerWidth/2, my = window.innerHeight/2;
        var tx = mx, ty = my;
        var circle = root.querySelector('.sl-cursor-circle');
        var dot = root.querySelector('.sl-cursor-dot');

        // Preset-specific elements
        var clouds = root.querySelector('.sl-clouds-lens');
        var distorted = root.querySelector('.sl-bg-distorted');
        var overlayMasked = root.querySelector('.sl-bg-overlay-masked');
        var waterRipple = root.querySelector('.sl-water-ripple');
        var swirlLayer = root.querySelector('.sl-swirl-layer');
        var turb = document.getElementById('{{ $uid }}-turb');
        var waterTurb = document.getElementById('{{ $uid }}-water-turb');

        if (!isTouch) {
            root.addEventListener('pointerenter', function(){ root.classList.add('sl-cursor-active'); });
            root.addEventListener('pointerleave', function(){ root.classList.remove('sl-cursor-active'); });
            document.addEventListener('mousemove', function(e){ tx=e.clientX; ty=e.clientY; }, {passive:true});
        } else {
            // Touch fallback — gentle drift
            var tDrift = 0;
            setInterval(function(){
                tDrift += 0.02;
                tx = window.innerWidth * (0.5 + Math.sin(tDrift) * 0.3);
                ty = window.innerHeight * (0.5 + Math.cos(tDrift * 0.7) * 0.25);
            }, 30);
        }

        var t = 0;
        function frame(){
            mx += (tx - mx) * 0.10;
            my += (ty - my) * 0.10;
            var px = mx + 'px', py = my + 'px';

            // Custom cursor
            if(circle) circle.style.transform = 'translate('+px+','+py+') translate(-50%,-50%)';
            if(dot) dot.style.transform = 'translate('+px+','+py+') translate(-50%,-50%)';

            // Clouds lens follows mouse
            if(clouds) { clouds.style.setProperty('--sl-mx',px); clouds.style.setProperty('--sl-my',py); }

            // Water-ink: move CSS mask center to follow cursor
            if(distorted) { distorted.style.setProperty('--sl-mx',px); distorted.style.setProperty('--sl-my',py); }
            if(overlayMasked) { overlayMasked.style.setProperty('--sl-mx',px); overlayMasked.style.setProperty('--sl-my',py); }

            // Just-water / just-swirls: move CSS mask
            if(waterRipple) { waterRipple.style.setProperty('--sl-mx',px); waterRipple.style.setProperty('--sl-my',py); }
            if(swirlLayer) { swirlLayer.style.setProperty('--sl-mx',px); swirlLayer.style.setProperty('--sl-my',py); }

            // Animate turbulence frequency for organic movement
            @if($preset === 'water-ink')
            t += 0.003;
            if(turb) { var freq = {{ $wiFreq }} + Math.sin(t) * 0.003; turb.setAttribute('baseFrequency', freq.toFixed(4)); }
            @endif
            @if($preset === 'just-water')
            t += 0.004;
            if(waterTurb) { var freq = {{ $me['just-water']['turbulenceFreq'] ?? 0.008 }} + Math.sin(t) * 0.002; waterTurb.setAttribute('baseFrequency', freq.toFixed(4)); }
            @endif

            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
        @endif
    })();
    </script>
</div>
