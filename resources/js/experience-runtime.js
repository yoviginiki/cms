/**
 * Ensodo CMS — Cinematic Experience Runtime v2.2 — Polished
 *
 * Smoother timings, longer easing curves, more dramatic reveals.
 * MiSO-inspired: slower builds, heavier weight, breathing room.
 */

import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);
window.gsap = gsap;
window.ScrollTrigger = ScrollTrigger;

// Global defaults — slower, smoother
gsap.defaults({ ease: 'expo.out', duration: 1.6 });

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
      var images = findImages(section);
      if (!items.length) return;

      // Non-image content: fade up
      var nonImages = items.filter(function (el) { return images.indexOf(el) === -1; });
      gsap.set(nonImages, { opacity: 0, y: 60 });

      ScrollTrigger.create({
        trigger: section,
        start: 'top 80%',
        onEnter: function () {
          gsap.to(nonImages, {
            opacity: 1, y: 0,
            duration: 1.8, stagger: 0.18, ease: 'expo.out'
          });
        },
        once: true
      });

      // Images: mask-reveal wipe + grayscale→color
      images.forEach(function (img) {
        gsap.set(img, { clipPath: 'inset(0 0 100% 0)', scale: 1.08, opacity: 0.85, filter: 'grayscale(100%)' });
        ScrollTrigger.create({
          trigger: img,
          start: 'top 82%',
          onEnter: function () {
            gsap.to(img, {
              clipPath: 'inset(0 0 0% 0)', scale: 1, opacity: 1, filter: 'grayscale(0%)',
              duration: 1.8, ease: 'expo.out'
            });
          },
          once: true
        });
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
          end: function () { return '+=' + Math.max(section.offsetHeight * 2.5, 1800); },
          pin: true,
          scrub: 2.5, // MiSO-weight scrub
          anticipatePin: 1
        }
      });

      var pos = 0;

      // Headings: slow char reveal
      splitGroups.forEach(function (chars) {
        tl.to(chars, {
          opacity: 1, y: 0, rotateX: 0,
          duration: 1.2, stagger: 0.035, ease: 'expo.out'
        }, pos);
        pos += 0.5;
      });

      // Dividers: elegant draw
      dividers.forEach(function (d) {
        tl.to(d, { scaleX: 1.02, duration: 1.2, ease: 'expo.out' }, pos);
        tl.to(d, { scaleX: 1, duration: 0.4, ease: 'power2.inOut' }, pos + 0.8);
        pos += 0.3;
      });

      // Paragraphs: gentle fade up
      paragraphs.forEach(function (p) {
        tl.to(p, { opacity: 1, y: 0, duration: 1, ease: 'expo.out' }, pos);
        pos += 0.3;
      });

      // Hold at end for a beat
      tl.to({}, { duration: 1 }, pos);
    },

    'scroll-gallery': function (section) {
      // Find top-level rows inside the section — each row is a complete "slide"
      var innerDiv = section.querySelector(':scope > div') || section;
      var slides = Array.from(innerDiv.querySelectorAll('.row-block'));

      // Fallback: if no row-blocks, try direct children of inner div
      if (slides.length < 2) {
        slides = Array.from(innerDiv.children).filter(function (el) {
          return el.offsetHeight > 50 && !el.classList.contains('spacer-block');
        });
      }

      if (slides.length < 2) {
        // Not enough slides — fall back to fade-through
        scenes['fade-through'](section);
        return;
      }

      // Stack slides: first visible, rest hidden underneath
      innerDiv.style.position = 'relative';
      innerDiv.style.minHeight = '80vh';

      slides.forEach(function (slide, i) {
        slide.style.width = '100%';
        if (i === 0) {
          slide.style.position = 'relative';
        } else {
          slide.style.position = 'absolute';
          slide.style.top = '0';
          slide.style.left = '0';
          gsap.set(slide, { opacity: 0, y: 30 });
        }
      });

      // Progress dots
      var progress = document.createElement('div');
      progress.style.cssText = 'position:fixed;right:60px;top:50%;transform:translateY(-50%);display:flex;flex-direction:column;gap:12px;z-index:9998;';
      slides.forEach(function (_, i) {
        var dot = document.createElement('span');
        dot.style.cssText = 'width:8px;height:8px;border-radius:50%;background:var(--color-text-muted,#B7AF96);opacity:' + (i === 0 ? '1' : '0.25') + ';transition:all 0.4s ease;';
        progress.appendChild(dot);
      });
      section.appendChild(progress);
      var dots = progress.querySelectorAll('span');

      // Pin the section and crossfade through slides
      var tl = gsap.timeline({
        scrollTrigger: {
          trigger: section,
          start: 'top top',
          end: '+=' + (slides.length * 150) + '%',
          pin: true,
          scrub: 2.5,
          onUpdate: function (self) {
            var idx = Math.round(self.progress * (slides.length - 1));
            dots.forEach(function (d, i) {
              d.style.opacity = i === idx ? '1' : '0.25';
              d.style.transform = i === idx ? 'scale(1.5)' : 'scale(1)';
            });
          },
          onLeave: function () { progress.style.display = 'none'; },
          onEnterBack: function () { progress.style.display = 'flex'; }
        }
      });

      // Crossfade entire slides (image + heading + text all together)
      for (var i = 0; i < slides.length - 1; i++) {
        tl.to(slides[i], { opacity: 0, y: -20, duration: 0.8, ease: 'power2.inOut' }, i)
          .to(slides[i + 1], { opacity: 1, y: 0, duration: 1.2, ease: 'expo.out' }, i + 0.15);
      }
      // Hold last slide
      tl.to({}, { duration: 0.8 });
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
              duration: 1.4, stagger: 0.04, ease: 'expo.out'
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
              duration: 1.6, stagger: 0.22, ease: 'expo.out', delay: 0.5
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
            gsap.to(d, { scaleX: 1.02, duration: 1.8, ease: 'expo.out', onComplete: function() { gsap.to(d, { scaleX: 1, duration: 0.4 }); } });
          },
          once: true
        });
      });

      // Images — clip-path mask wipe + subtle scale
      images.forEach(function (img) {
        gsap.set(img, { clipPath: 'inset(0 0 100% 0)', scale: 1.12, opacity: 0.8, filter: 'grayscale(100%)' });
        ScrollTrigger.create({
          trigger: img,
          start: 'top 80%',
          onEnter: function () {
            gsap.to(img, {
              clipPath: 'inset(0 0 0% 0)', scale: 1, opacity: 1, filter: 'grayscale(0%)',
              duration: 2, ease: 'expo.out'
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
            y: -120,
            ease: 'none',
            scrollTrigger: { trigger: section, start: 'top bottom', end: 'bottom top', scrub: 1.5 }
          });
          gsap.to(cols[1], {
            y: 120,
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
        gsap.set(img, { clipPath: 'inset(0 100% 0 0)', scale: 1.1, filter: 'grayscale(100%)' });
        ScrollTrigger.create({
          trigger: img,
          start: 'top 75%',
          onEnter: function () {
            gsap.to(img, {
              clipPath: 'inset(0 0% 0 0)', scale: 1, filter: 'grayscale(0%)',
              duration: 1.8, ease: 'expo.out'
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

  // ─── Refresh discipline ───
  // The preloader sets body.overflow='hidden' which makes ScrollTrigger
  // unable to measure. ALL refreshes must wait until overflow is restored.
  // We track this with a flag that the preloader sets when it's done.
  var preloaderDone = false;
  var pendingRefresh = false;
  var refreshTimer;

  function safeRefresh() {
    // If preloader is still hiding overflow, defer
    if (!preloaderDone && document.body.style.overflow === 'hidden') {
      pendingRefresh = true;
      return;
    }
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(function () {
      ScrollTrigger.refresh();
    }, 100);
  }

  // Called by preloader when it's done (overflow restored)
  window.__cinematicPreloaderDone = function () {
    preloaderDone = true;
    // Fire the refresh now that overflow is gone
    ScrollTrigger.refresh();
    // And again after media may have decoded
    setTimeout(function () { ScrollTrigger.refresh(); }, 300);
    setTimeout(function () { ScrollTrigger.refresh(); }, 1000);
  };

  // If no preloader, mark as done immediately
  var configEl2 = document.getElementById('experience-config');
  var atmos2 = configEl2 ? JSON.parse(configEl2.textContent) : {};
  if (!atmos2.preloader) {
    preloaderDone = true;
  }

  // Video loadeddata + canplay
  document.querySelectorAll('video').forEach(function (v) {
    v.addEventListener('loadeddata', safeRefresh);
    v.addEventListener('canplay', safeRefresh);
  });

  // Images
  document.querySelectorAll('img').forEach(function (img) {
    if (!img.complete) {
      img.addEventListener('load', safeRefresh);
      img.addEventListener('error', safeRefresh);
    }
  });

  // Fonts
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(safeRefresh);
  }

  // Window load
  window.addEventListener('load', function () {
    safeRefresh();
    setTimeout(safeRefresh, 500);
  });

  // Resize
  var resizeTimer2;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer2);
    resizeTimer2 = setTimeout(function () { ScrollTrigger.refresh(); }, 200);
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
            // Signal that overflow is restored — safe to refresh now
            if (window.__cinematicPreloaderDone) window.__cinematicPreloaderDone();
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
      cx += (mx - cx) * 0.06; // slower follow = more weight
      cy += (my - cy) * 0.06;
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
