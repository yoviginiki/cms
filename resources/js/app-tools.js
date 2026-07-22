/**
 * Interactive app-block runtime (NO dependencies, self-hosted, CSP-safe).
 *
 * Powers four reusable CMS blocks:
 *   .rr-breath-tool      → breathing pacer   (breathing-pacer block)
 *   .rr-meditation-tool  → zen timer         (meditation-timer block)
 *   .rr-pelvic-tool      → pelvic coordination(pelvic-trainer block)
 *   .rr-partner-deck     → partner card deck  (partner-deck block)
 *
 * Behaviour is a faithful port of the original Root & Rise site.js, refactored
 * so each tool is (a) scoped to its block root and (b) configurable from block
 * data via a JSON `data-rr-config` attribute. Every configurable value has a
 * safe fallback, so a tool renders and runs even with an empty config.
 *
 * Published next to the static site by App\Support\Blocks\AppToolRender and
 * loaded (deferred) only on pages that contain one of the four block types.
 */
(function () {
  "use strict";

  function readConfig(el) {
    // Config ships as a child <script type="application/json" class="rr-config">
    // (same convention as the slider block); falls back to a data-rr-config
    // attribute. Either may be absent — every tool has safe built-in defaults.
    try {
      var script = el.querySelector(":scope > script.rr-config");
      if (script && script.textContent.trim()) return JSON.parse(script.textContent) || {};
    } catch (_) { /* fall through */ }
    try { return JSON.parse(el.dataset.rrConfig || "{}") || {}; }
    catch (_) { return {}; }
  }

  /* ── Audio cues (optional — timers work without them) ─────────────────── */

  function makeBell(frequency, duration) {
    try {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      var context = new AC();
      var gain = context.createGain();
      var osc = context.createOscillator();
      osc.type = "sine";
      osc.frequency.value = frequency || 440;
      var end = duration || 0.72;
      gain.gain.setValueAtTime(0.0001, context.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.09, context.currentTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + end);
      osc.connect(gain).connect(context.destination);
      osc.start();
      osc.stop(context.currentTime + end + 0.02);
    } catch (_) { /* optional */ }
  }

  function makeMeditationBell() {
    try {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      var context = new AC();
      [196, 294, 392].forEach(function (frequency, index) {
        var osc = context.createOscillator();
        var gain = context.createGain();
        osc.type = "sine";
        osc.frequency.value = frequency;
        var start = context.currentTime + index * 0.03;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.075 / (index + 1), start + 0.04);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 2.8);
        osc.connect(gain).connect(context.destination);
        osc.start(start);
        osc.stop(start + 2.9);
      });
    } catch (_) { /* optional */ }
  }

  /* ── Breathing pacer ──────────────────────────────────────────────────── */

  function initBreathPacer(tool) {
    var phaseCards = Array.prototype.slice.call(tool.querySelectorAll(".phase-settings > label"));
    if (!phaseCards.length) return;

    var ranges = phaseCards.map(function (c) { return c.querySelector('input[type="range"]'); });
    var outputs = phaseCards.map(function (c) { return c.querySelector("output"); });
    var labels = phaseCards.map(function (c) {
      var l = c.querySelector(":scope > span");
      return l ? l.textContent.trim() : "Breathe";
    });
    var minusButtons = phaseCards.map(function (c) { return c.querySelector(".stepper button:first-child"); });
    var plusButtons = phaseCards.map(function (c) { return c.querySelector(".stepper button:last-child"); });
    var equalPhases = ranges.length > 1 && ranges.slice(1).every(function (r) { return r.disabled; });
    var durations = ranges.map(function (r) { return Number(r.value); });
    var initialDurations = durations.slice();
    var roundButtons = Array.prototype.slice.call(tool.querySelectorAll(".round-settings button"));
    var initialRoundButton = roundButtons.find(function (b) { return b.classList.contains("selected"); });
    var defaultRounds = initialRoundButton ? Number(initialRoundButton.textContent) : 5;
    var orb = tool.querySelector(".breath-orb");
    var orbProgress = tool.querySelector(".orb-progress");
    var phaseName = tool.querySelector(".orb-copy strong");
    var phaseSeconds = tool.querySelector(".orb-copy span");
    var roundReadout = tool.querySelectorAll(".round-readout span");
    var sound = tool.querySelector('.sound-toggle input[type="checkbox"]');
    var startButton = tool.querySelector(".tool-actions .button-ink");
    var resetButton = tool.querySelector(".tool-actions .button-quiet");
    var advancedAt = Number(tool.dataset.advancedAt || 0);
    if (!orb || !startButton) return;

    var rounds = defaultRounds, phaseIndex = 0, round = 1;
    var remaining = durations[0], deadline = 0, running = false, complete = false, timer = 0;

    function decimals(v) { return v % 1 ? 1 : 0; }
    function expertMode() { return advancedAt > 0 && Math.max.apply(null, durations) > advancedAt; }
    function expertAccepted() {
      var cb = tool.querySelector(".expert-warning input");
      return !expertMode() || Boolean(cb && cb.checked);
    }
    function renderExpertWarning() {
      var warning = tool.querySelector(".expert-warning");
      if (!expertMode()) { if (warning) warning.remove(); return; }
      if (!warning) {
        warning = document.createElement("div");
        warning.className = "expert-warning";
        warning.innerHTML = "<strong>Expert range: this is not a challenge.</strong>" +
          "<p>Durations above " + advancedAt + " seconds can cause marked air hunger, dizziness or fainting. " +
          "Use them only if a qualified teacher or clinician has assessed the practice for you.</p>" +
          '<label><input type="checkbox"> I understand and I am practising seated or lying down in a safe place.</label>';
        var actions = tool.querySelector(".tool-actions");
        tool.insertBefore(warning, actions);
        warning.querySelector("input").addEventListener("change", render);
      }
    }
    function totalMinutes() {
      return Math.ceil(durations.reduce(function (s, v) { return s + v; }, 0) * rounds / 60);
    }
    function render() {
      var currentDuration = durations[phaseIndex] || 1;
      var progress = Math.max(0, Math.min(1, 1 - remaining / currentDuration));
      var label = labels[phaseIndex] || "Breathe";
      var lower = label.toLowerCase();
      orb.classList.toggle("orb-in", /inhale|in$/.test(lower));
      orb.classList.toggle("orb-out", /exhale|out|soften/.test(lower));
      orb.style.setProperty("--phase-time", currentDuration + "s");
      orbProgress.style.setProperty("--progress", progress * 360 + "deg");
      phaseName.textContent = complete ? "Complete" : label;
      phaseSeconds.textContent = complete ? "Rest in natural breath" : String(Math.max(0, Math.ceil(remaining)));
      if (roundReadout[0]) roundReadout[0].textContent = "Round " + (complete ? rounds : round) + " / " + rounds;
      if (roundReadout[1]) roundReadout[1].textContent = "about " + totalMinutes() + " min";
      outputs.forEach(function (o, i) { o.textContent = durations[i].toFixed(decimals(durations[i])) + "s"; });
      ranges.forEach(function (r, i) {
        r.value = String(durations[i]);
        r.disabled = running || (equalPhases && i > 0);
      });
      minusButtons.concat(plusButtons).forEach(function (b, i) {
        var phase = i % phaseCards.length;
        b.disabled = running || (equalPhases && phase > 0);
      });
      roundButtons.forEach(function (b) {
        b.classList.toggle("selected", Number(b.textContent) === rounds);
        b.disabled = running;
      });
      startButton.textContent = running ? "Pause" : complete ? "Begin again" : "Start practice";
      startButton.disabled = !expertAccepted();
    }
    function stopTimer() { if (timer) window.clearInterval(timer); timer = 0; }
    function reset(restoreDefaults) {
      stopTimer(); running = false; complete = false; phaseIndex = 0; round = 1;
      if (restoreDefaults) initialDurations.forEach(function (v, i) { durations[i] = v; });
      remaining = durations[0];
      renderExpertWarning(); render();
    }
    function nextPhase() {
      if (phaseIndex + 1 < labels.length) { phaseIndex += 1; }
      else if (round < rounds) { phaseIndex = 0; round += 1; }
      else { running = false; complete = true; stopTimer(); if (!sound || sound.checked) makeBell(620); render(); return; }
      remaining = durations[phaseIndex];
      deadline = Date.now() + remaining * 1000;
      if (!sound || sound.checked) makeBell(390 + phaseIndex * 55);
    }
    function tick() { remaining = Math.max(0, (deadline - Date.now()) / 1000); if (remaining <= 0) nextPhase(); render(); }
    function toggle() {
      if (running) { running = false; stopTimer(); render(); return; }
      if (complete || remaining <= 0) reset(false);
      if (!expertAccepted()) return;
      complete = false; running = true;
      deadline = Date.now() + remaining * 1000;
      if (!sound || sound.checked) makeBell(330);
      timer = window.setInterval(tick, 100); render();
    }
    function updateDuration(index, value) {
      var range = ranges[index];
      var min = Number(range.min), max = Number(range.max);
      var safe = Math.max(min, Math.min(max, value));
      if (equalPhases) durations.forEach(function (_, p) { durations[p] = safe; });
      else durations[index] = safe;
      if (!running && index === 0) remaining = safe;
      var warning = tool.querySelector(".expert-warning");
      if (warning) warning.remove();
      renderExpertWarning(); render();
    }

    ranges.forEach(function (r, i) { r.addEventListener("input", function () { updateDuration(i, Number(r.value)); }); });
    minusButtons.forEach(function (b, i) { b.addEventListener("click", function () { updateDuration(i, durations[i] - Number(ranges[i].step || 1)); }); });
    plusButtons.forEach(function (b, i) { b.addEventListener("click", function () { updateDuration(i, durations[i] + Number(ranges[i].step || 1)); }); });
    roundButtons.forEach(function (b) { b.addEventListener("click", function () { rounds = Number(b.textContent); reset(false); }); });
    startButton.addEventListener("click", toggle);
    if (resetButton) resetButton.addEventListener("click", function () { reset(true); });
    renderExpertWarning(); render();
  }

  /* ── Meditation timer ─────────────────────────────────────────────────── */

  function initMeditationTimer(tool) {
    var config = readConfig(tool);
    var journeys = config.journeys && typeof config.journeys === "object" ? config.journeys : {
      "3-day opening": [5, 10, 15],
      "5-day steady": [5, 8, 12, 15, 20],
      "5-day deepening": [10, 15, 20, 25, 30]
    };
    var storeKey = config.storeKey || "rr-med";
    var presets = Array.prototype.slice.call(tool.querySelectorAll(".time-presets button"));
    var ring = tool.querySelector(".zen-ring");
    if (!ring) return;
    var timeOutput = ring.querySelector("strong");
    var statusOutput = ring.querySelector("span");
    var startButton = tool.querySelector(".meditation-timer-panel .tool-actions .button-ink");
    var resetButton = tool.querySelector(".meditation-timer-panel .tool-actions .button-quiet");
    var select = tool.querySelector(".journey-panel select");
    var list = tool.querySelector(".journey-days");
    var minutes = 5, remaining = minutes * 60, running = false, deadline = 0, timer = 0;

    function getCompleted(name) {
      try { return JSON.parse(window.localStorage.getItem(storeKey + "-" + name) || "[]"); } catch (_) { return []; }
    }
    function saveCompleted(name, values) {
      try { window.localStorage.setItem(storeKey + "-" + name, JSON.stringify(values)); } catch (_) { /* optional */ }
    }
    function formatTime(v) {
      return String(Math.floor(v / 60)).padStart(2, "0") + ":" + String(v % 60).padStart(2, "0");
    }
    function renderTimer() {
      var total = minutes * 60;
      var progress = Math.max(0, Math.min(1, 1 - remaining / total));
      ring.style.setProperty("--timer-progress", progress * 360 + "deg");
      if (timeOutput) timeOutput.textContent = formatTime(remaining);
      if (statusOutput) statusOutput.textContent = running ? "sit · breathe · return" : remaining === 0 ? "complete" : "ready";
      if (startButton) startButton.textContent = running ? "Pause" : remaining === 0 ? "Sit again" : "Begin with bell";
      presets.forEach(function (b) { b.classList.toggle("selected", parseInt(b.textContent, 10) === minutes); });
    }
    function choose(value) {
      if (timer) window.clearInterval(timer);
      timer = 0; running = false; minutes = value; remaining = value * 60; renderTimer();
    }
    function tick() {
      remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      if (remaining === 0) { running = false; window.clearInterval(timer); timer = 0; makeMeditationBell(); }
      renderTimer();
    }
    function toggle() {
      if (running) { running = false; window.clearInterval(timer); timer = 0; renderTimer(); return; }
      if (remaining === 0) remaining = minutes * 60;
      deadline = Date.now() + remaining * 1000;
      makeMeditationBell(); running = true; timer = window.setInterval(tick, 250); renderTimer();
    }
    function renderJourney() {
      if (!select || !list) return;
      var name = select.value;
      var completed = getCompleted(name);
      list.innerHTML = "";
      (journeys[name] || []).forEach(function (value, index) {
        var item = document.createElement("li");
        if (completed.indexOf(index) !== -1) item.className = "done";
        item.innerHTML = '<button type="button" class="day-check" aria-label="Mark day ' + (index + 1) + ' complete"><span>' +
          (completed.indexOf(index) !== -1 ? "✓" : index + 1) + "</span></button><div><strong>Day " + (index + 1) +
          "</strong><small>" + value + ' minutes · natural breath</small></div><button type="button" class="journey-start">Set timer</button>';
        item.querySelector(".day-check").addEventListener("click", function () {
          var current = getCompleted(name);
          var at = current.indexOf(index);
          if (at === -1) current.push(index); else current.splice(at, 1);
          saveCompleted(name, current); renderJourney();
        });
        item.querySelector(".journey-start").addEventListener("click", function () { choose(value); });
        list.appendChild(item);
      });
    }
    presets.forEach(function (b) { b.addEventListener("click", function () { choose(parseInt(b.textContent, 10)); }); });
    if (startButton) startButton.addEventListener("click", toggle);
    if (resetButton) resetButton.addEventListener("click", function () { choose(minutes); });
    if (select) select.addEventListener("change", renderJourney);
    renderJourney(); renderTimer();
  }

  /* ── Pelvic coordination ──────────────────────────────────────────────── */

  function initPelvicTool(tool) {
    var config = readConfig(tool);
    var phases = Array.isArray(config.phases) && config.phases.length ? config.phases : [
      { label: "Arrive", cue: "Feel the weight of the pelvis. Do nothing yet.", seconds: 8 },
      { label: "Inhale & widen", cue: "Let the lower ribs, belly and pelvic floor receive the breath.", seconds: 5 },
      { label: "Gentle lift", cue: "Lift at about 30% effort — no glute or abdominal squeeze.", seconds: 3 },
      { label: "Release fully", cue: "Let go for longer than you lifted. Notice the difference.", seconds: 6 }
    ];
    var totalRounds = Number(config.rounds) > 0 ? Number(config.rounds) : 6;
    var visual = tool.querySelector(".pelvic-visual");
    var title = tool.querySelector(".pelvic-copy h2");
    var cue = tool.querySelector(".pelvic-copy > p:not(.eyebrow)");
    var readout = tool.querySelector(".pelvic-copy > strong");
    var startButton = tool.querySelector(".tool-actions .button-ink");
    var resetButton = tool.querySelector(".tool-actions .button-quiet");
    if (!visual || !startButton) return;
    var phase = 0, remaining = phases[0].seconds, round = 1, running = false, complete = false, deadline = 0, timer = 0;

    function render() {
      visual.dataset.phase = String(phase);
      if (title) title.textContent = complete ? "Complete" : phases[phase].label;
      if (cue) cue.textContent = complete
        ? "Rest in natural breath and notice the difference between effort and release."
        : phases[phase].cue;
      if (readout) readout.innerHTML = (complete ? "Done" : remaining + "s") + " <small>· round " + round + "/" + totalRounds + "</small>";
      startButton.textContent = running ? "Pause" : complete ? "Begin again" : "Begin";
    }
    function stop() { if (timer) window.clearInterval(timer); timer = 0; }
    function reset() { stop(); phase = 0; remaining = phases[0].seconds; round = 1; running = false; complete = false; render(); }
    function tick() {
      remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      if (remaining <= 0) {
        if (phase < phases.length - 1) phase += 1;
        else if (round < totalRounds) { phase = 1; round += 1; }
        else { running = false; complete = true; stop(); render(); return; }
        remaining = phases[phase].seconds;
        deadline = Date.now() + remaining * 1000;
      }
      render();
    }
    function toggle() {
      if (running) { running = false; stop(); render(); return; }
      if (complete) reset();
      running = true; deadline = Date.now() + remaining * 1000;
      timer = window.setInterval(tick, 200); render();
    }
    startButton.addEventListener("click", toggle);
    if (resetButton) resetButton.addEventListener("click", reset);
    render();
  }

  /* ── Partner card deck ────────────────────────────────────────────────── */

  function initPartnerDeck(deck) {
    var config = readConfig(deck);
    var cards = Array.isArray(config.cards) && config.cards.length ? config.cards : [
      ["Three-minute arrival", "Sit facing each other. Share one easy breathing rhythm for three minutes. No fixing, no performance, no finish."],
      ["The touch map", "Each person shows three kinds of touch: yes, maybe and not today. Switch roles. Curiosity matters more than agreement."],
      ["Sensate focus I", "Ten minutes of touch on back, arms, face and scalp. Genitals and orgasm stay outside the plan."],
      ["Pause is part of the dance", "Choose a neutral pause word. When it is heard: stop, take three easy exhales, then choose together how to continue."],
      ["An evening without a finish line", "Make penetration and orgasm optional for one evening. Explore play, massage, humour and unhurried contact."],
      ["Five small wishes", "Each writes five ordinary things that help them feel desired. Exchange lists and choose one that feels easy tonight."],
      ["Give and receive", "Take turns receiving ten minutes of touch. The receiver guides with only: softer, firmer, slower, stay, or stop."],
      ["Afterward", "Complete three sentences without debate: I liked… Next time I would enjoy… Right now I feel…"]
    ].map(function (c) { return { title: c[0], body: c[1] }; });
    // normalise [title, body] tuples or {title, body} objects
    cards = cards.map(function (c) { return Array.isArray(c) ? { title: c[0], body: c[1] } : c; });

    var number = deck.querySelector(".deck-number");
    var title = deck.querySelector("h2");
    var copy = deck.querySelector(":scope > p:not(.eyebrow)");
    var button = deck.querySelector(".tool-actions button, button");
    var readout = deck.querySelector(".tool-actions span");
    if (!button || !title) return;
    var index = 0;
    function render() {
      if (number) number.textContent = String(index + 1).padStart(2, "0");
      title.textContent = cards[index].title;
      if (copy) copy.textContent = cards[index].body;
      if (readout) readout.textContent = index + 1 + " / " + cards.length;
    }
    button.addEventListener("click", function () { index = (index + 1) % cards.length; render(); });
    render();
  }

  /* ── Boot ─────────────────────────────────────────────────────────────── */

  function boot() {
    document.querySelectorAll(".rr-breath-tool").forEach(initBreathPacer);
    document.querySelectorAll(".rr-meditation-tool").forEach(initMeditationTimer);
    document.querySelectorAll(".rr-pelvic-tool").forEach(initPelvicTool);
    document.querySelectorAll(".rr-partner-deck").forEach(initPartnerDeck);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
