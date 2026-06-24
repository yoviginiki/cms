/**
 * Ensodo CMS — Global Custom Cursor
 * Standalone, no dependencies. Reads config from #cursor-config JSON.
 */
(function () {
  'use strict';

  // Touch/mobile → bail
  if (!window.matchMedia('(pointer: fine)').matches) return;
  // Reduced motion → bail
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

  // Size map
  var sizes = {
    sm: { dot: 4, ring: 24, hoverRing: 40, ringBorder: 1 },
    md: { dot: 6, ring: 36, hoverRing: 56, ringBorder: 1 },
    lg: { dot: 8, ring: 48, hoverRing: 72, ringBorder: 1.5 }
  };
  var s = sizes[size] || sizes.md;

  // Build CSS
  var css = document.createElement('style');
  css.textContent =
    'html.custom-cursor-active,html.custom-cursor-active *{cursor:none !important}' +
    '.custom-cursor{position:fixed;top:0;left:0;z-index:99998;pointer-events:none;' +
      (blend !== 'normal' ? 'mix-blend-mode:' + blend + ';' : '') + '}' +
    '.cursor-dot{position:absolute;width:' + s.dot + 'px;height:' + s.dot + 'px;border-radius:50%;background:' + color + ';transform:translate(-50%,-50%)}' +

    // dot-ring preset
    (preset === 'dot-ring' ?
      '.cursor-ring{position:absolute;width:' + s.ring + 'px;height:' + s.ring + 'px;border-radius:50%;border:' + s.ringBorder + 'px solid ' + ringColor + ';opacity:0.4;transform:translate(-50%,-50%);transition:width 0.25s,height 0.25s,opacity 0.25s}' : '') +

    // circle preset — filled circle, no dot
    (preset === 'circle' ?
      '.cursor-dot{width:' + s.ring + 'px;height:' + s.ring + 'px;opacity:0.15;transition:width 0.25s,height 0.25s,opacity 0.25s}' : '') +

    // crosshair preset
    (preset === 'crosshair' ?
      '.cursor-dot{width:1px;height:20px;border-radius:0;background:' + color + '}.cursor-dot::after{content:"";position:absolute;width:20px;height:1px;background:' + color + ';top:50%;left:50%;transform:translate(-50%,-50%)}' : '') +

    // minimal preset — just a dot, no ring
    (preset === 'minimal' ? '' : '') +

    // Global: reduced motion + mobile
    '@media(prefers-reduced-motion:reduce){.custom-cursor{display:none !important}html.custom-cursor-active,html.custom-cursor-active *{cursor:auto !important}}' +
    '@media(max-width:768px){.custom-cursor{display:none !important}html.custom-cursor-active,html.custom-cursor-active *{cursor:auto !important}}';
  document.head.appendChild(css);

  // Build DOM
  var el = document.createElement('div');
  el.className = 'custom-cursor';
  var dotHtml = '<span class="cursor-dot"></span>';
  var ringHtml = preset === 'dot-ring' ? '<span class="cursor-ring"></span>' : '';
  el.innerHTML = dotHtml + ringHtml;
  document.body.appendChild(el);
  document.documentElement.classList.add('custom-cursor-active');

  var dot = el.querySelector('.cursor-dot');
  var ring = preset === 'dot-ring' ? el.querySelector('.cursor-ring') : null;

  var mx = -100, my = -100, cx = -100, cy = -100;
  var raf;

  document.addEventListener('mousemove', function (e) {
    mx = e.clientX;
    my = e.clientY;
    // Dot follows immediately
    dot.style.transform = 'translate(-50%,-50%) translate(' + mx + 'px,' + my + 'px)';
  });

  if (ring) {
    // Ring follows with lerp (weighted lag)
    var lerp = cfg.lagWeight || 0.08;
    function tick() {
      cx += (mx - cx) * lerp;
      cy += (my - cy) * lerp;
      ring.style.transform = 'translate(-50%,-50%) translate(' + Math.round(cx * 10) / 10 + 'px,' + Math.round(cy * 10) / 10 + 'px)';
      raf = requestAnimationFrame(tick);
    }
    raf = requestAnimationFrame(tick);
  }

  // Hover enlargement on interactive elements
  var interactiveSelector = 'a, button, [role="button"], summary, input, select, textarea, label';

  document.addEventListener('mouseenter', function (e) {
    if (e.target.matches && e.target.matches(interactiveSelector)) {
      if (ring) {
        ring.style.width = s.hoverRing + 'px';
        ring.style.height = s.hoverRing + 'px';
        ring.style.opacity = '0.15';
      }
      if (preset === 'circle') {
        dot.style.width = s.hoverRing + 'px';
        dot.style.height = s.hoverRing + 'px';
        dot.style.opacity = '0.08';
      }
    }
  }, true);

  document.addEventListener('mouseleave', function (e) {
    if (e.target.matches && e.target.matches(interactiveSelector)) {
      if (ring) {
        ring.style.width = s.ring + 'px';
        ring.style.height = s.ring + 'px';
        ring.style.opacity = '0.4';
      }
      if (preset === 'circle') {
        dot.style.width = s.ring + 'px';
        dot.style.height = s.ring + 'px';
        dot.style.opacity = '0.15';
      }
    }
  }, true);

  // Hide when mouse leaves window
  document.addEventListener('mouseleave', function () {
    el.style.opacity = '0';
  });
  document.addEventListener('mouseenter', function () {
    el.style.opacity = '1';
  });
})();
