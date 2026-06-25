/**
 * Ensodo CMS — Global Custom Cursor (10 presets)
 * Standalone, no dependencies. Reads config from #cursor-config JSON.
 *
 * Presets:
 *  1. dot-ring      — small dot + trailing ring outline
 *  2. circle-dot    — filled circle with centered dot inside
 *  3. minimal       — tiny dot only
 *  4. crosshair     — thin cross lines
 *  5. ring-only     — ring outline, no dot
 *  6. glow          — soft radial glow that follows cursor
 *  7. spotlight      — large transparent circle, inverts content
 *  8. dash-ring     — dashed ring + dot
 *  9. square        — rounded square outline + dot
 * 10. arrow-dot     — keep native cursor, add trailing dot
 */
(function () {
  'use strict';

  if (!window.matchMedia('(pointer: fine)').matches) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var configEl = document.getElementById('cursor-config');
  if (!configEl) return;
  var cfg;
  try { cfg = JSON.parse(configEl.textContent); } catch (e) { return; }
  if (!cfg || !cfg.enabled) return;

  var preset = cfg.preset || 'dot-ring';
  var color = cfg.color || 'var(--color-text, #201F1D)';
  var ringColor = cfg.ringColor || 'var(--color-text-muted, #7D7B7A)';
  var blend = cfg.blend || 'normal';
  var size = cfg.size || 'md';

  var sizes = {
    sm: { dot: 4, ring: 24, hoverRing: 40, border: 1 },
    md: { dot: 6, ring: 36, hoverRing: 56, border: 1 },
    lg: { dot: 8, ring: 48, hoverRing: 72, border: 1.5 }
  };
  var s = sizes[size] || sizes.md;

  // Presets define: html (inner DOM), css (styles), hasRing (trailing element), hideNative
  var presets = {
    'dot-ring': {
      html: '<span class="c-dot"></span><span class="c-ring"></span>',
      css: '.c-dot{position:absolute;width:' + s.dot + 'px;height:' + s.dot + 'px;border-radius:50%;background:' + color + ';transform:translate(-50%,-50%)}' +
           '.c-ring{position:absolute;width:' + s.ring + 'px;height:' + s.ring + 'px;border-radius:50%;border:' + s.border + 'px solid ' + ringColor + ';opacity:0.4;transform:translate(-50%,-50%);transition:width 0.25s,height 0.25s,opacity 0.25s}',
      hasRing: true, hideNative: true
    },
    'circle-dot': {
      html: '<span class="c-ring"></span><span class="c-dot"></span>',
      css: '.c-dot{position:absolute;width:' + s.dot + 'px;height:' + s.dot + 'px;border-radius:50%;background:' + color + ';transform:translate(-50%,-50%)}' +
           '.c-ring{position:absolute;width:' + s.ring + 'px;height:' + s.ring + 'px;border-radius:50%;background:' + ringColor + ';opacity:0.12;transform:translate(-50%,-50%);transition:width 0.25s,height 0.25s,opacity 0.25s}',
      hasRing: true, hideNative: true
    },
    'minimal': {
      html: '<span class="c-dot"></span>',
      css: '.c-dot{position:absolute;width:' + s.dot + 'px;height:' + s.dot + 'px;border-radius:50%;background:' + color + ';transform:translate(-50%,-50%);transition:width 0.2s,height 0.2s}',
      hasRing: false, hideNative: true
    },
    'crosshair': {
      html: '<span class="c-dot"></span>',
      css: '.c-dot{position:absolute;width:1px;height:24px;background:' + color + ';opacity:0.5;transform:translate(-50%,-50%);border-radius:0}' +
           '.c-dot::after{content:"";position:absolute;width:24px;height:1px;background:' + color + ';top:50%;left:50%;transform:translate(-50%,-50%)}',
      hasRing: false, hideNative: true
    },
    'ring-only': {
      html: '<span class="c-ring"></span>',
      css: '.c-ring{position:absolute;width:' + s.ring + 'px;height:' + s.ring + 'px;border-radius:50%;border:' + s.border + 'px solid ' + color + ';opacity:0.5;transform:translate(-50%,-50%);transition:width 0.3s,height 0.3s,opacity 0.3s,border-color 0.3s}',
      hasRing: false, ringIsDot: true, hideNative: true
    },
    'glow': {
      html: '<span class="c-dot"></span>',
      css: '.c-dot{position:absolute;width:' + (s.ring * 2) + 'px;height:' + (s.ring * 2) + 'px;border-radius:50%;background:radial-gradient(circle,' + color + ' 0%,transparent 70%);opacity:0.15;transform:translate(-50%,-50%);transition:width 0.3s,height 0.3s,opacity 0.3s}',
      hasRing: false, hideNative: true
    },
    'spotlight': {
      html: '<span class="c-dot"></span>',
      css: '.c-dot{position:absolute;width:120px;height:120px;border-radius:50%;border:2px solid ' + color + ';opacity:0.12;transform:translate(-50%,-50%);transition:width 0.4s,height 0.4s,opacity 0.4s}',
      hasRing: false, hideNative: false
    },
    'dash-ring': {
      html: '<span class="c-dot"></span><span class="c-ring"></span>',
      css: '.c-dot{position:absolute;width:' + s.dot + 'px;height:' + s.dot + 'px;border-radius:50%;background:' + color + ';transform:translate(-50%,-50%)}' +
           '.c-ring{position:absolute;width:' + s.ring + 'px;height:' + s.ring + 'px;border-radius:50%;border:' + s.border + 'px dashed ' + ringColor + ';opacity:0.35;transform:translate(-50%,-50%);transition:width 0.25s,height 0.25s,opacity 0.25s}',
      hasRing: true, hideNative: true, spin: true
    },
    'square': {
      html: '<span class="c-dot"></span><span class="c-ring"></span>',
      css: '.c-dot{position:absolute;width:' + s.dot + 'px;height:' + s.dot + 'px;border-radius:50%;background:' + color + ';transform:translate(-50%,-50%)}' +
           '.c-ring{position:absolute;width:' + s.ring + 'px;height:' + s.ring + 'px;border-radius:4px;border:' + s.border + 'px solid ' + ringColor + ';opacity:0.35;transform:translate(-50%,-50%) rotate(0deg);transition:width 0.25s,height 0.25s,opacity 0.25s,border-radius 0.25s,transform 0.25s}',
      hasRing: true, hideNative: true
    },
    'arrow-dot': {
      html: '<span class="c-dot"></span>',
      css: '.c-dot{position:absolute;width:' + (s.dot + 2) + 'px;height:' + (s.dot + 2) + 'px;border-radius:50%;background:' + color + ';opacity:0.6;transform:translate(-50%,-50%);transition:opacity 0.2s}',
      hasRing: false, hideNative: false, lagDot: true
    }
  };

  var p = presets[preset] || presets['dot-ring'];

  // Inject CSS
  var css = document.createElement('style');
  var baseCss = '.custom-cursor{position:fixed;top:0;left:0;z-index:99998;pointer-events:none;' +
    (blend !== 'normal' ? 'mix-blend-mode:' + blend + ';' : '') + '}';
  var hideCss = p.hideNative ? 'html.custom-cursor-active,html.custom-cursor-active *{cursor:none !important}' : '';
  var motionCss = '@media(prefers-reduced-motion:reduce){.custom-cursor{display:none !important}html.custom-cursor-active,html.custom-cursor-active *{cursor:auto !important}}';
  var mobileCss = '@media(max-width:768px){.custom-cursor{display:none !important}html.custom-cursor-active,html.custom-cursor-active *{cursor:auto !important}}';
  var spinCss = p.spin ? '@keyframes cursor-spin{from{transform:translate(-50%,-50%) rotate(0deg)}to{transform:translate(-50%,-50%) rotate(360deg)}}.c-ring{animation:cursor-spin 4s linear infinite}' : '';
  css.textContent = hideCss + baseCss + p.css + spinCss + motionCss + mobileCss;
  document.head.appendChild(css);

  // Build DOM
  var el = document.createElement('div');
  el.className = 'custom-cursor';
  el.innerHTML = p.html;
  document.body.appendChild(el);
  document.documentElement.classList.add('custom-cursor-active');

  var dot = el.querySelector('.c-dot');
  var ring = el.querySelector('.c-ring');
  var mx = -100, my = -100, cx = -100, cy = -100;
  var lerp = cfg.lagWeight || 0.08;

  // Dot movement
  if (p.lagDot) {
    // arrow-dot: dot trails with lag, no instant follow
    var dx = -100, dy = -100;
    document.addEventListener('mousemove', function (e) { mx = e.clientX; my = e.clientY; });
    (function tickDot() {
      dx += (mx - dx) * 0.12;
      dy += (my - dy) * 0.12;
      if (dot) dot.style.transform = 'translate(-50%,-50%) translate(' + Math.round(dx * 10) / 10 + 'px,' + Math.round(dy * 10) / 10 + 'px)';
      requestAnimationFrame(tickDot);
    })();
  } else {
    document.addEventListener('mousemove', function (e) {
      mx = e.clientX; my = e.clientY;
      if (dot) dot.style.transform = 'translate(-50%,-50%) translate(' + mx + 'px,' + my + 'px)';
    });
  }

  // Ring trails with lerp (for presets that have a separate ring)
  if (p.hasRing && ring) {
    (function tickRing() {
      cx += (mx - cx) * lerp;
      cy += (my - cy) * lerp;
      var t = 'translate(-50%,-50%) translate(' + Math.round(cx * 10) / 10 + 'px,' + Math.round(cy * 10) / 10 + 'px)';
      if (p.spin) t += ' rotate(' + (Date.now() / 11 % 360) + 'deg)';
      ring.style.transform = t;
      requestAnimationFrame(tickRing);
    })();
  }
  // ring-only: ring IS the cursor, follows with slight lag
  if (p.ringIsDot && ring) {
    (function tickRingDot() {
      cx += (mx - cx) * 0.15;
      cy += (my - cy) * 0.15;
      ring.style.transform = 'translate(-50%,-50%) translate(' + Math.round(cx * 10) / 10 + 'px,' + Math.round(cy * 10) / 10 + 'px)';
      requestAnimationFrame(tickRingDot);
    })();
    document.addEventListener('mousemove', function (e) { mx = e.clientX; my = e.clientY; });
  }

  // Hover effects
  var interactive = 'a, button, [role="button"], summary, input, select, textarea, label';

  document.addEventListener('mouseenter', function (e) {
    if (!e.target.matches || !e.target.matches(interactive)) return;
    if (ring && (p.hasRing || p.ringIsDot)) {
      ring.style.width = s.hoverRing + 'px';
      ring.style.height = s.hoverRing + 'px';
      ring.style.opacity = p.ringIsDot ? '0.3' : '0.15';
      if (preset === 'square') ring.style.borderRadius = '8px';
    }
    if (preset === 'minimal' && dot) { dot.style.width = (s.dot * 3) + 'px'; dot.style.height = (s.dot * 3) + 'px'; }
    if (preset === 'glow' && dot) { dot.style.opacity = '0.3'; dot.style.width = (s.ring * 3) + 'px'; dot.style.height = (s.ring * 3) + 'px'; }
    if (preset === 'spotlight' && dot) { dot.style.width = '160px'; dot.style.height = '160px'; dot.style.opacity = '0.2'; }
    if (preset === 'crosshair' && dot) { dot.style.opacity = '1'; }
  }, true);

  document.addEventListener('mouseleave', function (e) {
    if (!e.target.matches || !e.target.matches(interactive)) return;
    if (ring && (p.hasRing || p.ringIsDot)) {
      ring.style.width = s.ring + 'px';
      ring.style.height = s.ring + 'px';
      ring.style.opacity = p.ringIsDot ? '0.5' : '0.4';
      if (preset === 'square') ring.style.borderRadius = '4px';
    }
    if (preset === 'minimal' && dot) { dot.style.width = s.dot + 'px'; dot.style.height = s.dot + 'px'; }
    if (preset === 'glow' && dot) { dot.style.opacity = '0.15'; dot.style.width = (s.ring * 2) + 'px'; dot.style.height = (s.ring * 2) + 'px'; }
    if (preset === 'spotlight' && dot) { dot.style.width = '120px'; dot.style.height = '120px'; dot.style.opacity = '0.12'; }
    if (preset === 'crosshair' && dot) { dot.style.opacity = '0.5'; }
  }, true);

  // Hide when mouse leaves window
  document.addEventListener('mouseleave', function (ev) {
    if (ev.target === document) el.style.opacity = '0';
  });
  document.addEventListener('mouseenter', function (ev) {
    if (ev.target === document) el.style.opacity = '1';
  });
})();
