{{-- Cinematic: full-viewport panels with smooth transitions --}}
<div class="cinematic-wrapper" data-cinematic="true">
    {!! $blocksHtml !!}
</div>
<style>
/* Cinematic layout core */
html, body { overflow: hidden !important; height: 100vh !important; margin: 0 !important; }
.cinematic-wrapper {
    position: relative;
    width: 100%;
    height: 100vh;
    overflow: hidden;
}

/* Each section = full-screen panel — override ALL external padding */
.cinematic-wrapper > .section-block {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100vh;
    overflow: auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    will-change: transform, opacity;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    padding-left: 0 !important;
    padding-right: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    margin: 0 !important;
}
/* Inner content div — centered with readable padding */
.cinematic-wrapper > .section-block > div {
    margin: 0 auto;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 3rem 4rem !important;
    box-sizing: border-box;
}
/* Hero section (first) — no padding, video fills the panel */
.cinematic-wrapper > .section-block:first-child > div {
    padding: 0 !important;
}

/* Video hero — capsule/ellipse shape with breathing room */
.cinematic-wrapper .video-hero {
    min-height: 80vh !important;
    margin: auto 3vw !important;
    width: calc(100% - 6vw) !important;
    border-radius: 999px !important;
}

/* On mobile */
@media (max-width: 768px) {
    .cinematic-wrapper > .section-block > div {
        padding: 2rem 1.5rem !important;
    }
    .cinematic-wrapper > .section-block:first-child > div {
        padding: 0 !important;
    }
    .cinematic-wrapper .video-hero {
        border-radius: 300px !important;
        min-height: 75vh !important;
        margin: auto 2vw !important;
        width: calc(100% - 4vw) !important;
    }
}

/* Initial state: first visible, rest below */
.cinematic-wrapper > .section-block {
    opacity: 0;
    transform: translateY(100%);
    pointer-events: none;
    z-index: 1;
}
.cinematic-wrapper > .section-block:first-child {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
    z-index: 2;
}

/* Transition classes — GPU accelerated */
.cinematic-wrapper > .section-block.panel-entering {
    transition: transform 1s cubic-bezier(0.76, 0, 0.24, 1), opacity 0.8s ease;
    z-index: 3 !important;
    pointer-events: none;
}
.cinematic-wrapper > .section-block.panel-leaving {
    transition: transform 1s cubic-bezier(0.76, 0, 0.24, 1), opacity 0.8s ease;
    z-index: 2;
    pointer-events: none;
}
.cinematic-wrapper > .section-block.panel-active {
    opacity: 1;
    transform: translateY(0) translateX(0) scale(1);
    pointer-events: auto;
    z-index: 2;
}

/* Hide spacers & chrome */
.cinematic-wrapper > .spacer-block { display: none !important; }
header[role="banner"] { display: none !important; }
footer[role="contentinfo"] { display: none !important; }

/* Nav dots */
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
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--color-text-muted, #999);
    opacity: 0.25;
    border: none;
    cursor: pointer;
    padding: 0;
    transition: opacity 0.4s, transform 0.4s, background 0.4s;
}
.cinematic-dot.is-active {
    opacity: 1;
    transform: scale(1.5);
    background: var(--color-primary, #358733);
}
.cinematic-dot:hover { opacity: 0.6; }

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    html, body { overflow: auto !important; height: auto !important; }
    .cinematic-wrapper { height: auto; overflow: visible; }
    .cinematic-wrapper > .section-block {
        position: relative !important;
        opacity: 1 !important;
        transform: none !important;
        pointer-events: auto !important;
        height: auto !important;
        min-height: 100vh;
        transition: none !important;
    }
    .cinematic-nav { display: none; }
}
@media (max-width: 768px) {
    .cinematic-nav { right: 12px; gap: 8px; }
    .cinematic-dot { width: 8px; height: 8px; }
}
</style>
<script>
(function(){
  var wrapper = document.querySelector('.cinematic-wrapper');
  if (!wrapper) return;

  // Reduced motion / user off → bail
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
      localStorage.getItem('ensodo:experience:off') === '1') {
    wrapper.style.height = 'auto';
    wrapper.style.overflow = 'visible';
    document.documentElement.style.overflow = 'auto';
    document.body.style.overflow = 'auto';
    document.documentElement.style.height = 'auto';
    document.body.style.height = 'auto';
    wrapper.querySelectorAll('.section-block').forEach(function(p) {
      p.style.cssText = 'position:relative;opacity:1;transform:none;pointer-events:auto;height:auto;min-height:100vh;';
    });
    return;
  }

  var panels = Array.from(wrapper.querySelectorAll(':scope > .section-block'));
  if (panels.length < 2) return;

  var current = 0;
  var locked = false;
  var DURATION = 1000;

  // Mark first panel active
  panels[0].classList.add('panel-active');

  // ─── Nav dots ───
  var nav = document.createElement('nav');
  nav.className = 'cinematic-nav';
  nav.setAttribute('aria-label', 'Panel navigation');
  panels.forEach(function(_, i) {
    var dot = document.createElement('button');
    dot.className = 'cinematic-dot' + (i === 0 ? ' is-active' : '');
    dot.setAttribute('aria-label', 'Go to panel ' + (i + 1));
    dot.dataset.index = String(i);
    nav.appendChild(dot);
  });
  document.body.appendChild(nav);
  var dots = nav.querySelectorAll('.cinematic-dot');

  function updateDots() {
    dots.forEach(function(d, i) { d.classList.toggle('is-active', i === current); });
  }
  nav.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-index]');
    if (btn) goTo(Number(btn.dataset.index));
  });

  // ─── Transition engine ───
  function goTo(idx) {
    if (locked || idx === current || idx < 0 || idx >= panels.length) return;
    locked = true;

    var from = panels[current];
    var to = panels[idx];
    var dir = idx > current ? 1 : -1;
    var scene = to.getAttribute('data-scene') || 'fade-through';

    // Clean previous transition classes
    panels.forEach(function(p) {
      p.classList.remove('panel-entering', 'panel-leaving');
    });

    // Set start position for incoming panel
    switch(scene) {
      case 'reveal':
      case 'pinned-statement':
        to.style.opacity = '1';
        to.style.transform = 'translateY(' + (dir > 0 ? '100%' : '-100%') + ')';
        break;
      case 'scroll-gallery':
        to.style.opacity = '1';
        to.style.transform = 'translateX(' + (dir > 0 ? '100%' : '-100%') + ')';
        break;
      case 'parallax-split':
        to.style.opacity = '0';
        to.style.transform = 'scale(0.92)';
        break;
      default:
        to.style.opacity = '0';
        to.style.transform = 'translateY(' + (dir > 0 ? '8%' : '-8%') + ')';
    }

    // Force layout before adding transition classes
    to.offsetHeight;

    // Add transition classes
    to.classList.add('panel-entering');
    from.classList.add('panel-leaving');

    // Animate to final position
    requestAnimationFrame(function() {
      // Incoming panel → center
      to.style.opacity = '1';
      to.style.transform = 'translateY(0) translateX(0) scale(1)';

      // Outgoing panel → exit
      switch(scene) {
        case 'reveal':
        case 'pinned-statement':
          from.style.transform = 'translateY(' + (dir > 0 ? '-30%' : '30%') + ')';
          from.style.opacity = '0.3';
          break;
        case 'scroll-gallery':
          from.style.transform = 'translateX(' + (dir > 0 ? '-100%' : '100%') + ')';
          break;
        case 'parallax-split':
          from.style.opacity = '0';
          from.style.transform = 'scale(1.06)';
          break;
        default:
          from.style.opacity = '0';
          from.style.transform = 'translateY(' + (dir > 0 ? '-8%' : '8%') + ')';
      }
    });

    // Cleanup after transition
    setTimeout(function() {
      from.classList.remove('panel-leaving', 'panel-active');
      from.style.transform = 'translateY(100%)';
      from.style.opacity = '0';
      from.style.transition = 'none';
      from.offsetHeight; // force reflow
      from.style.transition = '';

      to.classList.remove('panel-entering');
      to.classList.add('panel-active');

      current = idx;
      updateDots();
      locked = false;
    }, DURATION + 50);
  }

  // ─── Input handlers ───

  // Wheel — debounced
  var wheelCooldown = false;
  wrapper.addEventListener('wheel', function(e) {
    e.preventDefault();
    if (wheelCooldown || locked) return;
    wheelCooldown = true;
    if (e.deltaY > 5) goTo(current + 1);
    else if (e.deltaY < -5) goTo(current - 1);
    setTimeout(function() { wheelCooldown = false; }, 800);
  }, { passive: false });

  // Touch
  var ty = 0;
  wrapper.addEventListener('touchstart', function(e) { ty = e.touches[0].clientY; }, { passive: true });
  wrapper.addEventListener('touchend', function(e) {
    var diff = ty - e.changedTouches[0].clientY;
    if (Math.abs(diff) > 60) {
      if (diff > 0) goTo(current + 1);
      else goTo(current - 1);
    }
  });

  // Keyboard
  document.addEventListener('keydown', function(e) {
    if (locked) return;
    switch(e.key) {
      case 'ArrowDown': case 'PageDown': case ' ':
        e.preventDefault(); goTo(current + 1); break;
      case 'ArrowUp': case 'PageUp':
        e.preventDefault(); goTo(current - 1); break;
      case 'Home': e.preventDefault(); goTo(0); break;
      case 'End': e.preventDefault(); goTo(panels.length - 1); break;
    }
  });
})();
</script>
