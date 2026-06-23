/**
 * Ensodo CMS — Cinematic Experience Runtime (v2 — Scene Presets)
 *
 * Reads data-scene attributes on .section-block elements and creates
 * GSAP ScrollTrigger timelines for each scene preset.
 *
 * Scene presets:
 *   fade-through    — quiet opacity/translate crossfade (default)
 *   pinned-statement — pins; content builds to scroll progress (scrub)
 *   scroll-gallery   — pins; crossfades child blocks on scroll
 *   reveal           — split-text headings + staggered entrance
 *   parallax-split   — two-column counter-motion on scroll
 *
 * Guards:
 *   - prefers-reduced-motion: reduce → all content visible, no animation
 *   - localStorage 'ensodo:experience:off' → same fallback
 *   - < 1 section with data-scene → does nothing
 */

import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

(function () {
  'use strict';

  // ─── Guards ───
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const userOff = localStorage.getItem('ensodo:experience:off') === '1';

  if (reducedMotion || userOff) {
    document.documentElement.classList.add('experience-reduced');
    return;
  }

  // ─── Find scene sections ───
  const sections = Array.from(document.querySelectorAll('.section-block[data-scene]'));
  if (sections.length < 1) return;

  document.documentElement.classList.add('experience-active');

  // ─── Text split helper (no SplitText plugin) ───
  function splitText(el) {
    const text = el.textContent;
    el.textContent = '';
    el.setAttribute('aria-label', text);
    const chars = [];
    for (let i = 0; i < text.length; i++) {
      const span = document.createElement('span');
      span.textContent = text[i];
      span.style.display = 'inline-block';
      span.style.willChange = 'transform, opacity';
      if (text[i] === ' ') span.style.width = '0.3em';
      el.appendChild(span);
      chars.push(span);
    }
    return chars;
  }

  // ─── Scene Registry ───
  const scenes = {

    // ── fade-through: quiet opacity + translateY on enter/leave ──
    'fade-through': function (section) {
      const inner = section.querySelector(':scope > div') || section;
      gsap.set(inner.children, { opacity: 0, y: 40 });

      ScrollTrigger.create({
        trigger: section,
        start: 'top 80%',
        onEnter: function () {
          gsap.to(inner.children, {
            opacity: 1, y: 0,
            duration: 0.8, stagger: 0.1, ease: 'power2.out'
          });
        },
        once: true
      });
    },

    // ── pinned-statement: section pins; content scrubs in ──
    'pinned-statement': function (section) {
      const inner = section.querySelector(':scope > div') || section;
      const children = Array.from(inner.querySelectorAll(':scope > *'));
      if (children.length < 1) return;

      // Set initial state
      children.forEach(function (child, i) {
        if (i > 0) gsap.set(child, { opacity: 0, y: 30 });
      });

      // Split headings
      var headings = section.querySelectorAll('h1, h2, h3');
      var splitChars = [];
      headings.forEach(function (h) {
        var chars = splitText(h);
        gsap.set(chars, { opacity: 0, y: 20 });
        splitChars.push({ el: h, chars: chars });
      });

      var tl = gsap.timeline({
        scrollTrigger: {
          trigger: section,
          start: 'top top',
          end: '+=' + (children.length * 40) + '%',
          pin: true,
          scrub: 0.8,
          anticipatePin: 1
        }
      });

      // Animate split headings first
      splitChars.forEach(function (item) {
        tl.to(item.chars, {
          opacity: 1, y: 0,
          duration: 0.5, stagger: 0.02, ease: 'power2.out'
        }, 0);
      });

      // Then stagger in each child block
      children.forEach(function (child, i) {
        if (i > 0) {
          tl.to(child, {
            opacity: 1, y: 0,
            duration: 0.4, ease: 'power2.out'
          }, 0.15 * i);
        }
      });

      // Add a decorative rule animation if there's an hr/divider
      var rule = section.querySelector('hr, .divider-block');
      if (rule) {
        gsap.set(rule, { scaleX: 0, transformOrigin: 'left center' });
        tl.to(rule, { scaleX: 1, duration: 0.6, ease: 'power2.inOut' }, 0.3);
      }
    },

    // ── scroll-gallery: pins; crossfades through child blocks ──
    'scroll-gallery': function (section) {
      var inner = section.querySelector(':scope > div') || section;
      var items = Array.from(inner.querySelectorAll(':scope > div > div > *')); // row > column > blocks
      if (items.length < 2) {
        // Fallback: try direct children
        items = Array.from(inner.querySelectorAll(':scope > *'));
      }
      if (items.length < 2) return;

      // Stack all items absolutely
      var wrapper = inner;
      wrapper.style.position = 'relative';
      wrapper.style.minHeight = '60vh';

      items.forEach(function (item, i) {
        item.style.position = i === 0 ? 'relative' : 'absolute';
        item.style.top = '0';
        item.style.left = '0';
        item.style.width = '100%';
        if (i > 0) gsap.set(item, { opacity: 0 });
      });

      // Progress indicator
      var progress = document.createElement('div');
      progress.className = 'scene-gallery-progress';
      progress.style.cssText = 'position:absolute;bottom:20px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:5;';
      items.forEach(function (_, i) {
        var dot = document.createElement('span');
        dot.style.cssText = 'width:8px;height:8px;border-radius:50%;background:var(--color-text-muted,#999);opacity:' + (i === 0 ? '1' : '0.3') + ';transition:opacity 0.3s;';
        progress.appendChild(dot);
      });
      section.appendChild(progress);
      var dots = progress.querySelectorAll('span');

      var tl = gsap.timeline({
        scrollTrigger: {
          trigger: section,
          start: 'top top',
          end: '+=' + (items.length * 80) + '%',
          pin: true,
          scrub: 0.5,
          snap: { snapTo: 1 / (items.length - 1), duration: 0.3 },
          onUpdate: function (self) {
            var idx = Math.round(self.progress * (items.length - 1));
            dots.forEach(function (d, i) { d.style.opacity = i === idx ? '1' : '0.3'; });
          }
        }
      });

      // Crossfade between items
      for (var i = 0; i < items.length - 1; i++) {
        tl.to(items[i], { opacity: 0, duration: 0.5 }, i)
          .to(items[i + 1], { opacity: 1, duration: 0.5 }, i);
      }
    },

    // ── reveal: split-text headings + staggered block entrance ──
    'reveal': function (section) {
      var inner = section.querySelector(':scope > div') || section;
      var children = Array.from(inner.querySelectorAll(':scope > *'));

      // Split headings
      var headings = section.querySelectorAll('h1, h2, h3');
      headings.forEach(function (h) {
        var chars = splitText(h);
        gsap.set(chars, { opacity: 0, y: 30, rotateX: -40 });

        ScrollTrigger.create({
          trigger: h,
          start: 'top 85%',
          onEnter: function () {
            gsap.to(chars, {
              opacity: 1, y: 0, rotateX: 0,
              duration: 0.6, stagger: 0.025, ease: 'power3.out'
            });
          },
          once: true
        });
      });

      // Stagger non-heading children
      var blocks = children.filter(function (c) {
        return !c.matches('h1, h2, h3') && c.offsetHeight > 10;
      });
      gsap.set(blocks, { opacity: 0, y: 50 });

      ScrollTrigger.create({
        trigger: section,
        start: 'top 70%',
        onEnter: function () {
          gsap.to(blocks, {
            opacity: 1, y: 0,
            duration: 0.7, stagger: 0.12, ease: 'power2.out', delay: 0.2
          });
        },
        once: true
      });

      // Line draw on dividers
      var dividers = section.querySelectorAll('hr, .divider-block');
      dividers.forEach(function (d) {
        gsap.set(d, { scaleX: 0, transformOrigin: 'left center' });
        ScrollTrigger.create({
          trigger: d,
          start: 'top 85%',
          onEnter: function () {
            gsap.to(d, { scaleX: 1, duration: 1, ease: 'power2.inOut' });
          },
          once: true
        });
      });
    },

    // ── parallax-split: two columns counter-move on scroll ──
    'parallax-split': function (section) {
      var inner = section.querySelector(':scope > div') || section;
      // Find two-column layout (grid or flex with 2 children)
      var grid = inner.querySelector('[style*="grid-template-columns"]') || inner;
      var cols = Array.from(grid.children);

      if (cols.length >= 2) {
        var left = cols[0];
        var right = cols[1];

        // Counter-motion parallax
        gsap.to(left, {
          y: -60,
          ease: 'none',
          scrollTrigger: {
            trigger: section,
            start: 'top bottom',
            end: 'bottom top',
            scrub: true
          }
        });

        gsap.to(right, {
          y: 60,
          ease: 'none',
          scrollTrigger: {
            trigger: section,
            start: 'top bottom',
            end: 'bottom top',
            scrub: true
          }
        });
      }

      // Also run a reveal on the content
      var blocks = inner.querySelectorAll('p, h2, h3, h4, img, a');
      gsap.set(blocks, { opacity: 0, y: 30 });
      ScrollTrigger.create({
        trigger: section,
        start: 'top 75%',
        onEnter: function () {
          gsap.to(blocks, {
            opacity: 1, y: 0,
            duration: 0.6, stagger: 0.08, ease: 'power2.out'
          });
        },
        once: true
      });
    }
  };

  // ─── Initialize scenes ───
  sections.forEach(function (section) {
    var preset = section.getAttribute('data-scene');
    var factory = scenes[preset];
    if (factory) {
      factory(section);
    } else {
      // Unknown preset — apply fade-through as fallback
      scenes['fade-through'](section);
    }
  });

  // ─── Skip button ───
  var skipBtn = document.createElement('button');
  skipBtn.className = 'experience-skip';
  skipBtn.textContent = 'Disable animations';
  skipBtn.setAttribute('aria-label', 'Disable cinematic animations and use normal scrolling');
  skipBtn.addEventListener('click', function () {
    localStorage.setItem('ensodo:experience:off', '1');
    location.reload();
  });
  document.body.appendChild(skipBtn);

})();
