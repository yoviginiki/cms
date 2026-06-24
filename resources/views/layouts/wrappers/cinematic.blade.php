{{-- Cinematic layout: tall scrollable page, ScrollTrigger drives everything --}}
<div class="cinematic-wrapper">
    {!! $blocksHtml !!}
</div>
<style>
/* ─── Cinematic Layout Core ─── */
html.cinematic-page { scroll-behavior: auto; }
body.cinematic-page { margin: 0; }

/* Hide chrome */
.cinematic-page header[role="banner"],
.cinematic-page footer[role="contentinfo"],
.cinematic-page .site-nav { display: none !important; }

/* Wrapper: normal flow (NOT overflow:hidden) */
.cinematic-wrapper { position: relative; width: 100%; }

/* Each section = full viewport panel in normal flow */
.cinematic-wrapper > .section-block {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 0 !important;
    margin: 0 !important;
    position: relative;
    overflow: visible !important;
    /* BUG B: ensure no ancestor breaks ScrollTrigger pin */
    transform: none !important;
    will-change: auto !important;
}

/* BUG C: Reserve space for media to prevent reflow */
.cinematic-wrapper img {
    aspect-ratio: 16/10;
    object-fit: cover;
    width: 100%;
    height: auto;
}
.cinematic-wrapper video {
    aspect-ratio: 16/9;
    object-fit: cover;
    width: 100%;
}
.cinematic-wrapper .video-hero img,
.cinematic-wrapper .video-hero video {
    aspect-ratio: auto;
}
/* scroll-gallery: small ellipse images, not full-width */
.cinematic-wrapper .section-block[data-scene="scroll-gallery"] img {
    width: 220px !important;
    height: 300px !important;
    aspect-ratio: auto !important;
    object-fit: cover;
    border-radius: 110px / 150px;
    margin: 0 auto 1.5rem;
    display: block;
}

/* Inner content div — readable padding, centered */
.cinematic-wrapper > .section-block > div {
    padding: 3rem 4rem !important;
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
    position: relative;
    z-index: 1;
}

/* Hero section (first) — no padding, video fills */
.cinematic-wrapper > .section-block:first-child > div {
    padding: 0 !important;
    height: 100%;
}

/* Video hero capsule */
.cinematic-wrapper .video-hero {
    min-height: 85vh !important;
    margin: auto 3vw !important;
    width: calc(100% - 6vw) !important;
}

/* Hide spacers */
.cinematic-wrapper > .spacer-block { display: none !important; }

/* ─── Nav dots (driven by JS) ─── */
.cinematic-nav {
    position: fixed;
    right: 24px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.cinematic-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: var(--color-text-muted, #999);
    opacity: 0.25;
    border: none; cursor: pointer; padding: 0;
    transition: opacity 0.4s, transform 0.4s, background 0.4s;
}
.cinematic-dot.is-active {
    opacity: 1;
    transform: scale(1.5);
    background: var(--color-primary, #358733);
}
.cinematic-dot:hover { opacity: 0.6; }

/* ─── ScrollTrigger pin fix: ensure no ancestor breaks fixed positioning ─── */
.cinematic-wrapper > .section-block.pin-spacer,
.cinematic-wrapper .pin-spacer { /* ScrollTrigger adds this */ }

/* ─── Reduced motion ─── */
@media (prefers-reduced-motion: reduce) {
    .cinematic-wrapper > .section-block * {
        opacity: 1 !important;
        transform: none !important;
        transition: none !important;
        animation: none !important;
        clip-path: none !important;
    }
    .cinematic-nav { display: none; }
}

/* ─── Mobile ─── */
@media (max-width: 768px) {
    .cinematic-wrapper > .section-block > div { padding: 2rem 1.5rem !important; }
    .cinematic-wrapper > .section-block:first-child > div { padding: 0 !important; }
    .cinematic-wrapper .video-hero {
        border-radius: 200px !important;
        margin: auto 2vw !important;
        width: calc(100% - 4vw) !important;
        min-height: 75vh !important;
    }
    .cinematic-nav { right: 12px; gap: 8px; }
    .cinematic-dot { width: 8px; height: 8px; }
    .cinematic-wrapper .section-block[data-scene="scroll-gallery"] img {
        width: 160px !important;
        height: 220px !important;
        border-radius: 80px / 110px;
    }
}
</style>
<script>
// Cinematic layout init: mark body, build nav dots, scroll-to-section on dot click
(function(){
  // ?experience=force clears the off-toggle
  if (window.location.search.indexOf('experience=force') !== -1) {
    localStorage.removeItem('ensodo:experience:off');
  }

  // Reduced motion → just show everything
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  // User disabled → show re-enable button, then bail
  if (localStorage.getItem('ensodo:experience:off') === '1') {
    var btn = document.createElement('button');
    btn.className = 'cinematic-dot'; // reuse dot styling
    btn.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);width:auto;height:auto;border-radius:4px;padding:6px 16px;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;opacity:1;background:var(--color-bg-alt,#eee);color:var(--color-text,#333);z-index:9999;cursor:pointer;';
    btn.textContent = 'Enable cinematic';
    btn.onclick = function(){ localStorage.removeItem('ensodo:experience:off'); location.reload(); };
    document.addEventListener('DOMContentLoaded', function(){ document.body.appendChild(btn); });
    return;
  }

  document.documentElement.classList.add('cinematic-page');
  document.body.classList.add('cinematic-page');

  var sections = document.querySelectorAll('.cinematic-wrapper > .section-block');
  if (sections.length < 2) return;

  // Nav dots
  var nav = document.createElement('nav');
  nav.className = 'cinematic-nav';
  nav.setAttribute('aria-label', 'Section navigation');
  sections.forEach(function(_, i) {
    var dot = document.createElement('button');
    dot.className = 'cinematic-dot' + (i === 0 ? ' is-active' : '');
    dot.setAttribute('aria-label', 'Go to section ' + (i + 1));
    dot.dataset.index = String(i);
    nav.appendChild(dot);
  });
  document.body.appendChild(nav);

  // Click dot → scroll to section
  nav.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-index]');
    if (btn) {
      var idx = Number(btn.dataset.index);
      sections[idx].scrollIntoView({ behavior: 'smooth' });
    }
  });

  // Update dots on scroll (simple IntersectionObserver)
  var dots = nav.querySelectorAll('.cinematic-dot');
  var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting && entry.intersectionRatio > 0.3) {
        var idx = Array.from(sections).indexOf(entry.target);
        if (idx >= 0) {
          dots.forEach(function(d, i) { d.classList.toggle('is-active', i === idx); });
        }
      }
    });
  }, { threshold: [0.3, 0.5] });
  sections.forEach(function(s) { observer.observe(s); });

  // ─── Media-load refresh: tell ScrollTrigger to recalculate after all media loads ───
  var refreshTimer;
  function debouncedRefresh() {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(function() {
      if (window.ScrollTrigger) window.ScrollTrigger.refresh();
    }, 200);
  }

  // Video loadeddata
  document.querySelectorAll('video').forEach(function(v) {
    v.addEventListener('loadeddata', debouncedRefresh);
  });

  // Image load
  document.querySelectorAll('img[loading="lazy"], img').forEach(function(img) {
    if (img.complete) return;
    img.addEventListener('load', debouncedRefresh);
  });

  // Fonts
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(debouncedRefresh);
  }

  // Window load as final fallback
  window.addEventListener('load', function() {
    setTimeout(debouncedRefresh, 300);
  });
})();
</script>
