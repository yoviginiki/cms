/*
 * Grid-area overlay for the admin preview (grid areas as blocks, stage 4).
 * The preview render tags every grid area with data-sp-* (never in published
 * HTML). Two modes, from window.__SP_AREAS:
 *   select — inside the grid editor's iframe: areas are outlined and a click
 *            selects the area in the editor (postMessage to the parent).
 *   link   — standalone page preview: hovering a shared section area shows
 *            "Shared footer · Site footer — Edit ↗" to open its editor.
 */
(function () {
  var cfg = window.__SP_AREAS || {};
  var areas = Array.prototype.slice.call(document.querySelectorAll('[data-sp-area]'));
  if (!areas.length) return;

  var ACCENT = '#ec4899';
  var css = document.createElement('style');
  css.textContent =
    '[data-sp-area]{position:relative}' +
    '.sp-area-badge{position:absolute;top:4px;left:4px;z-index:2147483000;display:flex;align-items:center;gap:6px;' +
    'font:500 11px/1.2 system-ui,sans-serif;color:#fff;background:' + ACCENT + ';padding:3px 7px;border-radius:4px;' +
    'box-shadow:0 1px 3px rgba(0,0,0,.25);pointer-events:auto;white-space:nowrap}' +
    '.sp-area-badge a{color:#fff;text-decoration:underline;font-weight:600}' +
    '.sp-area-badge .sp-muted{opacity:.8;font-weight:400}' +
    (cfg.mode === 'select'
      ? '[data-sp-area]{outline:1px dashed rgba(236,72,153,.45);outline-offset:-1px;cursor:pointer}' +
        '[data-sp-area]:hover{outline:2px solid ' + ACCENT + ';outline-offset:-2px}' +
        '[data-sp-area].sp-area-selected{outline:3px solid ' + ACCENT + ';outline-offset:-3px}' +
        '[data-sp-area] .sp-area-badge{display:none}[data-sp-area]:hover>.sp-area-badge,[data-sp-area].sp-area-selected>.sp-area-badge{display:flex}'
      : '[data-sp-area][data-sp-section]:hover{outline:2px dashed ' + ACCENT + ';outline-offset:-2px}' +
        '[data-sp-area] .sp-area-badge{display:none}[data-sp-area]:hover>.sp-area-badge{display:flex}');
  document.head.appendChild(css);

  function text(el, s) { var n = document.createElement('span'); n.textContent = s; if (el) n.className = el; return n; }

  areas.forEach(function (area) {
    var d = area.dataset;
    var isSection = !!d.spSection;
    if (cfg.mode !== 'select' && !isSection) return; // link mode: only shared sections

    var badge = document.createElement('div');
    badge.className = 'sp-area-badge';
    badge.appendChild(text('', (d.spLabel || d.spArea) + (isSection ? ' · ' + (d.spSectionName || 'section') : '')));
    if (!isSection) badge.appendChild(text('sp-muted', d.spType));
    if (d.spOverride === 'section') badge.appendChild(text('sp-muted', '(this page only)'));
    if (isSection && cfg.sectionEditBase) {
      var a = document.createElement('a');
      a.href = cfg.sectionEditBase + d.spSection + '/edit';
      a.target = '_blank';
      a.rel = 'noopener';
      a.textContent = 'Edit ↗';
      a.addEventListener('click', function (e) { e.stopPropagation(); });
      badge.appendChild(a);
    }
    area.insertBefore(badge, area.firstChild);

    if (cfg.mode === 'select') {
      area.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('.sp-area-badge a')) return;
        e.preventDefault();
        e.stopPropagation();
        // innermost area wins when areas nest
        if (e.currentTarget !== (e.target.closest && e.target.closest('[data-sp-area]'))) return;
        areas.forEach(function (x) { x.classList.remove('sp-area-selected'); });
        area.classList.add('sp-area-selected');
        window.parent.postMessage({ type: 'sp-grid-area', area: d.spArea, positionId: d.spPosition }, cfg.parentOrigin || '*');
      }, true);
    }
  });

  // Parent can highlight an area picked in the editor's schematic.
  window.addEventListener('message', function (e) {
    if (cfg.parentOrigin && e.origin !== cfg.parentOrigin) return;
    var m = e.data || {};
    if (m.type !== 'sp-grid-select') return;
    areas.forEach(function (x) { x.classList.toggle('sp-area-selected', x.dataset.spArea === m.area); });
  });
})();
