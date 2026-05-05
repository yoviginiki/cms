@php
    $mode = $data['mode'] ?? 'realistic';
    $aspect = $data['aspect_ratio'] ?? '2:3';
    $flipTime = (int) ($data['flipping_time_ms'] ?? 800);
    $showCover = (bool) ($data['show_cover'] ?? true);
    $maxShadow = (float) ($data['max_shadow_opacity'] ?? 0.5);
    $clickFlip = (bool) ($data['click_to_flip'] ?? true);
    $swipeFlip = (bool) ($data['swipe_to_flip'] ?? true);
    $startPage = (int) ($data['start_page'] ?? 0);
    $showNavBar = (bool) ($data['show_nav_bar'] ?? true);
    $showFullscreen = (bool) ($data['show_fullscreen'] ?? true);
    $showIndicator = (bool) ($data['show_page_indicator'] ?? true);
    $source = $data['source'] ?? 'pdf';
    $pdfAssetId = $data['pdf_asset_id'] ?? null;
    $pdfUrl = $data['pdf_url'] ?? ($pdfAssetId ? "/api/v1/assets/{$pdfAssetId}/serve" : '');
    $hasPdf = $source === 'pdf' && !empty($pdfUrl);
    $hasCategory = $source === 'category' && !empty($data['category_id']);

    // Fetch category posts if source is category
    $categoryPosts = [];
    if ($hasCategory) {
        $orderMap = [
            'date_desc' => ['published_at', 'desc'],
            'date_asc' => ['published_at', 'asc'],
            'title_asc' => ['title', 'asc'],
            'title_desc' => ['title', 'desc'],
        ];
        $order = $orderMap[$data['posts_order'] ?? 'date_desc'] ?? ['published_at', 'desc'];
        $limit = min((int) ($data['posts_limit'] ?? 50), 200);

        $siteId = $site->id ?? null;
        $query = \App\Models\Post::where('category_id', $data['category_id'])
            ->where('status', 'published');
        if ($siteId) $query->where('site_id', $siteId);
        $categoryPosts = $query->orderBy($order[0], $order[1])
            ->limit($limit)
            ->get();
    }

    $config = json_encode([
        'mode' => $mode,
        'aspect_ratio' => $aspect,
        'custom_width_px' => $data['custom_width_px'] ?? null,
        'custom_height_px' => $data['custom_height_px'] ?? null,
        'flipping_time_ms' => $flipTime,
        'show_cover' => $showCover,
        'max_shadow_opacity' => $maxShadow,
        'click_to_flip' => $clickFlip,
        'swipe_to_flip' => $swipeFlip,
        'start_page' => $startPage,
        'responsive_breakpoint_px' => 720,
        'swipe_threshold_px' => 30,
    ], JSON_UNESCAPED_SLASHES);

    $pages = $childrenArray ?? [];
    $uid = 'fb-' . substr(md5(uniqid()), 0, 8);
@endphp
@once
<link rel="stylesheet" href="/vendor/flipbook/flipbook.css">
<style>
/* No-JS fallback */
.ef-root:not(.ef-enhanced) .ef-page { position:relative; display:block; max-width:700px; margin:0 auto 2rem; padding:2rem; background:#fff; border:1px solid #e5e7eb; border-radius:0.5rem; }
.ef-root.ef-enhanced .ef-noscript-note { display:none; }

/* ─── Block flipbook wrapper ─── */
.efb-wrap { position:relative; background:#111; border-radius:8px; overflow:hidden; }
.efb-book { width:100%; }

/* Overlay arrows */
.efb-arrow {
    position:absolute; top:50%; transform:translateY(-50%);
    width:40px; height:40px; display:flex; align-items:center; justify-content:center;
    background:rgba(0,0,0,0.4); border:none; border-radius:50%;
    color:#fff; font-size:20px; cursor:pointer; z-index:30;
    opacity:0; transition:opacity 0.25s;
}
.efb-wrap:hover .efb-arrow { opacity:0.7; }
.efb-arrow:hover { opacity:1 !important; background:rgba(0,0,0,0.6); }
.efb-arrow:disabled { opacity:0 !important; cursor:default; }
.efb-arrow-l { left:10px; }
.efb-arrow-r { right:10px; }

/* Navigation bar */
.efb-nav {
    display:flex; align-items:center; justify-content:center;
    gap:12px; padding:8px 16px;
    background:rgba(0,0,0,0.7); color:rgba(255,255,255,0.8);
    font-size:13px;
}
.efb-nav button {
    background:none; border:none; color:rgba(255,255,255,0.7);
    cursor:pointer; padding:4px 8px; font-size:13px; border-radius:4px;
    transition:background 0.15s, color 0.15s;
}
.efb-nav button:hover { background:rgba(255,255,255,0.12); color:#fff; }
.efb-nav button:disabled { opacity:0.3; cursor:default; }
.efb-nav-indicator { font-variant-numeric:tabular-nums; min-width:70px; text-align:center; }

/* Fullscreen mode */
.efb-wrap:fullscreen, .efb-wrap:-webkit-full-screen {
    background:#000; border-radius:0; display:flex; flex-direction:column;
}
.efb-wrap:fullscreen .efb-book, .efb-wrap:-webkit-full-screen .efb-book { flex:1; }
</style>
@endonce
<div class="efb-wrap" id="{{ $uid }}" data-flipbook='{{ $config }}' @if($hasPdf) data-pdf-url="{{ $pdfUrl }}" @endif>
    <div class="efb-book" id="{{ $uid }}-book">
        @if($hasCategory)
            {{-- Category articles as pages --}}
            @foreach($categoryPosts as $index => $post)
            <article class="ef-page" data-page-index="{{ $index }}" role="region" aria-label="{{ $post->title }}">
                <div style="padding:2rem 2.5rem;background:#fff;height:100%;overflow:auto;box-sizing:border-box;">
                    <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:0.5rem;color:#1a1a1a;line-height:1.3;">{{ $post->title }}</h2>
                    @if($post->published_at)
                    <time style="font-size:0.75rem;color:#999;display:block;margin-bottom:1rem;" datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('M j, Y') }}</time>
                    @endif
                    @if($post->excerpt)
                    <p style="font-size:0.95rem;color:#555;margin-bottom:1rem;font-style:italic;">{{ $post->excerpt }}</p>
                    @endif
                    <div style="font-size:0.9rem;line-height:1.7;color:#333;" class="prose">
                        {!! $post->content !!}
                    </div>
                </div>
            </article>
            @endforeach
        @elseif(!$hasPdf)
            {{-- Child blocks as pages --}}
            @foreach($pages as $index => $pageHtml)
            <article class="ef-page" data-page-index="{{ $index }}" role="region" aria-label="Page {{ $index + 1 }}">
                {!! $pageHtml !!}
            </article>
            @endforeach
        @endif
    </div>

    {{-- Overlay arrows --}}
    <button class="efb-arrow efb-arrow-l" data-dir="prev" aria-label="Previous page">&#8249;</button>
    <button class="efb-arrow efb-arrow-r" data-dir="next" aria-label="Next page">&#8250;</button>

    {{-- Navigation bar --}}
    @if($showNavBar)
    <div class="efb-nav">
        <button data-action="prev" aria-label="Previous">&#8592;</button>
        @if($showIndicator)
        <span class="efb-nav-indicator" data-role="indicator">-</span>
        @endif
        <button data-action="next" aria-label="Next">&#8594;</button>
        @if($showFullscreen)
        <button data-action="fullscreen" aria-label="Fullscreen" style="margin-left:8px;">&#x26F6;</button>
        @endif
    </div>
    @endif
</div>
@once
<script defer src="/vendor/flipbook/flipbook.iife.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.efb-wrap[data-flipbook]').forEach(function(wrap) {
        var config = JSON.parse(wrap.dataset.flipbook);
        var bookEl = wrap.querySelector('.efb-book');
        var pdfUrl = wrap.dataset.pdfUrl || '';
        var arrowL = wrap.querySelector('.efb-arrow-l');
        var arrowR = wrap.querySelector('.efb-arrow-r');
        var navPrev = wrap.querySelector('[data-action="prev"]');
        var navNext = wrap.querySelector('[data-action="next"]');
        var indicator = wrap.querySelector('[data-role="indicator"]');
        var fsBtn = wrap.querySelector('[data-action="fullscreen"]');

        function initFlipbook() {
            if (!window.EnsodoFlipbook) return;
            config.pages = [];
            var fb = new EnsodoFlipbook.Flipbook(bookEl, config);
            bookEl.classList.add('ef-enhanced');

            function updateUI() {
                var p = fb.getCurrentPage(), t = fb.getPageCount();
                var atStart = p <= 0, atEnd = p >= t - 2;
                if (arrowL) arrowL.disabled = atStart;
                if (arrowR) arrowR.disabled = atEnd;
                if (navPrev) navPrev.disabled = atStart;
                if (navNext) navNext.disabled = atEnd;
                if (indicator) indicator.textContent = (p+1) + ' / ' + t;
            }
            fb.on('flip', updateUI);
            fb.on('ready', updateUI);

            if (arrowL) arrowL.onclick = function(e) { e.stopPropagation(); fb.flipPrev(); };
            if (arrowR) arrowR.onclick = function(e) { e.stopPropagation(); fb.flipNext(); };
            if (navPrev) navPrev.onclick = function() { fb.flipPrev(); };
            if (navNext) navNext.onclick = function() { fb.flipNext(); };
            if (fsBtn) fsBtn.onclick = function() {
                if (!document.fullscreenElement) wrap.requestFullscreen().catch(function(){});
                else document.exitFullscreen();
            };
        }

        if (pdfUrl) {
            // Load pdf.js (legacy build that exposes window.pdfjsLib)
            function loadPdfJs(cb) {
                if (window.pdfjsLib) return cb();
                var s = document.createElement('script');
                s.src = '/vendor/pdfjs/pdf.min.js';
                s.onload = cb;
                s.onerror = function() {
                    bookEl.innerHTML = '<p style="padding:2rem;color:#f66;text-align:center;">Failed to load PDF viewer</p>';
                };
                document.head.appendChild(s);
            }

            loadPdfJs(function() {
                pdfjsLib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.js';
                var loadingTask = pdfjsLib.getDocument(pdfUrl);
                loadingTask.promise.then(function(pdf) {
                    var promises = [];
                    for (var i = 1; i <= pdf.numPages; i++) {
                        promises.push(renderPage(pdf, i));
                    }
                    Promise.all(promises).then(function(canvases) {
                        bookEl.innerHTML = '';
                        canvases.forEach(function(canvas) {
                            var article = document.createElement('article');
                            article.className = 'ef-page';
                            article.style.background = '#fff';
                            canvas.style.cssText = 'width:100%;height:100%;object-fit:contain;display:block;';
                            article.appendChild(canvas);
                            bookEl.appendChild(article);
                        });
                        initFlipbook();
                    });
                }).catch(function(err) {
                    bookEl.innerHTML = '<p style="padding:2rem;color:#f66;text-align:center;">Failed to load PDF: ' + err.message + '</p>';
                });
            });

            function renderPage(pdf, num) {
                return pdf.getPage(num).then(function(page) {
                    var scale = 2;
                    var viewport = page.getViewport({ scale: scale });
                    var canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise.then(function() {
                        return canvas;
                    });
                });
            }
        } else {
            initFlipbook();
        }
    });
});
</script>
@endonce
