/**
 * Ensodo CMS — Experience Runtime
 *
 * Transforms a page with [data-experience="cinematic"] into a full-viewport
 * panel-by-panel navigation (like turning pages of a book).
 *
 * Uses GSAP Observer for wheel/touch/key input detection.
 *
 * Respects:
 * - prefers-reduced-motion: reduce → falls back to normal scroll
 * - localStorage 'ensodo:experience:off' → falls back to normal scroll
 *
 * Only loaded on pages with experience_mode === 'cinematic'.
 */

import { gsap } from 'gsap';
import { Observer } from 'gsap/Observer';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(Observer, ScrollTrigger);

(function () {
  'use strict';

  // ─── Guards ───
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const userDisabled = localStorage.getItem('ensodo:experience:off') === '1';

  if (prefersReducedMotion || userDisabled) {
    document.documentElement.classList.add('experience-reduced');
    return; // Normal scroll, no snap, no animations
  }

  // ─── Find panels ───
  // Priority: .section-block elements → top-level blocks in .pos-main (grid layout)
  // → top-level blocks in <main> (standard layout)
  let panels = Array.from(document.querySelectorAll('.section-block'));

  if (panels.length < 2) {
    // Find the content container (grid layout uses .pos-main, standard uses <main>)
    const container = document.querySelector('.pos-main')
      || document.querySelector('main[role="main"] > main')
      || document.querySelector('main[role="main"]')
      || document.querySelector('main');

    if (container) {
      panels = Array.from(container.children).filter(function (el) {
        if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE') return false;
        if (el.classList.contains('spacer-block')) return false;
        // Skip tiny/empty elements
        if (el.offsetHeight < 50 && !el.querySelector('video')) return false;
        return true;
      });
    }
  }
  if (panels.length < 2) return; // Need at least 2 panels

  let currentIndex = 0;
  let isAnimating = false;

  // ─── Setup: make panels full-viewport, stacked ───
  const wrapper = document.querySelector('.pos-main')
    || document.querySelector('main[role="main"] > main')
    || document.querySelector('main[role="main"]')
    || document.querySelector('main')
    || document.body;

  // Add experience class for CSS
  document.documentElement.classList.add('experience-active');

  // Style wrapper
  Object.assign(wrapper.style, {
    position: 'relative',
    overflow: 'hidden',
    height: '100vh',
    width: '100%',
  });

  // Style each panel
  panels.forEach((panel, i) => {
    Object.assign(panel.style, {
      position: 'absolute',
      top: '0',
      left: '0',
      width: '100%',
      height: '100vh',
      overflow: 'auto',
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'center',
      zIndex: i === 0 ? '2' : '1',
      visibility: i === 0 ? 'visible' : 'hidden',
      opacity: i === 0 ? '1' : '0',
    });

    // Add data attributes for CSS targeting
    panel.setAttribute('data-panel-index', String(i));
    panel.setAttribute('data-panel-transition', panel.dataset?.experienceTransition ||
      panel.querySelector('[data-experience-transition]')?.dataset?.experienceTransition || 'fade');
    panel.setAttribute('data-panel-enter', panel.dataset?.experienceEnter ||
      panel.querySelector('[data-experience-enter]')?.dataset?.experienceEnter || 'fade-up');
  });

  // Run enter animation on the first panel
  runEnterAnimation(panels[0]);

  // ─── Transition presets ───
  function getTransitionTimeline(fromPanel, toPanel, direction, transitionType) {
    const tl = gsap.timeline({
      onStart: () => {
        isAnimating = true;
        toPanel.style.visibility = 'visible';
        toPanel.style.zIndex = '3';
      },
      onComplete: () => {
        fromPanel.style.visibility = 'hidden';
        fromPanel.style.opacity = '0';
        fromPanel.style.zIndex = '1';
        toPanel.style.zIndex = '2';
        isAnimating = false;
        runEnterAnimation(toPanel);
      },
    });

    const dur = 0.8;
    const ease = 'power2.inOut';

    switch (transitionType) {
      case 'slide-up':
        gsap.set(toPanel, { opacity: 1, y: direction > 0 ? '100%' : '-100%' });
        tl.to(fromPanel, { y: direction > 0 ? '-100%' : '100%', duration: dur, ease })
          .to(toPanel, { y: '0%', duration: dur, ease }, '<');
        break;

      case 'slide-left':
        gsap.set(toPanel, { opacity: 1, x: direction > 0 ? '100%' : '-100%' });
        tl.to(fromPanel, { x: direction > 0 ? '-100%' : '100%', duration: dur, ease })
          .to(toPanel, { x: '0%', duration: dur, ease }, '<');
        break;

      case 'cover':
        gsap.set(toPanel, { opacity: 1, y: direction > 0 ? '100%' : '-100%' });
        tl.to(toPanel, { y: '0%', duration: dur, ease });
        break;

      case 'mask-wipe':
        gsap.set(toPanel, { opacity: 1, clipPath: 'inset(0 0 100% 0)' });
        tl.to(toPanel, {
          clipPath: 'inset(0 0 0% 0)',
          duration: dur * 1.2,
          ease: 'power3.inOut',
        }).to(fromPanel, { opacity: 0, duration: 0.3 }, '<0.4');
        break;

      case 'zoom':
        gsap.set(toPanel, { opacity: 0, scale: 0.8 });
        tl.to(fromPanel, { opacity: 0, scale: 1.1, duration: dur * 0.6, ease: 'power2.in' })
          .to(toPanel, { opacity: 1, scale: 1, duration: dur, ease: 'power2.out' }, '<0.2');
        break;

      case 'fade':
      default:
        gsap.set(toPanel, { opacity: 0 });
        tl.to(fromPanel, { opacity: 0, duration: dur * 0.5, ease: 'power2.in' })
          .to(toPanel, { opacity: 1, duration: dur * 0.6, ease: 'power2.out' }, '<0.15');
        break;
    }

    return tl;
  }

  // ─── Enter animations ───
  function runEnterAnimation(panel) {
    const enterType = panel.getAttribute('data-panel-enter') || 'fade-up';
    if (enterType === 'none') return;

    const children = panel.querySelectorAll(':scope > div > *');
    if (!children.length) return;

    switch (enterType) {
      case 'fade-up':
        gsap.fromTo(children,
          { opacity: 0, y: 40 },
          { opacity: 1, y: 0, duration: 0.7, stagger: 0.1, ease: 'power2.out', delay: 0.2 }
        );
        break;

      case 'stagger':
        gsap.fromTo(children,
          { opacity: 0, y: 30, scale: 0.97 },
          { opacity: 1, y: 0, scale: 1, duration: 0.6, stagger: 0.15, ease: 'power2.out', delay: 0.15 }
        );
        break;

      case 'clip':
        gsap.fromTo(children,
          { clipPath: 'inset(100% 0 0 0)' },
          { clipPath: 'inset(0% 0 0 0)', duration: 0.8, stagger: 0.1, ease: 'power3.out', delay: 0.2 }
        );
        break;
    }
  }

  // ─── Navigation ───
  function goToPanel(newIndex) {
    if (isAnimating) return;
    if (newIndex < 0 || newIndex >= panels.length) return;
    if (newIndex === currentIndex) return;

    const direction = newIndex > currentIndex ? 1 : -1;
    const fromPanel = panels[currentIndex];
    const toPanel = panels[newIndex];
    const transitionType = toPanel.getAttribute('data-panel-transition') || 'fade';

    // Reset transforms on target panel
    gsap.set(toPanel, { x: 0, y: 0, scale: 1, clearProps: 'clipPath' });

    getTransitionTimeline(fromPanel, toPanel, direction, transitionType);
    currentIndex = newIndex;

    // Update URL hash for navigation
    const anchorId = toPanel.querySelector('[id]')?.id;
    if (anchorId) {
      history.replaceState(null, '', '#' + anchorId);
    }
  }

  // ─── GSAP Observer: wheel, touch, keys ───
  Observer.create({
    type: 'wheel,touch,pointer',
    wheelSpeed: -1,
    onDown: () => goToPanel(currentIndex - 1),
    onUp: () => goToPanel(currentIndex + 1),
    tolerance: 50,
    preventDefault: true,
  });

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (isAnimating) return;
    switch (e.key) {
      case 'ArrowDown':
      case 'PageDown':
      case ' ':
        e.preventDefault();
        goToPanel(currentIndex + 1);
        break;
      case 'ArrowUp':
      case 'PageUp':
        e.preventDefault();
        goToPanel(currentIndex - 1);
        break;
      case 'Home':
        e.preventDefault();
        goToPanel(0);
        break;
      case 'End':
        e.preventDefault();
        goToPanel(panels.length - 1);
        break;
    }
  });

  // ─── Panel indicator dots ───
  const nav = document.createElement('nav');
  nav.className = 'experience-nav';
  nav.setAttribute('aria-label', 'Panel navigation');
  nav.innerHTML = panels.map((_, i) =>
    `<button class="experience-nav-dot${i === 0 ? ' is-active' : ''}"
      data-index="${i}" aria-label="Go to panel ${i + 1}"
      ${i === 0 ? 'aria-current="true"' : ''}></button>`
  ).join('');
  document.body.appendChild(nav);

  nav.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-index]');
    if (btn) goToPanel(Number(btn.dataset.index));
  });

  // Update active dot
  const originalGoToPanel = goToPanel;
  const dots = nav.querySelectorAll('.experience-nav-dot');

  // Patch goToPanel to also update dots
  const origComplete = () => {};
  const observer = new MutationObserver(() => {
    dots.forEach((dot, i) => {
      const isActive = i === currentIndex;
      dot.classList.toggle('is-active', isActive);
      dot.setAttribute('aria-current', isActive ? 'true' : 'false');
    });
  });

  // Simple interval to sync dots with currentIndex
  setInterval(() => {
    dots.forEach((dot, i) => {
      dot.classList.toggle('is-active', i === currentIndex);
    });
  }, 200);

  // ─── Skip link for accessibility ───
  const skipBtn = document.createElement('button');
  skipBtn.className = 'experience-skip';
  skipBtn.textContent = 'Skip to normal scroll';
  skipBtn.setAttribute('aria-label', 'Disable cinematic mode and use normal scrolling');
  skipBtn.addEventListener('click', () => {
    localStorage.setItem('ensodo:experience:off', '1');
    location.reload();
  });
  document.body.appendChild(skipBtn);
})();
