@php
    $s = $magazine->settings ?? [];
    // display_mode: 'spread' (2-page), 'single' (1-page), 'scroll' (all pages stacked), 'flipbook' (3D library)
    $displayMode = $s['display_mode'] ?? 'spread';
    $useFlipbookLibrary = $displayMode === 'flipbook';
    $flipbookEnabled = $displayMode !== 'scroll' && !$useFlipbookLibrary;
    // Viewer display settings with defaults
    $viewMode = $s['view_mode'] ?? 'full';           // full, contained, centered
    $pageFit = $s['page_fit'] ?? 'fill';             // fill (edge-to-edge), fit (maintain ratio with padding), cover
    $bgColor = $s['bg_color'] ?? '#0a0a0a';
    $pageShadow = $s['page_shadow'] ?? true;
    $pageGap = (int)($s['page_gap'] ?? 0);             // px between pages in spread
    $showHeader = $s['show_header'] ?? true;
    $showControls = $s['show_controls'] ?? true;
    $showThumbnails = $s['show_thumbnails'] ?? true;
    $showToc = $s['show_toc'] ?? true;
    $showPageNumbers = $s['show_page_numbers'] ?? true;
    $autoHideUI = $s['auto_hide_ui'] ?? true;
    // Map display_mode to spread_mode for the JS viewer
    $spreadMode = $displayMode === 'single' ? 'single' : ($displayMode === 'scroll' ? 'single' : 'spread');
    $mobileBreakpoint = (int)($s['mobile_breakpoint'] ?? 768);
    $maxWidth = $s['max_width'] ?? '100%';            // 100%, 1400px, 90vw, etc.
    $maxHeight = $s['max_height'] ?? '100vh';
    $padding = $s['padding'] ?? '0';                  // padding around viewport
    $borderRadius = $s['border_radius'] ?? '0';
    $pageTransition = $s['page_transition'] ?? 'slide'; // slide, fade, flip, none
    // Spread mode always uses realistic page turn
    if ($displayMode === 'spread') $pageTransition = 'turn';
    $transitionSpeed = (int)($s['transition_speed'] ?? 400);
    $uiTheme = $s['ui_theme'] ?? 'dark';              // dark, light, auto
    $headerBg = $s['header_bg'] ?? '';
    $controlsBg = $s['controls_bg'] ?? '';
    $pnPosition = $s['pn_position'] ?? 'bottom';
    $pnAlign = $s['pn_align'] ?? 'outer';
    $pnSize = $s['pn_size'] ?? '9px';
@endphp
<!DOCTYPE html>
<html lang="{{ $site?->theme?->config['lang'] ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="robots" content="index, follow">
    <title>{{ $magazine->title }} | {{ $site?->name ?? 'Magazine' }}</title>
    <meta name="description" content="{{ $magazine->description ?? $magazine->title }}">
    <meta property="og:title" content="{{ $magazine->title }}">
    <meta property="og:type" content="article">
    @if($magazine->cover_image)
    <meta property="og:image" content="{{ $magazine->cover_image }}">
    @endif
    <link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Inter'; src: url('/fonts/inter.woff2') format('woff2'); font-weight: 100 900; font-style: normal; font-display: swap; }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%; overflow: hidden;
            font-family: 'Inter', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: {{ $bgColor }};
            color: {{ $uiTheme === 'light' ? '#1a1a1a' : '#fff' }};
        }

        /* ─── Viewer container ─── */
        .viewer {
            position: fixed; inset: 0;
            display: flex; align-items: center; justify-content: center;
            padding: {{ $padding }};
        }
        .viewer-inner {
            position: relative;
            width: 100%; height: 100%;
            max-width: {{ $maxWidth }};
            max-height: {{ $maxHeight }};
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto;
        }

        /* ─── Flipbook viewport ─── */
        .flipbook-viewport {
            position: relative; display: flex;
            gap: {{ $pageGap }}px;
            border-radius: {{ $borderRadius }};
            overflow: hidden;
        }

        /* ─── Page containers ─── */
        .page-container {
            position: relative; overflow: hidden;
            @if($pageShadow) box-shadow: 0 4px 24px rgba(0,0,0,0.3); @endif
            border-radius: {{ $borderRadius }};
            transition: opacity {{ $transitionSpeed }}ms ease, transform {{ $transitionSpeed }}ms ease;
        }
        .page-surface {
            width: 100%; height: 100%;
            position: relative; overflow: hidden;
            background-size: cover; background-position: center;
        }

        /* ─── Page elements ─── */
        .page-element { position: absolute; overflow: hidden; }
        .page-element.type-text { pointer-events: none; column-fill: auto; }
        .page-element.type-text * { margin: 0; }
        .page-element.type-image img { width: 100%; height: 100%; display: block; }
        .page-element.type-video iframe { width: 100%; height: 100%; border: none; }
        .page-element.type-hotspot { cursor: pointer; transition: background 0.2s; }
        .page-element.type-hotspot:hover { background: rgba(255,255,255,0.1); }

        /* ─── Transition effects ─── */
        @if($pageTransition === 'fade')
        .page-container.transitioning { opacity: 0; }
        @elseif($pageTransition === 'slide')
        .page-container.transitioning { transform: translateX(30px); opacity: 0; }
        @elseif($pageTransition === 'flip')
        .flipbook-viewport { perspective: 2000px; }
        .page-container {
            transform-style: preserve-3d;
            backface-visibility: hidden;
        }
        .page-container.transitioning { transform: rotateY(-90deg); opacity: 0.5; }
        .page-container.transitioning-reverse { transform: rotateY(90deg); opacity: 0.5; }
        @elseif($pageTransition === 'curl')
        .flipbook-viewport { perspective: 1800px; }
        .page-container {
            transform-origin: left center;
            transform-style: preserve-3d;
        }
        .page-container.transitioning {
            transform: rotateY(-120deg) scale(0.95);
            opacity: 0;
            box-shadow: -10px 0 30px rgba(0,0,0,0.3);
        }
        .page-container::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 40px; height: 100%;
            background: linear-gradient(to left, rgba(0,0,0,0.08), transparent);
            pointer-events: none;
            opacity: 0;
            transition: opacity {{ $transitionSpeed }}ms;
        }
        .page-container.transitioning::after { opacity: 1; }
        @elseif($pageTransition === 'turn')
        .flipbook-viewport {
            perspective: 2000px;
            position: relative;
            overflow: visible;
        }
        .page-container {
            will-change: transform;
            transform: rotateY(0deg);
            transform-style: preserve-3d;
            /* overflow must be visible so preserve-3d works (hidden flattens 3D) */
            overflow: visible !important;
            position: relative;
        }
        /* Keep page content clipped inside the surface, not the container */
        .page-container .page-surface {
            overflow: hidden;
        }
        /* Front face: hide when flipped past 90° */
        .page-container > .page-surface {
            backface-visibility: hidden;
        }
        /* Paper back — visible when page is flipped past 90° */
        .page-container.has-back::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to right, #e8e5e0, #f0ede8);
            transform: rotateY(180deg);
            backface-visibility: hidden;
            z-index: 0;
            border-radius: {{ $borderRadius }};
        }
        /* Spread layers for turn animation */
        .spread-layer {
            display: flex;
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
        }
        .spread-layer.under { z-index: 1; }
        .spread-layer.over  { z-index: 2; pointer-events: none; }
        /* Right page flips forward: hinged on LEFT edge */
        .right-page.turning-forward {
            transform-origin: left center;
            transform: rotateY(-180deg);
            transition: transform {{ $transitionSpeed * 1.5 }}ms cubic-bezier(0.22, 0.61, 0.36, 1);
            z-index: 10;
        }
        /* Fold shadow that appears during page turn */
        .right-page.turning-forward .page-surface::after {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to right, rgba(0,0,0,0.15), transparent 40%);
            pointer-events: none; z-index: 99;
        }
        /* Left page flips backward: hinged on RIGHT edge */
        .left-page.turning-backward {
            transform-origin: right center;
            transform: rotateY(180deg);
            transition: transform {{ $transitionSpeed * 1.5 }}ms cubic-bezier(0.22, 0.61, 0.36, 1);
            z-index: 10;
        }
        .left-page.turning-backward .page-surface::after {
            content: '';
            position: absolute; top: 0; right: 0; width: 100%; height: 100%;
            background: linear-gradient(to left, rgba(0,0,0,0.15), transparent 40%);
            pointer-events: none; z-index: 99;
        }
        /* Spine shadow on resting pages */
        .left-page { box-shadow: inset -4px 0 12px rgba(0,0,0,0.08); }
        .right-page { box-shadow: inset 4px 0 12px rgba(0,0,0,0.08); }
        @endif

        /* ─── Page numbers (all modes) ─── */
        .page-number {
            position: absolute;
            bottom: 16px;
            font-size: var(--pn-size, 9px);
            color: rgba(0,0,0,0.25);
            font-family: 'Inter', system-ui, sans-serif;
            pointer-events: none;
            z-index: 20;
        }
        .page-number.pn-left { left: 20px; }
        .page-number.pn-right { right: 20px; }
        .page-number.pn-center { left: 50%; transform: translateX(-50%); }

        /* ─── UI theme ─── */
        :root {
            --ui-text: {{ $uiTheme === 'light' ? 'rgba(0,0,0,0.7)' : 'rgba(255,255,255,0.7)' }};
            --ui-text-hover: {{ $uiTheme === 'light' ? 'rgba(0,0,0,0.95)' : '#fff' }};
            --ui-bg: {{ $uiTheme === 'light' ? 'rgba(255,255,255,0.85)' : 'rgba(0,0,0,0.7)' }};
            --ui-bg-hover: {{ $uiTheme === 'light' ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.15)' }};
            --ui-border: {{ $uiTheme === 'light' ? 'rgba(0,0,0,0.08)' : 'rgba(255,255,255,0.12)' }};
        }

        /* ─── Header bar ─── */
        .header-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: {{ $showHeader ? 'flex' : 'none' }};
            align-items: center; justify-content: space-between;
            padding: 10px 20px; height: 44px;
            background: {{ $headerBg ?: 'var(--ui-bg)' }};
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid var(--ui-border);
            transition: opacity 0.3s, transform 0.3s;
        }
        .header-bar .title { font-size: 13px; font-weight: 500; color: var(--ui-text); }
        .header-bar button {
            background: none; border: none; color: var(--ui-text); cursor: pointer;
            padding: 4px 8px; font-size: 14px; font-family: inherit; border-radius: 4px;
            transition: background 0.15s, color 0.15s;
        }
        .header-bar button:hover { color: var(--ui-text-hover); background: var(--ui-bg-hover); }

        /* ─── Controls bar ─── */
        .controls {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
            display: {{ $showControls ? 'flex' : 'none' }};
            align-items: center; justify-content: center;
            gap: 12px; padding: 10px 20px; height: 44px;
            background: {{ $controlsBg ?: 'var(--ui-bg)' }};
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            backdrop-filter: saturate(180%) blur(20px);
            border-top: 1px solid var(--ui-border);
            transition: opacity 0.3s, transform 0.3s;
        }
        .controls button {
            background: var(--ui-bg-hover); border: 1px solid var(--ui-border);
            color: var(--ui-text); padding: 6px 14px; font-size: 13px;
            font-family: inherit; cursor: pointer; border-radius: 6px;
            transition: background 0.15s, color 0.15s;
        }
        .controls button:hover { color: var(--ui-text-hover); background: var(--ui-bg-hover); }
        .controls button:disabled { opacity: 0.25; cursor: default; }
        .controls .page-indicator {
            font-size: 12px; color: var(--ui-text); min-width: 70px;
            text-align: center; font-variant-numeric: tabular-nums;
        }

        /* ─── Auto-hide UI ─── */
        @if($autoHideUI)
        .ui-hidden .header-bar { opacity: 0; transform: translateY(-100%); pointer-events: none; }
        .ui-hidden .controls { opacity: 0; transform: translateY(100%); pointer-events: none; }
        .ui-hidden .thumb-strip { opacity: 0; pointer-events: none; }
        @endif

        /* ─── TOC overlay ─── */
        .toc-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: {{ $uiTheme === 'light' ? 'rgba(255,255,255,0.95)' : 'rgba(0,0,0,0.92)' }};
            -webkit-backdrop-filter: blur(20px); backdrop-filter: blur(20px);
            display: none; align-items: center; justify-content: center;
        }
        .toc-overlay.visible { display: flex; }
        .toc-content { max-width: 400px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .toc-content h2 { font-size: 16px; font-weight: 500; margin-bottom: 20px; color: var(--ui-text-hover); }
        .toc-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid var(--ui-border);
            cursor: pointer; color: var(--ui-text); font-size: 14px; transition: color 0.15s;
        }
        .toc-item:hover { color: var(--ui-text-hover); }
        .toc-item .num { font-variant-numeric: tabular-nums; font-size: 11px; opacity: 0.4; }
        .toc-close { position: absolute; top: 16px; right: 20px; background: none; border: none; color: var(--ui-text); font-size: 22px; cursor: pointer; }

        /* ─── Thumbnails ─── */
        .thumb-strip {
            position: fixed; bottom: 44px; left: 0; right: 0; z-index: 90;
            display: none; justify-content: center; gap: 4px; padding: 8px 20px;
            overflow-x: auto; background: var(--ui-bg);
            -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
            border-top: 1px solid var(--ui-border);
            transition: opacity 0.3s;
        }
        .thumb-strip.visible { display: flex; }
        .thumb-strip .thumb {
            width: 40px; flex-shrink: 0;
            aspect-ratio: var(--page-ratio);
            border: 2px solid transparent; border-radius: 3px;
            cursor: pointer; overflow: hidden; transition: border-color 0.15s;
        }
        .thumb-strip .thumb.active { border-color: var(--ui-text-hover); }
        .thumb-strip .thumb:hover { border-color: var(--ui-text); }

        /* ─── Page numbers ─── */
        .page-number {
            position: absolute; bottom: 8px; font-size: 10px;
            color: rgba(128,128,128,0.5); font-variant-numeric: tabular-nums;
            display: {{ $showPageNumbers ? 'block' : 'none' }};
        }
        .page-number.left { left: 12px; }
        .page-number.right { right: 12px; }

        /* ─── Responsive ─── */
        @media (max-width: {{ $mobileBreakpoint }}px) {
            .flipbook-viewport { flex-direction: column; }
        }
        :fullscreen { background: {{ $bgColor }}; }

        @unless($flipbookEnabled)
        /* ─── Scroll mode (flipbook disabled) ─── */
        html, body { overflow: auto; height: auto; }
        .viewer { position: relative; inset: auto; display: block; padding: 0; }
        .viewer-inner { height: auto; display: block; }
        .flipbook-viewport {
            display: flex; flex-direction: column; align-items: center;
            gap: 24px; padding: 60px 20px 40px;
        }
        .page-container { flex-shrink: 0; }
        .controls { display: none !important; }
        @endunless
    </style>
</head>
<body>
    <div class="header-bar" id="header-bar">
        <span class="title">{{ e($magazine->title) }}</span>
        <div style="display:flex;gap:4px;">
            @if($showToc)<button onclick="toggleTOC()" title="Contents">&#9776;</button>@endif
            @if($showThumbnails)<button onclick="toggleThumbs()" title="Thumbnails">&#9707;</button>@endif
            <button onclick="toggleFullscreen()" title="Fullscreen">&#x26F6;</button>
        </div>
    </div>

    <div class="viewer" id="viewer">
        <div class="viewer-inner">
            <div class="flipbook-viewport" id="viewport"></div>
        </div>
    </div>

    <div class="controls" id="controls-bar">
        <button id="btn-prev" onclick="prevPage()" title="Previous">&#8592;</button>
        <span class="page-indicator" id="page-indicator">1 / 1</span>
        <button id="btn-next" onclick="nextPage()" title="Next">&#8594;</button>
    </div>

    <div class="thumb-strip" id="thumb-strip"></div>

    <div class="toc-overlay" id="toc-overlay">
        <button class="toc-close" onclick="toggleTOC()">&times;</button>
        <div class="toc-content" id="toc-content"></div>
    </div>

    {{-- Shared HTML sanitizer for magazine text content --}}
    <script>
    var SAFE_TAGS = new Set(['P','BR','B','I','U','EM','STRONG','SPAN','A','H1','H2','H3','H4','H5','H6','UL','OL','LI','BLOCKQUOTE','SUB','SUP','HR','DIV']);
    var SAFE_ATTRS = new Set(['href','target','rel','class','style']);
    function sanitizeHTML(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        tmp.querySelectorAll('script,iframe,object,embed,form,input,textarea,select,button,link,meta,style').forEach(function(el) { el.remove(); });
        var all = tmp.querySelectorAll('*');
        for (var i = 0; i < all.length; i++) {
            var el = all[i];
            if (!SAFE_TAGS.has(el.tagName)) { el.replaceWith(document.createTextNode(el.textContent || '')); continue; }
            for (var j = el.attributes.length - 1; j >= 0; j--) {
                var attr = el.attributes[j];
                if (!SAFE_ATTRS.has(attr.name)) { el.removeAttribute(attr.name); continue; }
                if (attr.name === 'href') {
                    var href = attr.value.replace(/[\x00-\x1f\x7f]/g, '').trim().toLowerCase();
                    var allowed = href.startsWith('http://') || href.startsWith('https://') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('/') || href.startsWith('#') || href.startsWith('.');
                    if (!allowed) { el.removeAttribute(attr.name); continue; }
                }
                }
            }
        }
        return tmp.innerHTML;
    }
    </script>

    @unless($useFlipbookLibrary)
    <script>
    (function() {
        const PAGES = {!! $pagesJson !!};
        const PAGE_W = {{ $magazine->page_width }};
        const PAGE_H = {{ $magazine->page_height }};
        const RATIO = PAGE_W / PAGE_H;
        const FLIPBOOK_ENABLED = {{ $flipbookEnabled ? 'true' : 'false' }};
        const SPREAD_MODE = '{{ $spreadMode }}';
        const PAGE_FIT = '{{ $pageFit }}';
        const AUTO_HIDE = {{ $autoHideUI ? 'true' : 'false' }};
        const SHOW_PAGE_NUM = {{ $showPageNumbers ? 'true' : 'false' }};
        const MOBILE_BP = {{ $mobileBreakpoint }};
        const TRANSITION = '{{ $pageTransition }}';
        const TRANS_SPEED = {{ $transitionSpeed }};

        let current = 0;
        let animating = false;
        let uiTimer = null;
        const isMobile = () => window.innerWidth <= MOBILE_BP;
        const isSingle = () => SPREAD_MODE === 'single' || (SPREAD_MODE === 'auto' && isMobile());

        const viewport = document.getElementById('viewport');
        const indicator = document.getElementById('page-indicator');
        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');

        document.documentElement.style.setProperty('--page-ratio', RATIO);

        // ─── Page sizing ───
        function getPageSize() {
            const headerH = document.getElementById('header-bar')?.offsetHeight || 0;
            const controlsH = document.getElementById('controls-bar')?.offsetHeight || 0;
            const uiH = headerH + controlsH + 16;
            const viewerEl = document.querySelector('.viewer-inner');
            const maxW = viewerEl ? viewerEl.clientWidth : window.innerWidth;
            const maxH = viewerEl ? viewerEl.clientHeight : window.innerHeight;

            const availH = maxH - uiH;
            const availW = maxW / (isSingle() ? 1 : 2);

            let w, h;
            if (PAGE_FIT === 'fill') {
                // Fill: use all available space
                w = availW;
                h = availH;
                // Adjust to maintain ratio
                if (w / h > RATIO) { w = h * RATIO; } else { h = w / RATIO; }
            } else if (PAGE_FIT === 'cover') {
                // Cover: fill container, crop overflow
                w = availW;
                h = availH;
            } else {
                // Fit: maintain ratio with padding
                w = availW - 24;
                h = w / RATIO;
                if (h > availH - 24) { h = availH - 24; w = h * RATIO; }
            }

            return { w: Math.floor(Math.max(w, 100)), h: Math.floor(Math.max(h, 100)) };
        }

        // ─── Render page ───
        function renderPage(page, pageNum) {
            const surface = document.createElement('div');
            surface.className = 'page-surface';
            surface.style.backgroundColor = page.background_color || '#fff';
            if (page.background_image) surface.style.backgroundImage = `url(${page.background_image})`;

            (page.elements || []).forEach(el => {
                const div = document.createElement('div');
                div.className = `page-element type-${el.type}`;
                div.style.cssText = `left:${el.x}%;top:${el.y}%;width:${el.width}%;height:${el.height}%;z-index:${el.z_index||0};`;
                if (el.rotation) div.style.transform = `rotate(${el.rotation}deg)`;

                if (el.type === 'text') {
                    div.innerHTML = sanitizeHTML(el.content.html || '');
                    var cols = parseInt(el.content.columnsInFrame) || 1;
                    if (cols > 1 && cols <= 6) { div.style.columnCount = cols; div.style.columnGap = (parseInt(el.content.columnGap) || 12) + 'px'; }
                    div.style.columnFill = el.content.columnFill === 'balance' ? 'balance' : 'auto';
                    if (el.content.textInset) { var ti = el.content.textInset; div.style.padding = (parseInt(ti.top)||0)+'px '+(parseInt(ti.right)||0)+'px '+(parseInt(ti.bottom)||0)+'px '+(parseInt(ti.left)||0)+'px'; }
                } else if (el.type === 'image' && el.content.src) {
                    var img = document.createElement('img');
                    img.src = el.content.src; img.alt = el.content.alt || '';
                    img.style.objectFit = el.content.objectFit || 'cover'; img.loading = 'lazy';
                    div.appendChild(img);
                } else if (el.type === 'video' && el.content.videoId) {
                    const iframe = document.createElement('iframe');
                    iframe.src = `https://www.youtube-nocookie.com/embed/${el.content.videoId}?rel=0`;
                    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                    iframe.allowFullscreen = true;
                    div.appendChild(iframe);
                } else if (el.type === 'hotspot') {
                    div.title = el.content.tooltip || '';
                    if (el.content.shape === 'circle') div.style.borderRadius = '50%';
                    div.style.border = `2px solid ${el.content.borderColor || 'transparent'}`;
                    div.onclick = () => { if (el.content.url) window.open(el.content.url, el.content.target || '_blank'); };
                } else if (el.type === 'shape') {
                    div.style.backgroundColor = el.content.fill || '#000';
                    if (el.content.shapeType === 'circle') div.style.borderRadius = '50%';
                }
                surface.appendChild(div);
            });

            // Page number
            if (SHOW_PAGE_NUM && pageNum !== undefined) {
                const num = document.createElement('span');
                num.className = 'page-number ' + (pageNum % 2 === 0 ? 'left' : 'right');
                num.textContent = pageNum + 1;
                surface.appendChild(num);
            }

            return surface;
        }

        // ─── Page number settings (from global site settings) ───
        const PN_ENABLED = {{ $showPageNumbers ? 'true' : 'false' }};
        const PN_POSITION = '{{ $pnPosition }}'; // bottom or top
        const PN_ALIGN = '{{ $pnAlign }}';       // outer, center, left, right
        const PN_SIZE = '{{ $pnSize }}';
        document.documentElement.style.setProperty('--pn-size', PN_SIZE);

        // ─── Build a spread div for given page index ───
        function buildSpreadDiv(startIdx) {
            const { w, h } = getPageSize();
            const frag = document.createDocumentFragment();

            if (isSingle()) {
                const c = makePageContainer(startIdx, w, h, 'single');
                frag.appendChild(c);
            } else {
                const leftIdx = startIdx % 2 === 0 ? startIdx : startIdx - 1;
                const lc = makePageContainer(leftIdx, w, h, 'left-page');
                const rc = makePageContainer(leftIdx + 1, w, h, 'right-page');
                frag.appendChild(lc);
                frag.appendChild(rc);
            }
            return frag;
        }

        function makePageContainer(idx, w, h, cls) {
            const c = document.createElement('div');
            c.className = 'page-container ' + cls;
            c.style.width = w + 'px';
            c.style.height = h + 'px';
            if (idx >= 0 && idx < PAGES.length) {
                c.style.backgroundColor = PAGES[idx].background_color || '#ffffff';
                c.appendChild(renderPage(PAGES[idx], idx));
                // Add page number
                if (PN_ENABLED) {
                    const pn = document.createElement('div');
                    let alignClass = 'pn-center';
                    if (PN_ALIGN === 'outer') alignClass = cls === 'left-page' ? 'pn-left' : cls === 'right-page' ? 'pn-right' : 'pn-center';
                    else if (PN_ALIGN === 'left') alignClass = 'pn-left';
                    else if (PN_ALIGN === 'right') alignClass = 'pn-right';
                    else alignClass = 'pn-center';
                    pn.className = 'page-number ' + alignClass;
                    if (PN_POSITION === 'top') { pn.style.bottom = 'auto'; pn.style.top = '16px'; }
                    pn.textContent = String(idx + 1);
                    c.appendChild(pn);
                }
            } else {
                // Empty page (beyond content) — white, not black
                c.style.backgroundColor = '#ffffff';
            }
            return c;
        }

        // ─── Build spread (normal, non-animated) ───
        function buildSpread() {
            viewport.innerHTML = '';
            viewport.style.position = 'relative';
            // Set explicit dimensions so layout is stable between resting and animated states
            const { w, h } = getPageSize();
            viewport.style.width = (isSingle() ? w : w * 2) + 'px';
            viewport.style.height = h + 'px';
            viewport.appendChild(buildSpreadDiv(current));
            updateUI();
        }

        function updateUI() {
            indicator.textContent = `${current + 1} / ${PAGES.length}`;
            btnPrev.disabled = current <= 0;
            btnNext.disabled = current >= PAGES.length - 1;
            document.querySelectorAll('.thumb-strip .thumb').forEach((t, i) => t.classList.toggle('active', i === current));
        }

        // ─── Navigation with transition ───
        function navigate(dir) {
            if (animating) return;
            const step = isSingle() ? 1 : 2;
            const next = dir > 0 ? Math.min(current + step, PAGES.length - 1) : Math.max(current - step, 0);
            if (next === current) return;

            if (TRANSITION === 'none') {
                current = next;
                buildSpread();
                return;
            }

            animating = true;
            const isPageTurn = (TRANSITION === 'turn' || TRANSITION === 'curl');
            const speed = isPageTurn ? Math.round(TRANS_SPEED * 1.4) : TRANS_SPEED;

            if (isPageTurn && !isSingle()) {
                // ─── REALISTIC PAGE TURN ───
                // Layer the NEXT spread UNDER the current one
                // Then animate the turning page to reveal the next spread
                const { w, h } = getPageSize();

                // Build next spread and place it under
                const underLayer = document.createElement('div');
                underLayer.className = 'spread-layer under';
                underLayer.style.cssText = `display:flex;position:absolute;top:0;left:0;right:0;bottom:0;z-index:1;`;
                underLayer.appendChild(buildSpreadDiv(next));

                // Wrap current spread as over layer
                const overLayer = document.createElement('div');
                overLayer.className = 'spread-layer over';
                overLayer.style.cssText = `display:flex;position:relative;z-index:2;`;

                // Move existing page containers into over layer
                const existing = Array.from(viewport.querySelectorAll('.page-container'));
                existing.forEach(el => overLayer.appendChild(el));

                viewport.innerHTML = '';
                viewport.style.position = 'relative';
                viewport.appendChild(underLayer);
                viewport.appendChild(overLayer);

                // Set viewport dimensions
                viewport.style.width = (isSingle() ? w : w * 2) + 'px';
                viewport.style.height = h + 'px';

                // Trigger the turn animation — double rAF ensures the browser has
                // painted the initial state so the CSS transition actually fires
                requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    const turningPage = dir > 0
                        ? overLayer.querySelector('.right-page')
                        : overLayer.querySelector('.left-page');
                    const turnClass = dir > 0 ? 'turning-forward' : 'turning-backward';
                    if (turningPage) {
                        turningPage.classList.add('has-back');
                        turningPage.classList.add(turnClass);
                    }

                    // After animation completes, clean up and show final spread
                    setTimeout(() => {
                        current = next;
                        buildSpread();
                        animating = false;
                    }, speed);
                });
                });

            } else {
                // ─── SIMPLE TRANSITIONS (fade, slide, flip) ───
                const transClass = dir < 0 ? 'transitioning-reverse' : 'transitioning';
                viewport.querySelectorAll('.page-container').forEach(c => c.classList.add(transClass));

                setTimeout(() => {
                    current = next;
                    buildSpread();
                    const newContainers = viewport.querySelectorAll('.page-container');
                    newContainers.forEach(c => c.classList.add(transClass));
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            newContainers.forEach(c => {
                                c.classList.remove('transitioning');
                                c.classList.remove('transitioning-reverse');
                            });
                            setTimeout(() => { animating = false; }, speed);
                        });
                    });
                }, speed);
            }
        }

        window.nextPage = () => navigate(1);
        window.prevPage = () => navigate(-1);
        function goToPage(idx) {
            current = Math.max(0, Math.min(idx, PAGES.length - 1));
            if (!isSingle()) current = current % 2 === 0 ? current : current - 1;
            buildSpread();
        }

        // ─── Auto-hide UI ───
        if (AUTO_HIDE) {
            function showUI() {
                document.body.classList.remove('ui-hidden');
                clearTimeout(uiTimer);
                uiTimer = setTimeout(() => document.body.classList.add('ui-hidden'), 3000);
            }
            document.addEventListener('mousemove', showUI);
            document.addEventListener('touchstart', showUI, { passive: true });
            showUI();
        }

        // ─── Keyboard ───
        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowRight' || e.key === ' ') { e.preventDefault(); nextPage(); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); prevPage(); }
            if (e.key === 'Escape') { toggleTOC(false); toggleThumbs(false); }
            if (e.key === 'f') toggleFullscreen();
        });

        // ─── Touch swipe ───
        let touchX = 0;
        document.addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
        document.addEventListener('touchend', e => {
            const dx = e.changedTouches[0].clientX - touchX;
            if (Math.abs(dx) > 50) { dx < 0 ? nextPage() : prevPage(); }
        }, { passive: true });

        // ─── Fullscreen ───
        window.toggleFullscreen = () => {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(() => {});
            else document.exitFullscreen();
        };

        // ─── TOC ───
        window.toggleTOC = (show) => {
            const el = document.getElementById('toc-overlay');
            el.classList.toggle('visible', show !== undefined ? show : !el.classList.contains('visible'));
        };
        function buildTOC() {
            const c = document.getElementById('toc-content');
            c.innerHTML = '<h2>Contents</h2>';
            PAGES.forEach((page, i) => {
                const item = document.createElement('div');
                item.className = 'toc-item';
                var titleSpan = document.createElement('span'); titleSpan.textContent = page.title || 'Page ' + (i + 1);
                var numSpan = document.createElement('span'); numSpan.className = 'num'; numSpan.textContent = String(i + 1);
                item.appendChild(titleSpan); item.appendChild(numSpan);
                item.onclick = () => { goToPage(i); toggleTOC(false); };
                c.appendChild(item);
            });
        }

        // ─── Thumbnails ───
        window.toggleThumbs = (show) => {
            const el = document.getElementById('thumb-strip');
            el.classList.toggle('visible', show !== undefined ? show : !el.classList.contains('visible'));
        };
        function buildThumbs() {
            const strip = document.getElementById('thumb-strip');
            strip.innerHTML = '';
            PAGES.forEach((page, i) => {
                const t = document.createElement('div');
                t.className = 'thumb' + (i === current ? ' active' : '');
                t.style.backgroundColor = page.background_color || '#fff';
                if (page.background_image) { t.style.backgroundImage = `url(${page.background_image})`; t.style.backgroundSize = 'cover'; }
                t.onclick = () => goToPage(i);
                strip.appendChild(t);
            });
        }

        // ─── Scroll mode: render all pages at once ───
        function buildScrollMode() {
            viewport.innerHTML = '';
            const maxW = Math.min(window.innerWidth - 40, 900);
            const pageH = maxW / RATIO;
            PAGES.forEach((page, i) => {
                const c = document.createElement('div');
                c.className = 'page-container';
                c.style.width = maxW + 'px';
                c.style.height = pageH + 'px';
                c.style.backgroundColor = page.background_color || '#ffffff';
                c.appendChild(renderPage(page, i));
                if (PN_ENABLED) {
                    const pn = document.createElement('div');
                    pn.className = 'page-number ' + (i % 2 === 0 ? 'pn-left' : 'pn-right');
                    if (PN_POSITION === 'top') { pn.style.bottom = 'auto'; pn.style.top = '16px'; }
                    pn.textContent = String(i + 1);
                    c.appendChild(pn);
                }
                viewport.appendChild(c);
            });
            indicator.textContent = `${PAGES.length} pages`;
        }

        if (!FLIPBOOK_ENABLED) {
            buildScrollMode(); buildTOC();
            window.addEventListener('resize', buildScrollMode);
        } else {
            window.addEventListener('resize', () => buildSpread());
            buildSpread(); buildTOC(); buildThumbs();
        }
    })();
    </script>
    @endunless
@if($useFlipbookLibrary)
    {{-- ═══ FLIPBOOK MODE: powered by EnsodoFlipbook library ═══ --}}
    <style>
        /* Flipbook library core styles */
        .ef-root{position:relative;margin:0 auto;user-select:none;-webkit-user-select:none;outline:none}
        .ef-viewport{position:relative;width:100%;overflow:hidden}
        .ef-spread{position:relative;display:flex;width:100%;height:100%}
        .ef-root.ef-single .ef-spread{display:block}
        .ef-well{position:relative;width:50%;height:100%;overflow:hidden;flex-shrink:0}
        .ef-root.ef-single .ef-well{width:100%}
        .ef-well-left{border-right:1px solid rgba(0,0,0,0.06)}
        .ef-well-right{border-left:1px solid rgba(0,0,0,0.06)}
        .ef-page{position:absolute;top:0;left:0;width:100%;height:100%;overflow:hidden;background:#fff;box-sizing:border-box}
        .ef-page[aria-hidden='true']{display:none}
        .ef-flip-container{position:absolute;top:0;width:100%;height:100%;transform-style:preserve-3d;z-index:10}
        .ef-flip-front,.ef-flip-back{position:absolute;top:0;left:0;width:100%;height:100%;backface-visibility:hidden;-webkit-backface-visibility:hidden;overflow:hidden;background:#fff}
        .ef-flip-back{transform:rotateY(180deg)}
        .ef-curl-gradient,.ef-cast-shadow{position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none}
        .ef-curl-gradient{z-index:20}.ef-cast-shadow{z-index:5}
        .ef-sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        .ef-page.ef-cover{box-shadow:0 2px 8px rgba(0,0,0,0.15)}
        .ef-well-left::after,.ef-well-right::after{content:'';position:absolute;top:0;width:12px;height:100%;pointer-events:none;z-index:15}
        .ef-well-left::after{right:0;background:linear-gradient(to left,rgba(0,0,0,0.04),transparent)}
        .ef-well-right::after{left:0;background:linear-gradient(to right,rgba(0,0,0,0.04),transparent)}

        /* Hide old viewer elements completely */
        .flipbook-viewport, #controls-bar, #thumb-strip { display:none !important; }

        /* Flipbook wrapper — fill screen, minimal gaps */
        .fb-wrapper {
            position: fixed; inset: 0;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 46px 0 0;
            box-sizing: border-box;
        }
        .fb-book-container {
            position: relative;
            flex: 1 1 auto;
            display: flex; align-items: center; justify-content: center;
            width: 100%;
            min-height: 0;
            padding: 0 60px; /* room for side arrows */
        }
        .fb-book-area {
            width: 100%;
            max-height: 100%;
        }
        /* Fullscreen: keep header, minimal gaps */
        :fullscreen .fb-wrapper,
        :-webkit-full-screen .fb-wrapper {
            padding: 46px 0 0;
        }
        .ef-page .page-surface {
            width:100%; height:100%; position:relative; overflow:hidden;
        }

        /* Side arrows */
        .fb-side-arrow {
            position: absolute;
            top: 50%; transform: translateY(-50%);
            width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.08);
            border: none; border-radius: 50%;
            color: rgba(255,255,255,0.7); font-size: 22px;
            cursor: pointer; z-index: 30;
            transition: background 0.2s, color 0.2s;
            flex-shrink: 0;
        }
        .fb-side-arrow:hover { background: rgba(255,255,255,0.18); color: #fff; }
        .fb-side-arrow:disabled { opacity: 0.15; cursor: default; }
        .fb-arrow-left { left: 6px; }
        .fb-arrow-right { right: 6px; }
    </style>
    <script>
    (function() {
        var PAGES = {!! $pagesJson !!};
        var PAGE_W = {{ $magazine->page_width }};
        var PAGE_H = {{ $magazine->page_height }};

        // Build the flipbook wrapper (replaces old viewer layout)
        var viewer = document.getElementById('viewer');
        viewer.innerHTML = '';
        viewer.style.cssText = '';

        var wrapper = document.createElement('div');
        wrapper.className = 'fb-wrapper';

        var bookContainer = document.createElement('div');
        bookContainer.className = 'fb-book-container';

        var bookArea = document.createElement('div');
        bookArea.className = 'fb-book-area';
        bookArea.id = 'fb-book';

        // Side arrows
        var arrowLeft = document.createElement('button');
        arrowLeft.className = 'fb-side-arrow fb-arrow-left';
        arrowLeft.id = 'fb-arrow-l';
        arrowLeft.innerHTML = '&#8249;';
        arrowLeft.title = 'Previous page';

        var arrowRight = document.createElement('button');
        arrowRight.className = 'fb-side-arrow fb-arrow-right';
        arrowRight.id = 'fb-arrow-r';
        arrowRight.innerHTML = '&#8250;';
        arrowRight.title = 'Next page';

        bookContainer.appendChild(arrowLeft);
        bookContainer.appendChild(bookArea);
        bookContainer.appendChild(arrowRight);

        wrapper.appendChild(bookContainer);
        document.body.appendChild(wrapper);

        // Build page elements
        PAGES.forEach(function(page, i) {
            var article = document.createElement('article');
            var surface = document.createElement('div');
            surface.className = 'page-surface';
            surface.style.backgroundColor = page.background_color || '#fff';
            if (page.background_image) {
                surface.style.backgroundImage = 'url(' + page.background_image + ')';
                surface.style.backgroundSize = 'cover';
                surface.style.backgroundPosition = 'center';
            }
            (page.elements || []).forEach(function(el) {
                var div = document.createElement('div');
                div.style.cssText = 'position:absolute;overflow:hidden;left:'+el.x+'%;top:'+el.y+'%;width:'+el.width+'%;height:'+el.height+'%;z-index:'+(el.z_index||0)+';';
                if (el.rotation) div.style.transform = 'rotate('+el.rotation+'deg)';
                if (el.type === 'text') {
                    div.innerHTML = sanitizeHTML(el.content.html || '');
                    var cols2 = parseInt(el.content.columnsInFrame) || 1;
                    if (cols2 > 1 && cols2 <= 6) { div.style.columnCount = cols2; div.style.columnGap = (parseInt(el.content.columnGap) || 12) + 'px'; }
                    div.style.columnFill = el.content.columnFill === 'balance' ? 'balance' : 'auto';
                    if (el.content.textInset) { var ti2 = el.content.textInset; div.style.padding = (parseInt(ti2.top)||0)+'px '+(parseInt(ti2.right)||0)+'px '+(parseInt(ti2.bottom)||0)+'px '+(parseInt(ti2.left)||0)+'px'; }
                } else if (el.type === 'image' && el.content.src) {
                    var img = document.createElement('img');
                    img.src = el.content.src; img.alt = el.content.alt || '';
                    img.style.cssText = 'width:100%;height:100%;object-fit:'+(el.content.objectFit||'cover')+';display:block;';
                    img.loading = 'lazy';
                    div.appendChild(img);
                } else if (el.type === 'shape') {
                    div.style.backgroundColor = el.content.fill || el.content.fillColor
                        || (el.content._v2style && el.content._v2style.fill ? el.content._v2style.fill.color : null)
                        || (el.style && el.style.fill ? el.style.fill.color : null)
                        || '#e5e7eb';
                }
                surface.appendChild(div);
            });
            article.appendChild(surface);
            bookArea.appendChild(article);
        });

        // Build TOC
        var tocContent = document.getElementById('toc-content');
        if (tocContent) {
            tocContent.innerHTML = '<h2>Contents</h2>';
            PAGES.forEach(function(page, i) {
                var item = document.createElement('div');
                item.className = 'toc-item';
                var ts = document.createElement('span'); ts.textContent = page.title || 'Page '+(i+1);
                var ns = document.createElement('span'); ns.className = 'num'; ns.textContent = String(i+1);
                item.appendChild(ts); item.appendChild(ns);
                item.onclick = function() { if(window.__fb) window.__fb.flipTo(i); toggleTOC(false); };
                tocContent.appendChild(item);
            });
        }

        // Load and init flipbook library
        var script = document.createElement('script');
        script.src = '/vendor/flipbook/flipbook.iife.js?v={{ time() }}';
        script.onload = function() {
            if (!window.EnsodoFlipbook) return;
            var fb = new EnsodoFlipbook.Flipbook(bookArea, {
                mode: 'realistic',
                aspect_ratio: PAGE_W + ':' + PAGE_H,
                flipping_time_ms: {{ $transitionSpeed ?: 800 }},
                show_cover: true,
                max_shadow_opacity: {{ (float)($s['max_shadow_opacity'] ?? 0.5) }},
                click_to_flip: true,
                swipe_to_flip: true,
                start_page: 0,
                responsive_breakpoint_px: {{ $mobileBreakpoint }},
                swipe_threshold_px: 30,
                pages: [],
            });
            window.__fb = fb;

            var arrL = document.getElementById('fb-arrow-l');
            var arrR = document.getElementById('fb-arrow-r');

            function updateNav() {
                var p = fb.getCurrentPage(), total = fb.getPageCount();
                if (arrL) arrL.disabled = p <= 0;
                if (arrR) arrR.disabled = p >= total - 2;
            }
            if (arrL) arrL.onclick = function() { fb.flipPrev(); };
            if (arrR) arrR.onclick = function() { fb.flipNext(); };
            fb.on('flip', updateNav);
            fb.on('ready', updateNav);

            // Also update the header bar indicator
            var headerInd = document.getElementById('page-indicator');
            if (headerInd) {
                fb.on('flip', function(e) { headerInd.textContent = (e.page+1)+' / '+fb.getPageCount(); });
                headerInd.textContent = '1 / ' + fb.getPageCount();
            }
        };
        document.head.appendChild(script);

        // Global nav functions for header buttons
        window.toggleFullscreen = function() {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(function(){});
            else document.exitFullscreen();
        };
        window.toggleTOC = function(show) {
            var el = document.getElementById('toc-overlay');
            if (el) el.classList.toggle('visible', show !== undefined ? show : !el.classList.contains('visible'));
        };
        window.toggleThumbs = function() {};
        window.nextPage = function() { if(window.__fb) window.__fb.flipNext(); };
        window.prevPage = function() { if(window.__fb) window.__fb.flipPrev(); };
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') toggleTOC(false);
            if (e.key === 'f') toggleFullscreen();
        });

        @if($autoHideUI)
        var uiTimer;
        function showUI() {
            document.body.classList.remove('ui-hidden');
            clearTimeout(uiTimer);
            uiTimer = setTimeout(function() { document.body.classList.add('ui-hidden'); }, 3000);
        }
        document.addEventListener('mousemove', showUI);
        document.addEventListener('touchstart', showUI, { passive: true });
        showUI();
        @endif
    })();
    </script>
@endif
</body>
</html>
