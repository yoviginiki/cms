{{-- Cinematic: full-viewport panels, no chrome, no scroll --}}
<div class="cinematic-wrapper" data-cinematic="true" style="position:relative;overflow:hidden;height:100vh;width:100%;">
    {!! $blocksHtml !!}
</div>
<style>
/* Cinematic layout: each section is a full-screen panel */
.cinematic-wrapper > .section-block {
    position: absolute;
    top: 0; left: 0;
    width: 100%;
    height: 100vh;
    overflow: auto;
    display: flex;
    flex-direction: column;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    z-index: 1;
}
.cinematic-wrapper > .section-block:first-child {
    opacity: 1;
    visibility: visible;
    z-index: 2;
}
/* Hide spacers in cinematic mode */
.cinematic-wrapper > .spacer-block { display: none; }
/* Hide nav/footer */
.cinematic-wrapper ~ footer,
header[role="banner"] { display: none !important; }
</style>
<script>
(function(){
  // Cinematic panel navigation
  var wrapper = document.querySelector('.cinematic-wrapper');
  if (!wrapper) return;

  // Respect reduced-motion
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
      localStorage.getItem('ensodo:experience:off') === '1') {
    // Fallback: make all panels visible, normal scroll
    wrapper.style.height = 'auto';
    wrapper.style.overflow = 'visible';
    var panels = wrapper.querySelectorAll('.section-block');
    panels.forEach(function(p) {
      p.style.position = 'relative';
      p.style.opacity = '1';
      p.style.visibility = 'visible';
      p.style.height = 'auto';
      p.style.minHeight = '100vh';
    });
    return;
  }

  var panels = Array.from(wrapper.querySelectorAll(':scope > .section-block'));
  if (panels.length < 2) return;

  var current = 0;
  var animating = false;

  // Nav dots
  var nav = document.createElement('nav');
  nav.className = 'cinematic-nav';
  nav.setAttribute('aria-label', 'Panel navigation');
  panels.forEach(function(_, i) {
    var dot = document.createElement('button');
    dot.className = 'cinematic-dot' + (i === 0 ? ' is-active' : '');
    dot.setAttribute('aria-label', 'Go to panel ' + (i + 1));
    dot.dataset.index = i;
    nav.appendChild(dot);
  });
  document.body.appendChild(nav);

  function updateDots() {
    nav.querySelectorAll('.cinematic-dot').forEach(function(d, i) {
      d.classList.toggle('is-active', i === current);
    });
  }

  nav.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-index]');
    if (btn) goTo(Number(btn.dataset.index));
  });

  function goTo(idx) {
    if (animating || idx === current || idx < 0 || idx >= panels.length) return;
    animating = true;

    var from = panels[current];
    var to = panels[idx];
    var dir = idx > current ? 1 : -1;

    // Get scene preset
    var scene = to.getAttribute('data-scene') || 'fade-through';

    // Prepare target
    to.style.visibility = 'visible';
    to.style.zIndex = '3';

    // Transition based on scene
    switch(scene) {
      case 'pinned-statement':
      case 'reveal':
        // Slide up
        to.style.opacity = '1';
        to.style.transform = 'translateY(' + (dir > 0 ? '100%' : '-100%') + ')';
        to.style.transition = 'transform 0.8s cubic-bezier(0.77,0,0.18,1)';
        from.style.transition = 'transform 0.8s cubic-bezier(0.77,0,0.18,1)';
        requestAnimationFrame(function() {
          to.style.transform = 'translateY(0)';
          from.style.transform = 'translateY(' + (dir > 0 ? '-40%' : '40%') + ')';
          from.style.opacity = '0.5';
        });
        break;

      case 'parallax-split':
        // Scale zoom
        to.style.opacity = '0';
        to.style.transform = 'scale(0.9)';
        to.style.transition = 'opacity 0.7s ease, transform 0.7s ease';
        from.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        requestAnimationFrame(function() {
          to.style.opacity = '1';
          to.style.transform = 'scale(1)';
          from.style.opacity = '0';
          from.style.transform = 'scale(1.05)';
        });
        break;

      case 'scroll-gallery':
        // Horizontal slide
        to.style.opacity = '1';
        to.style.transform = 'translateX(' + (dir > 0 ? '100%' : '-100%') + ')';
        to.style.transition = 'transform 0.8s cubic-bezier(0.77,0,0.18,1)';
        from.style.transition = 'transform 0.8s cubic-bezier(0.77,0,0.18,1)';
        requestAnimationFrame(function() {
          to.style.transform = 'translateX(0)';
          from.style.transform = 'translateX(' + (dir > 0 ? '-100%' : '100%') + ')';
        });
        break;

      default: // fade-through
        to.style.opacity = '0';
        to.style.transition = 'opacity 0.6s ease';
        from.style.transition = 'opacity 0.4s ease';
        requestAnimationFrame(function() {
          to.style.opacity = '1';
          from.style.opacity = '0';
        });
    }

    setTimeout(function() {
      from.style.visibility = 'hidden';
      from.style.zIndex = '1';
      from.style.transform = '';
      from.style.transition = '';
      from.style.opacity = '0';
      to.style.zIndex = '2';
      to.style.transition = '';
      to.style.transform = '';
      current = idx;
      updateDots();
      animating = false;
    }, 900);
  }

  // Wheel navigation
  var wheelTimeout;
  wrapper.addEventListener('wheel', function(e) {
    e.preventDefault();
    clearTimeout(wheelTimeout);
    wheelTimeout = setTimeout(function() {
      if (e.deltaY > 0) goTo(current + 1);
      else if (e.deltaY < 0) goTo(current - 1);
    }, 50);
  }, { passive: false });

  // Touch navigation
  var touchStartY = 0;
  wrapper.addEventListener('touchstart', function(e) { touchStartY = e.touches[0].clientY; }, { passive: true });
  wrapper.addEventListener('touchend', function(e) {
    var diff = touchStartY - e.changedTouches[0].clientY;
    if (Math.abs(diff) > 50) {
      if (diff > 0) goTo(current + 1);
      else goTo(current - 1);
    }
  });

  // Keyboard
  document.addEventListener('keydown', function(e) {
    if (animating) return;
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
<style>
.cinematic-nav {
    position: fixed;
    right: 24px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 9000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.cinematic-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--color-text-muted, #999);
    opacity: 0.3;
    border: none;
    cursor: pointer;
    padding: 0;
    transition: opacity 0.3s, transform 0.3s;
}
.cinematic-dot.is-active {
    opacity: 1;
    transform: scale(1.4);
    background: var(--color-primary, #358733);
}
@media (max-width: 768px) {
    .cinematic-nav { right: 12px; gap: 8px; }
    .cinematic-dot { width: 8px; height: 8px; }
}
@media (prefers-reduced-motion: reduce) {
    .cinematic-nav { display: none; }
}
</style>
