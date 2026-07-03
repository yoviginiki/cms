/**
 * Stillopress motion-runtime — ONE module for GSAP timeline construction.
 *
 * Ported from .slider-spec/slider-reference-prototype.html (the canonical
 * behavioral spec). Its SPEC NOTES define: the config JSON shape, the
 * Swiper<->GSAP event contract, the preset table, and the fast-swipe
 * kill/reset rule implemented here.
 *
 * Ships to published pages as a content-hashed static file next to Swiper 11
 * and GSAP 3 core (no SplitText — custom split() below).
 *
 * TODO(motion-runtime): migrate ScrollPage / Flipbook / Cinematic GSAP init
 * into this module. Do not fork per-feature GSAP wiring.
 */
(function (global) {
  'use strict';

  var REDUCED = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ── Presets: single source of truth (mirrors prototype SPEC NOTES §3) ── */
  var PRESETS = {
    'fadeUp'        : { from: { y: 60,  autoAlpha: 0 }, to: { y: 0, autoAlpha: 1 }, ease: 'power3.out' },
    'fadeUp-out'    : { from: { y: 0,   autoAlpha: 1 }, to: { y: -40, autoAlpha: 0 }, ease: 'power2.in' },
    'fadeIn'        : { from: { autoAlpha: 0 },         to: { autoAlpha: 1 },         ease: 'power2.out' },
    'fadeOut'       : { from: { autoAlpha: 1 },         to: { autoAlpha: 0 },         ease: 'power2.in' },
    'slideLeft'     : { from: { x: -80, autoAlpha: 0 }, to: { x: 0, autoAlpha: 1 },   ease: 'power3.out' },
    'slideLeft-out' : { from: { x: 0,   autoAlpha: 1 }, to: { x: -80, autoAlpha: 0 }, ease: 'power2.in' },
    'slideRight'    : { from: { x: 80,  autoAlpha: 0 }, to: { x: 0, autoAlpha: 1 },   ease: 'power3.out' },
    'slideRight-out': { from: { x: 0,   autoAlpha: 1 }, to: { x: 80, autoAlpha: 0 },  ease: 'power2.in' },
    'zoomIn'        : { from: { scale: 0.6, autoAlpha: 0 }, to: { scale: 1, autoAlpha: 1 }, ease: 'back.out(1.6)' },
    'maskWipe'      : { from: { clipPath: 'inset(0 100% 0 0)', autoAlpha: 1 },
                        to:   { clipPath: 'inset(0 0% 0 0)',   autoAlpha: 1 }, ease: 'power3.inOut' }
  };
  var OUT_FALLBACK = 'fadeOut';

  /* ── split(el, mode): custom SplitText replacement (spec: prototype §split) ── */
  function split(el, mode) {
    if (!mode || mode === 'none') return [el];
    if (el._splitDone) return el._splitTargets;
    var wrap = function (txt, cls) { return '<span class="' + cls + '">' + txt + '</span>'; };
    var process = function (node) {
      // snapshot first: replacing children mutates the live NodeList mid-iteration
      Array.prototype.slice.call(node.childNodes).forEach(function (child) {
        if (child.nodeType === 3) {
          var frag = document.createElement('span');
          var tokens = mode === 'chars'
            ? Array.from(child.textContent)
            : child.textContent.split(/(\s+)/);
          frag.innerHTML = tokens.map(function (t) {
            return /^\s+$/.test(t) ? t : wrap(t, mode === 'chars' ? 'sp-char' : 'sp-word');
          }).join('');
          child.replaceWith.apply(child, frag.childNodes);
        } else if (child.nodeType === 1 && child.tagName !== 'BR') {
          process(child);
        }
      });
    };
    process(el);
    var targets = Array.prototype.slice.call(
      el.querySelectorAll(mode === 'chars' ? '.sp-char' : '.sp-word'));
    if (mode === 'lines') {
      targets = Array.prototype.slice.call(el.querySelectorAll('.sp-word'));
      var lines = new Map();
      targets.forEach(function (w) {
        var top = w.offsetTop;
        if (!lines.has(top)) lines.set(top, []);
        lines.get(top).push(w);
      });
      targets = Array.from(lines.values()).map(function (words) {
        var line = document.createElement('span');
        line.className = 'sp-line';
        words[0].before(line);
        words.forEach(function (w) { line.appendChild(w); });
        return line;
      });
    }
    el._splitDone = true;
    el._splitTargets = targets;
    return targets;
  }

  /* ── buildSlideTimeline(slideEl, slideConfig, phase) -> PAUSED gsap.timeline ── */
  function buildSlideTimeline(slideEl, conf, phase) {
    var tl = gsap.timeline({ paused: true });
    (conf.layers || []).forEach(function (layer) {
      var scene = layer.animation && layer.animation[phase];
      var el = slideEl.querySelector('[data-layer-id="' + layer.id + '"]');
      if (!el) return;
      var targets = split(el, layer.animation && layer.animation.split);
      var animTargets = targets.length > 1 ? targets : [el];

      if (!scene) {
        if (phase === 'in') tl.set(el, { autoAlpha: 1 }, 0);
        else tl.to(el, { autoAlpha: 0, duration: 0.25, ease: 'power1.in' }, 0);
        return;
      }
      var stagger = scene.stagger || 0;
      if (scene.preset) {
        var p = PRESETS[scene.preset] || PRESETS[phase === 'out' ? OUT_FALLBACK : 'fadeIn'];
        if (targets.length > 1 && phase === 'in') tl.set(el, { autoAlpha: 1 }, 0);
        tl.fromTo(animTargets, Object.assign({}, p.from), Object.assign({}, p.to, {
          duration: scene.duration || 0.6, ease: p.ease,
          stagger: stagger, immediateRender: phase === 'in'
        }), scene.delay || 0);
      }
      (scene.tracks || []).forEach(function (tr) {
        var fromVars = {}; fromVars[tr.attr] = tr.from;
        var toVars = {}; toVars[tr.attr] = tr.to;
        toVars.duration = tr.duration || 0.6;
        toVars.ease = tr.ease || 'power2.out';
        toVars.immediateRender = phase === 'in';
        tl.fromTo(el, fromVars, toVars, tr.delay || 0);
      });
    });
    return tl;
  }

  /* ── SliderController: Swiper <-> GSAP contract incl. fast-swipe kill/reset ── */
  function SliderController(rootEl, config) {
    var self = this;
    this.root = rootEl;
    this.config = config;

    var slideConf = function (id) {
      return (config.slides || []).find(function (s) { return s.id === id; });
    };
    var baseRotation = function (el) {
      var m = (el.getAttribute('style') || '').match(/rotate\((-?[\d.]+)deg\)/);
      return m ? parseFloat(m[1]) : 0;
    };

    function killLoops(slideEl) {
      (slideEl._loopTweens || []).forEach(function (t) { t.kill(); });
      slideEl._loopTweens = [];
    }

    function applyFinalState(slideEl) {
      var conf = slideConf(slideEl.getAttribute('data-slide-id'));
      if (!conf) return;
      killLoops(slideEl);
      (conf.layers || []).forEach(function (layer) {
        var el = slideEl.querySelector('[data-layer-id="' + layer.id + '"]');
        if (!el) return;
        var targets = split(el, layer.animation && layer.animation.split);
        gsap.killTweensOf([el].concat(targets));
        gsap.set([el].concat(targets), { clearProps: 'x,y,scale,opacity,visibility,clipPath' });
        gsap.set(el, { rotation: baseRotation(el), autoAlpha: 1 });
      });
    }

    function startLoops(slideEl) {
      var conf = slideConf(slideEl.getAttribute('data-slide-id'));
      killLoops(slideEl);
      (conf.layers || []).forEach(function (layer) {
        var loop = layer.animation && layer.animation.loop;
        if (!loop || !loop.tracks) return;
        var el = slideEl.querySelector('[data-layer-id="' + layer.id + '"]');
        if (!el) return;
        loop.tracks.forEach(function (tr) {
          var vars = {};
          vars[tr.attr] = tr.to;
          vars.duration = tr.duration;
          vars.ease = tr.ease || 'sine.inOut';
          vars.yoyo = tr.yoyo !== false;
          vars.repeat = tr.repeat != null ? tr.repeat : -1;
          slideEl._loopTweens.push(gsap.to(el, vars));
        });
      });
    }

    /* KILL/RESET RULE (spec §2): any mid-flight timeline dies, layers land clean */
    function playPhase(slideEl, phase) {
      if (slideEl._activeTl) { slideEl._activeTl.kill(); slideEl._activeTl = null; }
      applyFinalState(slideEl);
      if (REDUCED) return;

      var conf = slideConf(slideEl.getAttribute('data-slide-id'));
      if (!conf) return;
      var tl = buildSlideTimeline(slideEl, conf, phase);
      slideEl._activeTl = tl;
      if (phase === 'out') tl.timeScale(2.5); /* OUT plays fast (~0.4x duration) */
      tl.eventCallback('onComplete', function () {
        slideEl._activeTl = null;
        if (phase === 'in') startLoops(slideEl);
      });
      tl.play();
    }
    this.playPhase = playPhase;
    this.applyFinalState = applyFinalState;

    /* Audio layers: never autoplay; always pause when their slide leaves */
    function pauseMedia(slideEl) {
      slideEl.querySelectorAll('audio').forEach(function (a) { a.pause(); });
    }

    rootEl.setAttribute('data-armed', '1');

    var counterCur = rootEl.querySelector('[data-slider-counter-current]');
    var progressBar = rootEl.querySelector('[data-slider-progress]');
    var bullets = Array.prototype.slice.call(rootEl.querySelectorAll('[data-slider-bullet]'));
    var sw = config.swiper || {};

    var swiper = new Swiper(rootEl.querySelector('.swiper'), {
      effect: sw.effect || 'slide',
      speed: REDUCED ? 250 : (sw.speed || 700),
      loop: !!sw.loop,
      autoplay: sw.autoplay ? { delay: sw.autoplayDelay || 6000, disableOnInteraction: false } : false,
      keyboard: { enabled: sw.keyboard !== false },
      on: {
        init: function (s) {
          var active = s.slides[s.activeIndex];
          if (active) playPhase(active, 'in');
        },
        slideChangeTransitionStart: function (s) {
          var prev = s.slides[s.previousIndex];
          var next = s.slides[s.activeIndex];
          if (prev && prev !== next) { pauseMedia(prev); playPhase(prev, 'out'); }
          if (next) playPhase(next, 'in');
          var realIndex = s.realIndex != null ? s.realIndex : s.activeIndex;
          if (counterCur) counterCur.textContent = String(realIndex + 1);
          bullets.forEach(function (b, i) {
            if (i === realIndex) b.setAttribute('aria-current', 'true');
            else b.removeAttribute('aria-current');
          });
        },
        autoplayTimeLeft: function (s, time, progress) {
          if (progressBar) progressBar.style.transform = 'scaleX(' + (1 - progress) + ')';
        }
      }
    });
    this.swiper = swiper;

    var next = rootEl.querySelector('[data-slider-next]');
    var prev = rootEl.querySelector('[data-slider-prev]');
    if (next) next.addEventListener('click', function () { swiper.slideNext(); });
    if (prev) prev.addEventListener('click', function () { swiper.slidePrev(); });
    bullets.forEach(function (b, i) {
      b.addEventListener('click', function () { swiper.slideToLoop(i); });
    });

    var paused = false;
    var pauseBtn = rootEl.querySelector('[data-slider-pause]');
    if (pauseBtn && sw.autoplay) {
      pauseBtn.addEventListener('click', function () {
        paused = !paused;
        pauseBtn.setAttribute('aria-pressed', String(paused));
        pauseBtn.textContent = paused ? 'Play' : 'Pause';
        paused ? swiper.autoplay.stop() : swiper.autoplay.start();
      });
    }
    if (sw.pauseOnHover && sw.autoplay) {
      rootEl.addEventListener('mouseenter', function () { swiper.autoplay.stop(); });
      rootEl.addEventListener('mouseleave', function () { if (!paused) swiper.autoplay.start(); });
    }

    /* trigger actions: goToSlide from layer config */
    (config.slides || []).forEach(function (s) {
      (s.layers || []).forEach(function (layer) {
        var trig = layer.animation && layer.animation.trigger;
        if (!trig || trig.action !== 'goToSlide') return;
        var el = rootEl.querySelector('[data-layer-id="' + layer.id + '"]');
        if (el) el.addEventListener('click', function () {
          swiper.slideToLoop(parseInt(trig.target, 10) || 0);
        });
      });
    });
  }

  /* ── auto-init: one runtime scan for every published slider on the page ── */
  function initAll() {
    document.querySelectorAll('[data-slider-id]').forEach(function (rootEl) {
      if (rootEl._sliderController) return;
      var cfgEl = rootEl.querySelector('script[data-slider-config]');
      if (!cfgEl || typeof Swiper === 'undefined' || typeof gsap === 'undefined') return;
      try {
        rootEl._sliderController = new SliderController(rootEl, JSON.parse(cfgEl.textContent));
      } catch (e) {
        if (global.console) console.error('slider init failed', e);
      }
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  global.StillopressMotion = {
    buildSlideTimeline: buildSlideTimeline,
    split: split,
    SliderController: SliderController,
    PRESETS: PRESETS,
    initAll: initAll
  };
})(window);
