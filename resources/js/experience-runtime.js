/**
 * Ensodo CMS — Cinematic Experience Runtime v2.2 — Polished
 *
 * Smoother timings, longer easing curves, more dramatic reveals.
 * MiSO-inspired: slower builds, heavier weight, breathing room.
 */

import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);
window.ScrollTrigger = ScrollTrigger;

// Global defaults — slower, smoother
gsap.defaults({ ease: 'power3.out', duration: 1.2 });

(function () {
  'use strict';

  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var userOff = localStorage.getItem('ensodo:experience:off') === '1';

  if (window.location.search.indexOf('experience=force') !== -1) {
    localStorage.removeItem('ensodo:experience:off');
    userOff = false;
  }

  if (reducedMotion || userOff) {
    document.documentElement.classList.add('experience-reduced');
    if (userOff && !reducedMotion) {
      var enableBtn = document.createElement('button');
      enableBtn.className = 'experience-skip';
      enableBtn.style.opacity = '1';
      enableBtn.textContent = 'Enable animations';
      enableBtn.addEventListener('click', function () {
        localStorage.removeItem('ensodo:experience:off');
        location.reload();
      });
      document.addEventListener('DOMContentLoaded', function() {
        document.body.appendChild(enableBtn);
      });
    }
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

  function findHeadings(s) { return Array.from(s.querySelectorAll('h1, h2, h3')); }
  function findParagraphs(s) { return Array.from(s.querySelectorAll('p, .paragraph-block .prose, .rich-text-block .prose')); }
  function findDividers(s) { return Array.from(s.querySelectorAll('hr, .divider-block')); }
  function findImages(s) { return Array.from(s.querySelectorAll('img, figure, .image-block, .video-hero')); }
  function findContent(s) { return Array.from(s.querySelectorAll('h1, h2, h3, h4, p, img, figure, video, .video-hero, .divider-block, .image-block, .heading-block, .paragraph-block, .rich-text-block')); }

  // ─── Scene Registry ───
  var scenes = {

    'fade-through': function (section) {
      var items = findContent(section);
      if (!items.length) return;

      gsap.set(items, { opacity: 0, y: 60 });

      ScrollTrigger.create({
        trigger: section,
        start: 'top 80%',
        onEnter: function () {
          gsap.to(items, {
            opacity: 1, y: 0,
            duration: 1.4, stagger: 0.15, ease: 'power3.out'
          });
        },
        once: true
      });
    },

    'pinned-statement': function (section) {
      var headings = findHeadings(section);
      var paragraphs = findParagraphs(section);
      var dividers = findDividers(section);

      var splitGroups = [];
      headings.forEach(function (h) {
        var chars = splitText(h);
        gsap.set(chars, { opacity: 0, y: 30, rotateX: -40 });
        splitGroups.push(chars);
      });

      paragraphs.forEach(function (p) { gsap.set(p, { opacity: 0, y: 40 }); });
      dividers.forEach(function (d) { gsap.set(d, { scaleX: 0, transformOrigin: 'left center' }); });

      var tl = gsap.timeline({
        scrollTrigger: {
          trigger: section,
          start: 'top top',
          end: function () { return '+=' + Math.max(section.offsetHeight * 2, 1200); },
          pin: true,
          scrub: 1.5, // slower scrub = smoother
          anticipatePin: 1
        }
      });

      var pos = 0;

      // Headings: slow char reveal
      splitGroups.forEach(function (chars) {
        tl.to(chars, {
          opacity: 1, y: 0, rotateX: 0,
          duration: 0.8, stagger: 0.025, ease: 'power3.out'
        }, pos);
        pos += 0.5;
      });

      // Dividers: elegant draw
      dividers.forEach(function (d) {
        tl.to(d, { scaleX: 1, duration: 0.8, ease: 'expo.inOut' }, pos);
        pos += 0.3;
      });

      // Paragraphs: gentle fade up
      paragraphs.forEach(function (p) {
        tl.to(p, { opacity: 1, y: 0, duration: 0.7, ease: 'power2.out' }, pos);
        pos += 0.3;
      });

      // Hold at end for a beat
      tl.to({}, { duration: 0.5 }, pos);
    },

    'scroll-gallery': function (section) {
      var images = findImages(section);
      var headings = findHeadings(section);
      var paragraphs = findParagraphs(section);
      var dividers = findDividers(section);

      // First: reveal headings/text with fade-in (they stay visible)
      var textItems = [].concat(headings, paragraphs, dividers);
      if (textItems.length) {
        gsap.set(textItems, { opacity: 0, y: 40 });
        ScrollTrigger.create({
          trigger: section,
          start: 'top 80%',
          onEnter: function () {
            gsap.to(textItems, {
              opacity: 1, y: 0,
              duration: 1.2, stagger: 0.15, ease: 'power3.out'
            });
          },
          once: true
        });
      }

      // Gallery: only crossfade actual images (not headings/text)
      if (images.length < 2) {
        // Single image — just mask-reveal it
        if (images.length === 1) {
          gsap.set(images[0], { clipPath: 'inset(0 0 100% 0)', scale: 1.08 });
          ScrollTrigger.create({
            trigger: images[0],
            start: 'top 80%',
            onEnter: function () {
              gsap.to(images[0], {
                clipPath: 'inset(0 0 0% 0)', scale: 1,
                duration: 1.4, ease: 'power4.out'
              });
            },
            once: true
          });
        }
        return;
      }

      // Stack images in a gallery container
      var galleryWrap = document.createElement('div');
      galleryWrap.style.cssText = 'position:relative;min-height:50vh;margin:2rem 0;';
      images[0].parentElement.insertBefore(galleryWrap, images[0]);

      images.forEach(function (img, i) {
        galleryWrap.appendChild(img);
        img.style.position = i === 0 ? 'relative' : 'absolute';
        img.style.top = '0';
        img.style.left = '0';
        img.style.width = '100%';
        if (i > 0) gsap.set(img, { opacity: 0, scale: 1.04 });
      });

      // Progress dots
      var progress = document.createElement('div');
      progress.style.cssText = 'position:absolute;bottom:24px;left:50%;transform:translateX(-50%);display:flex;gap:12px;z-index:5;';
      images.forEach(function (_, i) {
        var dot = document.createElement('span');
        dot.style.cssText = 'width:8px;height:8px;border-radius:50%;background:var(--color-text-muted,#B7AF96);opacity:' + (i === 0 ? '1' : '0.25') + ';transition:all 0.4s ease;';
        progress.appendChild(dot);
      });
      galleryWrap.appendChild(progress);
      var dots = progress.querySelectorAll('span');

      // Pin the SECTION and crossfade images
      var tl = gsap.timeline({
        scrollTrigger: {
          trigger: section,
          start: 'top top',
          end: '+=' + (images.length * 120) + '%',
          pin: true,
          scrub: 1,
          onUpdate: function (self) {
            var idx = Math.round(self.progress * (images.length - 1));
            dots.forEach(function (d, i) {
              d.style.opacity = i === idx ? '1' : '0.25';
              d.style.transform = i === idx ? 'scale(1.5)' : 'scale(1)';
            });
          }
        }
      });

      for (var i = 0; i < images.length - 1; i++) {
        tl.to(images[i], { opacity: 0, scale: 1.06, duration: 0.6, ease: 'power2.inOut' }, i)
          .to(images[i + 1], { opacity: 1, scale: 1, duration: 0.8, ease: 'power2.out' }, i + 0.15);
      }
      tl.to({}, { duration: 0.3 });
    },

    'reveal': function (section) {
      var headings = findHeadings(section);
      var paragraphs = findParagraphs(section);
      var dividers = findDividers(section);
      var images = findImages(section);

      // Split headings — dramatic 3D char reveal
      headings.forEach(function (h) {
        var chars = splitText(h);
        gsap.set(chars, { opacity: 0, y: 50, rotateX: -60, transformOrigin: 'bottom center' });

        ScrollTrigger.create({
          trigger: h,
          start: 'top 85%',
          onEnter: function () {
            gsap.to(chars, {
              opacity: 1, y: 0, rotateX: 0,
              duration: 1, stagger: 0.035, ease: 'power4.out'
            });
          },
          once: true
        });
      });

      // Paragraphs — slow stagger
      if (paragraphs.length) {
        gsap.set(paragraphs, { opacity: 0, y: 50 });
        ScrollTrigger.create({
          trigger: section,
          start: 'top 60%',
          onEnter: function () {
            gsap.to(paragraphs, {
              opacity: 1, y: 0,
              duration: 1.2, stagger: 0.2, ease: 'power3.out', delay: 0.4
            });
          },
          once: true
        });
      }

      // Dividers — slow elegant draw
      dividers.forEach(function (d) {
        gsap.set(d, { scaleX: 0, transformOrigin: 'left center' });
        ScrollTrigger.create({
          trigger: d,
          start: 'top 85%',
          onEnter: function () {
            gsap.to(d, { scaleX: 1, duration: 1.5, ease: 'expo.inOut' });
          },
          once: true
        });
      });

      // Images — clip-path mask wipe + subtle scale
      images.forEach(function (img) {
        gsap.set(img, { clipPath: 'inset(0 0 100% 0)', scale: 1.1, opacity: 0.8 });
        ScrollTrigger.create({
          trigger: img,
          start: 'top 80%',
          onEnter: function () {
            gsap.to(img, {
              clipPath: 'inset(0 0 0% 0)', scale: 1, opacity: 1,
              duration: 1.6, ease: 'power4.out'
            });
          },
          once: true
        });
      });
    },

    'parallax-split': function (section) {
      var grid = section.querySelector('[style*="grid-template-columns"]') ||
                 section.querySelector('.row-block > div');

      if (grid) {
        var cols = Array.from(grid.children);
        if (cols.length >= 2) {
          // Deeper parallax movement
          gsap.to(cols[0], {
            y: -100,
            ease: 'none',
            scrollTrigger: { trigger: section, start: 'top bottom', end: 'bottom top', scrub: 1.5 }
          });
          gsap.to(cols[1], {
            y: 100,
            ease: 'none',
            scrollTrigger: { trigger: section, start: 'top bottom', end: 'bottom top', scrub: 1.5 }
          });
        }
      }

      // Content reveal with stagger
      var content = findContent(section);
      gsap.set(content, { opacity: 0, y: 50 });
      ScrollTrigger.create({
        trigger: section,
        start: 'top 70%',
        onEnter: function () {
          gsap.to(content, {
            opacity: 1, y: 0,
            duration: 1.2, stagger: 0.12, ease: 'power3.out'
          });
        },
        once: true
      });

      // Image mask reveal for images in parallax
      findImages(section).forEach(function (img) {
        gsap.set(img, { clipPath: 'inset(0 100% 0 0)', scale: 1.08 });
        ScrollTrigger.create({
          trigger: img,
          start: 'top 75%',
          onEnter: function () {
            gsap.to(img, {
              clipPath: 'inset(0 0% 0 0)', scale: 1,
              duration: 1.4, ease: 'power4.out'
            });
          },
          once: true
        });
      });
    }
  };

  // ─── Initialize ───
  sections.forEach(function (section) {
    var preset = section.getAttribute('data-scene');
    (scenes[preset] || scenes['fade-through'])(section);
  });

  // ─── BUG FIX A: Media-load refresh inside runtime ───
  var refreshTimer;
  function debouncedRefresh() {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(function () { ScrollTrigger.refresh(); }, 250);
  }
  // Video
  document.querySelectorAll('video').forEach(function (v) {
    v.addEventListener('loadeddata', debouncedRefresh);
  });
  // Images
  document.querySelectorAll('img').forEach(function (img) {
    if (!img.complete) img.addEventListener('load', debouncedRefresh);
  });
  // Fonts
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(debouncedRefresh);
  }
  // Window load fallback
  window.addEventListener('load', function () {
    setTimeout(debouncedRefresh, 400);
  });
  // Resize
  window.addEventListener('resize', function () {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(function () { ScrollTrigger.refresh(); }, 300);
  });

  // ─── Atmosphere ───
  var configEl = document.getElementById('experience-config');
  var atmos = configEl ? JSON.parse(configEl.textContent) : {};

  // Preloader — slower, more dramatic
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
      val: 100, duration: 3, ease: 'power2.inOut',
      onUpdate: function () {
        count.textContent = Math.round(obj.val);
        fill.style.width = obj.val + '%';
      },
      onComplete: function () {
        gsap.to(loader, {
          opacity: 0, duration: 0.8, delay: 0.5, ease: 'power2.inOut',
          onComplete: function () {
            loader.remove();
            document.body.style.overflow = '';
            ScrollTrigger.refresh();
          }
        });
      }
    });
  }

  // Custom cursor — smoother tracking
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
      gsap.to(dot, { x: mx, y: my, duration: 0.1, ease: 'power2.out' });
    });
    gsap.ticker.add(function () {
      cx += (mx - cx) * 0.08; // slower follow = more weight
      cy += (my - cy) * 0.08;
      gsap.set(ring, { x: cx, y: cy });
    });
    document.addEventListener('mouseenter', function (e) {
      if (e.target.matches && e.target.matches('a, button, [role="button"], summary')) {
        gsap.to(ring, { width: 60, height: 60, opacity: 0.15, duration: 0.3 });
      }
    }, true);
    document.addEventListener('mouseleave', function (e) {
      if (e.target.matches && e.target.matches('a, button, [role="button"], summary')) {
        gsap.to(ring, { width: 36, height: 36, opacity: 0.4, duration: 0.3 });
      }
    }, true);
  }

  // Sound
  if (atmos.sound && atmos.soundAsset) {
    var audio = new Audio();
    audio.src = atmos.soundAsset;
    audio.loop = true;
    audio.volume = 0.3;
    var soundOn = false;
    var soundBtn = document.createElement('button');
    soundBtn.className = 'experience-sound';
    soundBtn.innerHTML = '<span class="sound-icon">&#9835;</span> <span class="sound-label">sound off</span>';
    document.body.appendChild(soundBtn);
    soundBtn.addEventListener('click', function () {
      if (soundOn) { audio.pause(); soundBtn.querySelector('.sound-label').textContent = 'sound off'; soundBtn.classList.remove('is-on'); }
      else { audio.play().catch(function(){}); soundBtn.querySelector('.sound-label').textContent = 'sound on'; soundBtn.classList.add('is-on'); }
      soundOn = !soundOn;
    });
  }

  // Skip
  var skipBtn = document.createElement('button');
  skipBtn.className = 'experience-skip';
  skipBtn.textContent = 'Disable animations';
  skipBtn.addEventListener('click', function () {
    localStorage.setItem('ensodo:experience:off', '1');
    location.reload();
  });
  document.body.appendChild(skipBtn);

})();
