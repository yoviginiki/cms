/**
 * Ensodo CMS — Cinematic Experience Runtime (v2.1 — Deep Content Targeting)
 *
 * Reads data-scene attributes on .section-block elements and creates
 * GSAP ScrollTrigger timelines. Content is found at ANY depth via
 * querySelectorAll (not just direct children).
 */

import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);
window.ScrollTrigger = ScrollTrigger;

(function () {
  'use strict';

  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var userOff = localStorage.getItem('ensodo:experience:off') === '1';

  if (reducedMotion || userOff) {
    document.documentElement.classList.add('experience-reduced');
    return;
  }

  var sections = Array.from(document.querySelectorAll('.section-block[data-scene]'));
  if (sections.length < 1) return;

  document.documentElement.classList.add('experience-active');

  // ─── Helpers ───

  function splitText(el) {
    var text = el.textContent;
    el.textContent = '';
    el.setAttribute('aria-label', text);
    var chars = [];
    for (var i = 0; i < text.length; i++) {
      var span = document.createElement('span');
      span.textContent = text[i];
      span.style.display = 'inline-block';
      span.style.willChange = 'transform, opacity';
      if (text[i] === ' ') span.style.width = '0.3em';
      el.appendChild(span);
      chars.push(span);
    }
    return chars;
  }

  // Find visible content blocks deep inside a section
  function findContent(section) {
    return Array.from(section.querySelectorAll('h1, h2, h3, h4, p, img, figure, video, .video-hero, .divider-block, .image-block, .heading-block, .paragraph-block, .rich-text-block'));
  }

  function findHeadings(section) {
    return Array.from(section.querySelectorAll('h1, h2, h3'));
  }

  function findImages(section) {
    return Array.from(section.querySelectorAll('img, figure, .image-block, .video-hero'));
  }

  function findDividers(section) {
    return Array.from(section.querySelectorAll('hr, .divider-block'));
  }

  function findParagraphs(section) {
    return Array.from(section.querySelectorAll('p, .paragraph-block .prose, .rich-text-block .prose'));
  }

  // ─── Scene Registry ───
  var scenes = {

    'fade-through': function (section) {
      var items = findContent(section);
      if (!items.length) return;

      gsap.set(items, { opacity: 0, y: 50 });

      ScrollTrigger.create({
        trigger: section,
        start: 'top 75%',
        onEnter: function () {
          gsap.to(items, {
            opacity: 1, y: 0,
            duration: 1, stagger: 0.12, ease: 'power3.out'
          });
        },
        once: true
      });
    },

    'pinned-statement': function (section) {
      var headings = findHeadings(section);
      var paragraphs = findParagraphs(section);
      var dividers = findDividers(section);
      var allContent = findContent(section);

      if (allContent.length < 1) return;

      // Set initial states
      var splitGroups = [];
      headings.forEach(function (h) {
        var chars = splitText(h);
        gsap.set(chars, { opacity: 0, y: 25, rotateX: -30 });
        splitGroups.push(chars);
      });

      paragraphs.forEach(function (p) {
        gsap.set(p, { opacity: 0, y: 30 });
      });

      dividers.forEach(function (d) {
        gsap.set(d, { scaleX: 0, transformOrigin: 'left center' });
      });

      // Pin + scrub timeline
      var tl = gsap.timeline({
        scrollTrigger: {
          trigger: section,
          start: 'top top',
          end: function () { return '+=' + (section.offsetHeight * 1.5); },
          pin: true,
          scrub: 1,
          anticipatePin: 1
        }
      });

      // Animate headings (split chars)
      var pos = 0;
      splitGroups.forEach(function (chars) {
        tl.to(chars, {
          opacity: 1, y: 0, rotateX: 0,
          duration: 0.6, stagger: 0.02, ease: 'power3.out'
        }, pos);
        pos += 0.4;
      });

      // Animate dividers
      dividers.forEach(function (d) {
        tl.to(d, { scaleX: 1, duration: 0.5, ease: 'power2.inOut' }, pos);
        pos += 0.2;
      });

      // Animate paragraphs
      paragraphs.forEach(function (p) {
        tl.to(p, { opacity: 1, y: 0, duration: 0.5, ease: 'power2.out' }, pos);
        pos += 0.25;
      });
    },

    'scroll-gallery': function (section) {
      var images = findImages(section);
      if (images.length < 2) {
        // Fallback to fade-through
        scenes['fade-through'](section);
        return;
      }

      // Stack images
      var container = images[0].parentElement;
      container.style.position = 'relative';
      container.style.minHeight = '60vh';

      images.forEach(function (img, i) {
        if (i > 0) {
          img.style.position = 'absolute';
          img.style.top = '0';
          img.style.left = '0';
          img.style.width = '100%';
          gsap.set(img, { opacity: 0 });
        }
      });

      // Progress dots
      var progress = document.createElement('div');
      progress.style.cssText = 'position:absolute;bottom:20px;left:50%;transform:translateX(-50%);display:flex;gap:10px;z-index:5;';
      images.forEach(function (_, i) {
        var dot = document.createElement('span');
        dot.style.cssText = 'width:8px;height:8px;border-radius:50%;background:var(--color-text-muted,#999);opacity:' + (i === 0 ? '1' : '0.3') + ';transition:all 0.3s;';
        progress.appendChild(dot);
      });
      section.appendChild(progress);
      var dots = progress.querySelectorAll('span');

      // Pin + crossfade
      var tl = gsap.timeline({
        scrollTrigger: {
          trigger: section,
          start: 'top top',
          end: '+=' + (images.length * 100) + '%',
          pin: true,
          scrub: 0.5,
          onUpdate: function (self) {
            var idx = Math.round(self.progress * (images.length - 1));
            dots.forEach(function (d, i) {
              d.style.opacity = i === idx ? '1' : '0.3';
              d.style.transform = i === idx ? 'scale(1.4)' : 'scale(1)';
            });
          }
        }
      });

      for (var i = 0; i < images.length - 1; i++) {
        tl.to(images[i], { opacity: 0, scale: 1.05, duration: 0.5 }, i)
          .to(images[i + 1], { opacity: 1, duration: 0.5 }, i);
      }
    },

    'reveal': function (section) {
      var headings = findHeadings(section);
      var paragraphs = findParagraphs(section);
      var dividers = findDividers(section);
      var images = findImages(section);

      // Split and animate headings
      headings.forEach(function (h) {
        var chars = splitText(h);
        gsap.set(chars, { opacity: 0, y: 40, rotateX: -50 });

        ScrollTrigger.create({
          trigger: h,
          start: 'top 85%',
          onEnter: function () {
            gsap.to(chars, {
              opacity: 1, y: 0, rotateX: 0,
              duration: 0.8, stagger: 0.03, ease: 'power3.out'
            });
          },
          once: true
        });
      });

      // Stagger paragraphs
      if (paragraphs.length) {
        gsap.set(paragraphs, { opacity: 0, y: 40 });
        ScrollTrigger.create({
          trigger: section,
          start: 'top 65%',
          onEnter: function () {
            gsap.to(paragraphs, {
              opacity: 1, y: 0,
              duration: 0.9, stagger: 0.15, ease: 'power2.out', delay: 0.3
            });
          },
          once: true
        });
      }

      // Draw dividers
      dividers.forEach(function (d) {
        gsap.set(d, { scaleX: 0, transformOrigin: 'left center' });
        ScrollTrigger.create({
          trigger: d,
          start: 'top 85%',
          onEnter: function () {
            gsap.to(d, { scaleX: 1, duration: 1.2, ease: 'power2.inOut' });
          },
          once: true
        });
      });

      // Mask-reveal images (clip-path wipe)
      images.forEach(function (img) {
        gsap.set(img, { clipPath: 'inset(0 0 100% 0)', scale: 1.08 });
        ScrollTrigger.create({
          trigger: img,
          start: 'top 80%',
          onEnter: function () {
            gsap.to(img, {
              clipPath: 'inset(0 0 0% 0)', scale: 1,
              duration: 1.2, ease: 'power3.out'
            });
          },
          once: true
        });
      });
    },

    'parallax-split': function (section) {
      // Find the grid/row that contains 2 columns
      var grid = section.querySelector('[style*="grid-template-columns"]') ||
                 section.querySelector('.row-block > div');

      if (grid) {
        var cols = Array.from(grid.children);
        if (cols.length >= 2) {
          gsap.to(cols[0], {
            y: -80,
            ease: 'none',
            scrollTrigger: {
              trigger: section,
              start: 'top bottom',
              end: 'bottom top',
              scrub: true
            }
          });
          gsap.to(cols[1], {
            y: 80,
            ease: 'none',
            scrollTrigger: {
              trigger: section,
              start: 'top bottom',
              end: 'bottom top',
              scrub: true
            }
          });
        }
      }

      // Also reveal content
      var content = findContent(section);
      gsap.set(content, { opacity: 0, y: 40 });
      ScrollTrigger.create({
        trigger: section,
        start: 'top 70%',
        onEnter: function () {
          gsap.to(content, {
            opacity: 1, y: 0,
            duration: 0.8, stagger: 0.1, ease: 'power2.out'
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
      scenes['fade-through'](section);
    }
  });

  // ─── Atmosphere ───
  var configEl = document.getElementById('experience-config');
  var atmos = configEl ? JSON.parse(configEl.textContent) : {};

  if (atmos.preloader) {
    var loader = document.createElement('div');
    loader.className = 'experience-preloader';
    loader.innerHTML = '<div class="preloader-inner"><div class="preloader-count">0</div><div class="preloader-bar"><div class="preloader-fill"></div></div></div>';
    document.body.appendChild(loader);
    document.body.style.overflow = 'hidden';
    var count = loader.querySelector('.preloader-count');
    var fill = loader.querySelector('.preloader-fill');
    var obj = { val: 0 };
    gsap.to(obj, {
      val: 100, duration: 2.5, ease: 'power2.inOut',
      onUpdate: function () {
        count.textContent = Math.round(obj.val);
        fill.style.width = obj.val + '%';
      },
      onComplete: function () {
        gsap.to(loader, {
          opacity: 0, duration: 0.6, delay: 0.3,
          onComplete: function () {
            loader.remove();
            document.body.style.overflow = '';
            ScrollTrigger.refresh();
          }
        });
      }
    });
  }

  if (atmos.cursor && window.matchMedia('(pointer: fine)').matches) {
    var cursor = document.createElement('div');
    cursor.className = 'experience-cursor';
    cursor.innerHTML = '<span class="cursor-dot"></span><span class="cursor-ring"></span>';
    document.body.appendChild(cursor);
    document.documentElement.classList.add('experience-cursor-active');
    var dot = cursor.querySelector('.cursor-dot');
    var ring = cursor.querySelector('.cursor-ring');
    var mx = 0, my = 0, cx = 0, cy = 0;
    document.addEventListener('mousemove', function (e) {
      mx = e.clientX; my = e.clientY;
      gsap.set(dot, { x: mx, y: my });
    });
    gsap.ticker.add(function () {
      cx += (mx - cx) * 0.12;
      cy += (my - cy) * 0.12;
      gsap.set(ring, { x: cx, y: cy });
    });
    document.addEventListener('mouseenter', function (e) {
      if (e.target.matches && e.target.matches('a, button, [role="button"], summary')) {
        ring.classList.add('is-hover');
      }
    }, true);
    document.addEventListener('mouseleave', function (e) {
      if (e.target.matches && e.target.matches('a, button, [role="button"], summary')) {
        ring.classList.remove('is-hover');
      }
    }, true);
  }

  // Skip button
  var skipBtn = document.createElement('button');
  skipBtn.className = 'experience-skip';
  skipBtn.textContent = 'Disable animations';
  skipBtn.addEventListener('click', function () {
    localStorage.setItem('ensodo:experience:off', '1');
    location.reload();
  });
  document.body.appendChild(skipBtn);

})();
