{{-- ═══════════════════════════════════════════════════════════════════════
  STILLO VIEWER — self-contained magazine reader (Viewer 2.0).
  ONE runtime, three consumers: authed preview, public viewer, ZIP export
  (all CSS/JS inline; only webfonts + remote embeds leave this file).

  Reader modes:  scroll (vertical) · book (page-flip) · presentation (fade)
  Publisher controls (viewerSettings): display_mode, bg_color, arrow_color,
  auto_hide_ui, show_page_numbers, side_banners[{side,src,href,alt}],
  audio{enabled,tracks[{src,title}]}.
  UX: top bar + controls fade when idle; fullscreen; keyboard + swipe;
  prefers-reduced-motion honored; first spread eager, rest lazy.
═══════════════════════════════════════════════════════════════════════ --}}
@php
    $vs = is_array($viewerSettings ?? null) ? $viewerSettings : [];
    $mode = in_array($vs['display_mode'] ?? '', ['scroll', 'book', 'presentation']) ? $vs['display_mode'] : 'book';
    $bg = preg_match('/^#[0-9a-fA-F]{3,8}$/', $vs['bg_color'] ?? '') ? $vs['bg_color'] : '#111110';
    $arrow = preg_match('/^#[0-9a-fA-F]{3,8}$/', $vs['arrow_color'] ?? '') ? $vs['arrow_color'] : '#E63B2E';
    $autoHide = ($vs['auto_hide_ui'] ?? true) !== false;
    $showNums = ($vs['show_page_numbers'] ?? true) !== false;
    $coverStandalone = ($coverMode ?? 'standalone') !== 'spread';
    $banners = collect(is_array($vs['side_banners'] ?? null) ? $vs['side_banners'] : [])
        ->filter(fn ($b) => is_array($b) && preg_match('#^https?://|^/#', $b['src'] ?? ''))
        ->map(fn ($b) => [
            'side' => ($b['side'] ?? 'right') === 'left' ? 'left' : 'right',
            'src' => $b['src'],
            'href' => preg_match('#^https?://#', $b['href'] ?? '') ? $b['href'] : null,
            'alt' => $b['alt'] ?? 'Advertisement',
        ])->values();
    $audio = is_array($vs['audio'] ?? null) ? $vs['audio'] : [];
    $audioTracks = collect(is_array($audio['tracks'] ?? null) ? $audio['tracks'] : [])
        ->filter(fn ($t) => is_array($t) && preg_match('#^https?://|^/#', $t['src'] ?? ''))
        ->map(fn ($t) => ['src' => $t['src'], 'title' => $t['title'] ?? 'Track'])->values();
    $audioOn = !empty($audio['enabled']) && $audioTracks->isNotEmpty();
    $allPages = collect($spreads)->flatMap(fn ($sp) => $sp['pages'])->sortBy('index')->values();
    $pw = (int) ($allPages[0]['width'] ?? 595);
    $ph = (int) ($allPages[0]['height'] ?? 842);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ e($issue['title'] ?? 'Magazine') }}</title>
@if(!empty($fontsUrl))
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="{{ $fontsUrl }}">
@endif
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --sv-bg: {{ $bg }}; --sv-arrow: {{ $arrow }}; --sv-pw: {{ $pw }}px; --sv-ph: {{ $ph }}px; }
html, body { height: 100%; }
body { background: var(--sv-bg); font-family: 'Inter', system-ui, sans-serif; overflow: hidden; }
.sv-page { position: relative; overflow: hidden; flex: 0 0 auto; }
.sv-frame { position: absolute; overflow: hidden; }
.sv-frame img { display: block; max-width: 100%; }

/* ── top bar ── */
#sv-top {
    position: fixed; top: 0; left: 0; right: 0; z-index: 60;
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 18px; color: #fff; font-size: 13px;
    background: linear-gradient(rgba(0,0,0,0.55), transparent);
    transition: opacity .45s, transform .45s; pointer-events: none;
}
#sv-top .t { font-weight: 600; letter-spacing: .04em; text-shadow: 0 1px 4px rgba(0,0,0,.6); }
#sv-top .s { opacity: .55; font-size: 11px; }
body.sv-idle #sv-top, body.sv-started #sv-top { opacity: 0; transform: translateY(-8px); }
body.sv-started:not(.sv-idle):hover #sv-top { opacity: 1; transform: none; }

/* ── stage ── */
#sv-stage { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
#sv-book { position: relative; perspective: 2400px; display: flex; }
.sv-mode-scroll #sv-stage { overflow-y: auto; display: block; }
.sv-mode-scroll #sv-book { flex-direction: column; align-items: center; gap: 28px; padding: 60px 0 100px; perspective: none; margin: 0 auto; width: fit-content; }
.sv-mode-book .sv-page, .sv-mode-presentation .sv-page { box-shadow: 0 8px 40px rgba(0,0,0,.5); }
.sv-mode-scroll .sv-page { box-shadow: 0 4px 24px rgba(0,0,0,.45); }

/* book flip */
#sv-flip {
    position: absolute; top: 0; width: 50%; height: 100%; z-index: 40;
    transform-style: preserve-3d; pointer-events: none; display: none;
}
#sv-flip .ff, #sv-flip .fb { position: absolute; inset: 0; backface-visibility: hidden; overflow: hidden; background: #fff; }
#sv-flip .fb { transform: rotateY(180deg); }
#sv-flip.go-next { right: 0; transform-origin: left center; display: block; animation: svFlipN .65s ease-in-out forwards; }
#sv-flip.go-prev { left: 0; transform-origin: right center; display: block; animation: svFlipP .65s ease-in-out forwards; }
@keyframes svFlipN { from { transform: rotateY(0); } to { transform: rotateY(-180deg); } }
@keyframes svFlipP { from { transform: rotateY(0); } to { transform: rotateY(180deg); } }
.sv-mode-presentation .sv-pair { animation: svFade .4s ease; }
@keyframes svFade { from { opacity: 0; } to { opacity: 1; } }
@media (prefers-reduced-motion: reduce) {
  #sv-flip.go-next, #sv-flip.go-prev { animation-duration: .01s; }
  .sv-mode-presentation .sv-pair { animation: none; }
  #sv-top, #sv-ctl { transition: none; }
}

/* ── controls ── */
#sv-ctl {
    position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%); z-index: 60;
    display: flex; align-items: center; gap: 10px;
    background: rgba(0,0,0,.72); backdrop-filter: blur(10px);
    padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(255,255,255,.12);
    transition: opacity .45s, transform .45s;
}
body.sv-idle #sv-ctl { opacity: 0; transform: translate(-50%, 10px); pointer-events: none; }
#sv-ctl button {
    background: none; border: 0; cursor: pointer; color: var(--sv-arrow);
    width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
    border-radius: 50%; transition: background .15s;
}
#sv-ctl button:hover { background: rgba(255,255,255,.12); }
#sv-ctl button.mode { color: rgba(255,255,255,.45); width: 26px; height: 26px; }
#sv-ctl button.mode.on { color: var(--sv-arrow); }
#sv-ctl svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
#sv-info { color: rgba(255,255,255,.75); font-size: 11.5px; min-width: 58px; text-align: center; letter-spacing: .06em; }
.sv-sep { width: 1px; height: 18px; background: rgba(255,255,255,.15); }

/* ── side banners ── */
.sv-banner { position: fixed; top: 50%; transform: translateY(-50%); z-index: 50; display: flex; flex-direction: column; gap: 14px; }
.sv-banner.left { left: 16px; } .sv-banner.right { right: 16px; }
.sv-banner img { max-width: 160px; max-height: 70vh; display: block; box-shadow: 0 6px 24px rgba(0,0,0,.45); }
.sv-banner a { display: block; }
@media (max-width: 1180px) { .sv-banner { display: none; } }

/* ── audio ── */
#sv-audio {
    position: fixed; bottom: 18px; left: 18px; z-index: 60;
    display: flex; align-items: center; gap: 8px;
    background: rgba(0,0,0,.72); backdrop-filter: blur(10px);
    padding: 7px 12px; border-radius: 999px; border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.8); font-size: 11px; max-width: 260px;
    transition: opacity .45s;
}
body.sv-idle #sv-audio { opacity: .25; }
#sv-audio button { background: none; border: 0; color: var(--sv-arrow); cursor: pointer; width: 24px; height: 24px; }
#sv-audio .tt { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }
</style>
</head>
<body class="sv-mode-{{ $mode }}">
<div id="sv-top">
    <div><span class="t">{{ e($issue['title'] ?? '') }}</span>
        @if(!empty($issue['subtitle']))<span class="s"> — {{ e($issue['subtitle']) }}</span>@endif
    </div>
    @if($showNums)<div class="s" id="sv-top-info"></div>@endif
</div>

@foreach(['left', 'right'] as $side)
    @php $sideB = $banners->where('side', $side); @endphp
    @if($sideB->isNotEmpty())
    <div class="sv-banner {{ $side }}">
        @foreach($sideB as $b)
            @if($b['href'])
                <a href="{{ e($b['href']) }}" target="_blank" rel="noopener sponsored"><img src="{{ e($b['src']) }}" alt="{{ e($b['alt']) }}" loading="lazy"></a>
            @else
                <img src="{{ e($b['src']) }}" alt="{{ e($b['alt']) }}" loading="lazy">
            @endif
        @endforeach
    </div>
    @endif
@endforeach

<div id="sv-stage"><div id="sv-book"></div></div>

{{-- all pages rendered ONCE; JS moves them between layouts (scroll readable without JS) --}}
<noscript><style>#sv-pages{display:block!important} .sv-page{margin:20px auto}</style></noscript>
<div id="sv-pages" style="display:none">
@foreach($allPages as $p)
    <div class="sv-page" data-idx="{{ $p['index'] }}" style="{{ $p['style'] }}">
        @foreach($p['frames'] as $f)
            <div class="sv-frame" style="{{ $f['style'] }}">{!! $f['html'] !!}</div>
        @endforeach
    </div>
@endforeach
</div>

<div id="sv-ctl">
    <button id="sv-prev" title="Previous page (←)"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
    <span id="sv-info"></span>
    <button id="sv-next" title="Next page (→)"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
    <span class="sv-sep"></span>
    <button class="mode" data-mode="scroll" title="Vertical scroll"><svg viewBox="0 0 24 24"><path d="M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4"/></svg></button>
    <button class="mode" data-mode="book" title="Book (page flip)"><svg viewBox="0 0 24 24"><path d="M12 5c-2-1.5-5-2-8-2v16c3 0 6 .5 8 2 2-1.5 5-2 8-2V3c-3 0-6 .5-8 2zM12 5v16"/></svg></button>
    <button class="mode" data-mode="presentation" title="Presentation"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="12" rx="1"/><path d="M12 17v3M8 21h8"/></svg></button>
    <span class="sv-sep"></span>
    <button id="sv-fs" title="Fullscreen (F)"><svg viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button>
</div>

@if($audioOn)
<div id="sv-audio">
    <button id="sv-au-play" title="Play / pause">
        <svg id="sv-au-i-play" viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;stroke:none"><path d="M7 4l12 8-12 8z"/></svg>
        <svg id="sv-au-i-pause" viewBox="0 0 24 24" style="display:none;width:16px;height:16px;fill:currentColor;stroke:none"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>
    </button>
    <button id="sv-au-next" title="Next track"><svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;stroke:none"><path d="M5 4l10 8-10 8zM17 4h2v16h-2z"/></svg></button>
    <span class="tt" id="sv-au-title"></span>
    <audio id="sv-au"></audio>
</div>
@endif

<script>
(function () {
    'use strict';
    var pages = Array.prototype.slice.call(document.querySelectorAll('#sv-pages .sv-page'));
    var book = document.getElementById('sv-book');
    var stage = document.getElementById('sv-stage');
    var total = pages.length;
    var coverStandalone = {{ $coverStandalone ? 'true' : 'false' }};
    var mode = document.body.className.replace('sv-mode-', '') || 'book';
    var cur = 0; // current page index (left-most visible)
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // first spread eager, rest lazy (audit M-G)
    pages.slice(0, 2).forEach(function (p) {
        p.querySelectorAll('img[loading="lazy"]').forEach(function (im) { im.loading = 'eager'; });
    });

    function pairFor(i) { // [left, right|null] page indices for book/presentation
        if (coverStandalone) {
            if (i === 0) return [0, null];
            var s = i % 2 === 0 ? i - 1 : i;
            return [s, s + 1 < total ? s + 1 : null];
        }
        var st = i % 2 === 0 ? i : i - 1;
        return [st, st + 1 < total ? st + 1 : null];
    }

    function fitScale(nPages) {
        var w = {{ $pw }} * nPages, h = {{ $ph }};
        var margin = 90;
        return Math.min((window.innerWidth - margin) / w, (window.innerHeight - margin) / h, 1.4);
    }

    function render() {
        book.innerHTML = '';
        document.body.className = 'sv-mode-' + mode + (document.body.classList.contains('sv-idle') ? ' sv-idle' : '') + (document.body.classList.contains('sv-started') ? ' sv-started' : '');
        document.querySelectorAll('#sv-ctl .mode').forEach(function (b) { b.classList.toggle('on', b.dataset.mode === mode); });
        if (mode === 'scroll') {
            var sc = fitScale(1);
            pages.forEach(function (p) {
                p.style.transformOrigin = 'top center';
                p.style.transform = sc < 1 ? 'scale(' + sc + ')' : '';
                p.style.margin = sc < 1 ? '0 0 ' + (({{ $ph }} * (sc - 1)) | 0) + 'px' : '';
                book.appendChild(p);
            });
            var target = pages[cur];
            if (target) setTimeout(function () { target.scrollIntoView({ block: 'start', behavior: reduced ? 'auto' : 'smooth' }); }, 30);
        } else {
            var pair = pairFor(cur);
            cur = pair[0];
            var wrap = document.createElement('div');
            wrap.className = 'sv-pair';
            wrap.style.display = 'flex';
            var n = pair[1] === null ? 1 : 2;
            var sc2 = fitScale(n);
            wrap.style.transform = 'scale(' + sc2 + ')';
            wrap.appendChild(pages[pair[0]]);
            if (pair[1] !== null) wrap.appendChild(pages[pair[1]]);
            book.appendChild(wrap);
        }
        var label = mode === 'scroll' ? (cur + 1) + ' / ' + total
            : (function () { var p = pairFor(cur); return (p[1] === null ? (p[0] + 1) : (p[0] + 1) + '–' + (p[1] + 2 <= total ? p[1] + 1 : total)) + ' / ' + total; })();
        var info = document.getElementById('sv-info');
        if (info) info.textContent = label;
        var ti = document.getElementById('sv-top-info');
        if (ti) ti.textContent = label;
    }

    function step(dir) {
        if (mode === 'scroll') {
            cur = Math.max(0, Math.min(total - 1, cur + dir));
            var t = pages[cur];
            if (t) t.scrollIntoView({ block: 'start', behavior: reduced ? 'auto' : 'smooth' });
            var info = document.getElementById('sv-info');
            if (info) info.textContent = (cur + 1) + ' / ' + total;
            return;
        }
        var pair = pairFor(cur);
        var nextStart = dir > 0 ? (pair[1] === null ? pair[0] + 1 : pair[1] + 1) : (coverStandalone && pair[0] === 1 ? 0 : pair[0] - 2);
        if (nextStart < 0 || nextStart >= total) return;
        if (mode === 'book' && !reduced) flipTo(nextStart, dir);
        else { cur = nextStart; render(); }
    }

    function flipTo(nextStart, dir) {
        // page-flip: snapshot the turning half, rotate about the spine
        var pairEl = book.querySelector('.sv-pair');
        if (!pairEl) { cur = nextStart; render(); return; }
        var flip = document.createElement('div');
        flip.id = 'sv-flip';
        var pair = pairFor(cur);
        var frontIdx = dir > 0 ? pair[1] : pair[0];
        var np = pairFor(nextStart);
        var backIdx = dir > 0 ? np[0] : (np[1] !== null ? np[1] : np[0]);
        if (frontIdx === null) { cur = nextStart; render(); return; }
        var ff = document.createElement('div'); ff.className = 'ff';
        var fb = document.createElement('div'); fb.className = 'fb';
        ff.appendChild(pages[frontIdx].cloneNode(true));
        if (backIdx !== null && backIdx !== undefined) fb.appendChild(pages[backIdx].cloneNode(true));
        flip.appendChild(ff); flip.appendChild(fb);
        pairEl.appendChild(flip);
        flip.className = dir > 0 ? 'go-next' : 'go-prev';
        flip.addEventListener('animationend', function () { cur = nextStart; render(); }, { once: true });
        setTimeout(function () { if (document.getElementById('sv-flip')) { cur = nextStart; render(); } }, 900);
    }

    // controls
    document.getElementById('sv-prev').addEventListener('click', function () { step(-1); });
    document.getElementById('sv-next').addEventListener('click', function () { step(1); });
    document.querySelectorAll('#sv-ctl .mode').forEach(function (b) {
        b.addEventListener('click', function () { mode = b.dataset.mode; render(); });
    });
    document.getElementById('sv-fs').addEventListener('click', function () {
        if (document.fullscreenElement) document.exitFullscreen();
        else document.documentElement.requestFullscreen && document.documentElement.requestFullscreen();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') { e.preventDefault(); step(1); }
        if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); step(-1); }
        if (e.key === 'Home') { cur = 0; render(); }
        if (e.key === 'End') { cur = total - 1; render(); }
        if (e.key === 'f' || e.key === 'F') document.getElementById('sv-fs').click();
    });
    // touch swipe
    var tx = null;
    document.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; }, { passive: true });
    document.addEventListener('touchend', function (e) {
        if (tx === null) return;
        var dx = e.changedTouches[0].clientX - tx;
        if (Math.abs(dx) > 48 && mode !== 'scroll') step(dx < 0 ? 1 : -1);
        tx = null;
    }, { passive: true });

    // idle fade (auto_hide_ui) + top bar fades after start
    var autoHide = {{ $autoHide ? 'true' : 'false' }};
    var idleT = null;
    function wake() {
        document.body.classList.remove('sv-idle');
        if (!autoHide) return;
        clearTimeout(idleT);
        idleT = setTimeout(function () { document.body.classList.add('sv-idle'); }, 2600);
    }
    ['mousemove', 'touchstart', 'keydown', 'click'].forEach(function (ev) {
        document.addEventListener(ev, wake, { passive: true });
    });
    wake();
    setTimeout(function () { document.body.classList.add('sv-started'); }, 2800);

    // audio playlist
    @if($audioOn)
    (function () {
        var tracks = @json($audioTracks);
        var i = 0, el = document.getElementById('sv-au');
        var title = document.getElementById('sv-au-title');
        var ip = document.getElementById('sv-au-i-play'), ipa = document.getElementById('sv-au-i-pause');
        function load(n) { i = (n + tracks.length) % tracks.length; el.src = tracks[i].src; title.textContent = tracks[i].title; }
        function sync() { var on = !el.paused; ip.style.display = on ? 'none' : ''; ipa.style.display = on ? '' : 'none'; }
        document.getElementById('sv-au-play').addEventListener('click', function () { if (el.paused) el.play(); else el.pause(); });
        document.getElementById('sv-au-next').addEventListener('click', function () { var was = !el.paused; load(i + 1); if (was) el.play(); });
        el.addEventListener('ended', function () { load(i + 1); el.play(); });
        el.addEventListener('play', sync); el.addEventListener('pause', sync);
        load(0); sync();
    })();
    @endif

    window.addEventListener('resize', render);
    render();
})();
</script>
</body>
</html>
