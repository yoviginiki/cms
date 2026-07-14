/**
 * Stillopress Collections — client-side search island (Track G2, Tier 1).
 *
 * Vanilla JS, no framework, size-budgeted (≤15KB gzipped — actual is far
 * under). Binds the search-box / facet-filter / results-grid blocks
 * (data-cs-role attributes), fetches the collection's static JSON index
 * (manifest + shards) lazily on first interaction — or eagerly when the URL
 * already carries search state — and filters in memory.
 *
 * Contract with the publisher (CollectionPublishService):
 *   manifest /{prefix}/index.json:
 *     { collection, name, count, currency, fields:[{key,label,type,facet}], shards:[url] }
 *   shard: [{ u:url, t:title, s:"lowercase search text", f:{key:value|values}, d:{key:raw}, i:thumbUrl }]
 *
 * URL state: ?q=…&<facetKey>=v1,v2 — shareable, back-button-safe
 * (popstate re-applies). Multi-facet logic: AND across fields, OR within.
 * Progressive enhancement: without JS the static archive listing stands.
 */
(function () {
  'use strict';

  var islands = document.querySelectorAll('[data-cs-role][data-cs-collection]');
  if (!islands.length) return;

  // Group islands per collection slug.
  var groups = {};
  islands.forEach(function (el) {
    var key = el.getAttribute('data-cs-collection');
    (groups[key] = groups[key] || { els: [], source: el.getAttribute('data-cs-source') }).els.push(el);
  });

  Object.keys(groups).forEach(function (slug) {
    initGroup(slug, groups[slug]);
  });

  function initGroup(slug, group) {
    var state = {
      q: '',
      facets: {},        // key -> [selected values]
      manifest: null,
      records: null,     // loaded shard rows
      loading: false,
      active: false,
      fieldMeta: {},     // key -> {label,type}
    };

    var searchInputs = [];
    var facetRoots = [];
    var resultRoots = [];
    group.els.forEach(function (el) {
      var role = el.getAttribute('data-cs-role');
      if (role === 'search-box') { var i = el.querySelector('input[type=search]'); if (i) searchInputs.push(i); }
      else if (role === 'facets') facetRoots.push(el);
      else if (role === 'results') resultRoots.push(el);
    });

    // ── URL state ────────────────────────────────────────────────────────
    function readUrl() {
      var params = new URLSearchParams(location.search);
      state.q = (params.get('q') || '').trim();
      state.facets = {};
      facetRoots.forEach(function (root) {
        root.querySelectorAll('[data-cs-facet]').forEach(function (fs) {
          var key = fs.getAttribute('data-cs-facet');
          var raw = params.get(key);
          if (raw) state.facets[key] = raw.split(',').filter(Boolean);
        });
      });
    }

    function writeUrl() {
      var params = new URLSearchParams(location.search);
      if (state.q) params.set('q', state.q); else params.delete('q');
      facetRoots.forEach(function (root) {
        root.querySelectorAll('[data-cs-facet]').forEach(function (fs) {
          var key = fs.getAttribute('data-cs-facet');
          var vals = state.facets[key];
          if (vals && vals.length) params.set(key, vals.join(',')); else params.delete(key);
        });
      });
      var qs = params.toString();
      history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
    }

    // ── Data loading (lazy) ──────────────────────────────────────────────
    function load() {
      if (state.records || state.loading || !group.source) return Promise.resolve();
      state.loading = true;
      setStatus('Loading…');
      return fetch(group.source)
        .then(function (r) { if (!r.ok) throw new Error('index ' + r.status); return r.json(); })
        .then(function (manifest) {
          state.manifest = manifest;
          (manifest.fields || []).forEach(function (f) { state.fieldMeta[f.key] = f; });
          var shardUrls = manifest.shards || [];
          return Promise.all(shardUrls.map(function (u) {
            return fetch(u).then(function (r) { if (!r.ok) throw new Error('shard ' + r.status); return r.json(); });
          }));
        })
        .then(function (shards) {
          state.records = [].concat.apply([], shards);
          state.loading = false;
        })
        .catch(function () {
          state.loading = false;
          setStatus('Search is unavailable right now.');
        });
    }

    // ── Filtering ────────────────────────────────────────────────────────
    function matchesQ(row, q) {
      return !q || (row.s || '').indexOf(q) !== -1;
    }

    function matchesFacet(row, key, selected) {
      var v = row.f ? row.f[key] : undefined;
      if (v === undefined || v === null) return false;
      if (Array.isArray(v)) {
        for (var i = 0; i < v.length; i++) if (selected.indexOf(String(v[i])) !== -1) return true;
        return false;
      }
      if (typeof v === 'boolean') v = v ? 'true' : 'false';
      return selected.indexOf(String(v)) !== -1;
    }

    function filter() {
      var q = state.q.toLowerCase();
      var keys = Object.keys(state.facets).filter(function (k) { return state.facets[k].length; });
      return state.records.filter(function (row) {
        if (!matchesQ(row, q)) return false;
        for (var i = 0; i < keys.length; i++) {
          if (!matchesFacet(row, keys[i], state.facets[keys[i]])) return false;
        }
        return true;
      });
    }

    /** Counts for one facet key: matches under q + every OTHER selected facet. */
    function facetCounts(key) {
      var q = state.q.toLowerCase();
      var keys = Object.keys(state.facets).filter(function (k) { return k !== key && state.facets[k].length; });
      var counts = {};
      state.records.forEach(function (row) {
        if (!matchesQ(row, q)) return;
        for (var i = 0; i < keys.length; i++) {
          if (!matchesFacet(row, keys[i], state.facets[keys[i]])) return;
        }
        var v = row.f ? row.f[key] : undefined;
        if (v === undefined || v === null) return;
        (Array.isArray(v) ? v : [typeof v === 'boolean' ? (v ? 'true' : 'false') : v]).forEach(function (val) {
          val = String(val);
          counts[val] = (counts[val] || 0) + 1;
        });
      });
      return counts;
    }

    // ── Rendering ────────────────────────────────────────────────────────
    var MAX_RENDER = 120;

    function fmtValue(key, raw) {
      var meta = state.fieldMeta[key] || {};
      if (raw === undefined || raw === null || raw === '') return '';
      if (meta.type === 'price') {
        var n = Number(raw);
        return isFinite(n) ? n.toFixed(2) + ' ' + (state.manifest.currency || '€') : String(raw);
      }
      if (meta.type === 'boolean') return raw ? '✓' : '';
      if (Array.isArray(raw)) return raw.join(', ');
      return String(raw);
    }

    function render() {
      if (!state.records) return;
      var rows = filter();
      var hasFilter = !!state.q || Object.keys(state.facets).some(function (k) { return state.facets[k].length; });

      resultRoots.forEach(function (root) {
        var grid = root.querySelector('.cs-results');
        var empty = root.querySelector('.cs-empty');
        var tpl = root.querySelector('template[data-cs-card]');
        if (!grid || !tpl) return;

        grid.textContent = '';
        empty.hidden = rows.length !== 0;

        rows.slice(0, MAX_RENDER).forEach(function (row) {
          var card = tpl.content.cloneNode(true);
          card.querySelectorAll('[data-cs-slot="url"]').forEach(function (a) {
            a.setAttribute('href', row.u);
            if (a.getAttribute('data-cs-slot-text') === 'title') a.textContent = row.t;
          });
          var img = card.querySelector('[data-cs-slot="image"]');
          if (img) {
            if (row.i) { img.src = row.i; img.alt = row.t; }
            else if (img.closest('a')) img.closest('a').style.display = 'none';
          }
          card.querySelectorAll('[data-cs-slot-field]').forEach(function (el) {
            el.textContent = fmtValue(el.getAttribute('data-cs-slot-field'), row.d ? row.d[el.getAttribute('data-cs-slot-field')] : undefined);
          });
          grid.appendChild(card);
        });
      });

      setStatus(rows.length + ' result' + (rows.length === 1 ? '' : 's')
        + (rows.length > MAX_RENDER ? ' — showing first ' + MAX_RENDER + ', refine your search' : ''));

      // Hide the static archive listing while actively filtering (results
      // grid takes over); restore when cleared. JS-off keeps the static list.
      if (resultRoots.length) {
        document.querySelectorAll('.record-loop-block').forEach(function (el) {
          el.style.display = hasFilter ? 'none' : '';
        });
        resultRoots.forEach(function (root) {
          var grid = root.querySelector('.cs-results');
          if (grid) grid.style.display = hasFilter ? '' : 'none';
          var empty = root.querySelector('.cs-empty');
          if (empty && !hasFilter) empty.hidden = true;
        });
        if (!hasFilter) setStatus('');
      }

      renderFacets();
    }

    function renderFacets() {
      facetRoots.forEach(function (root) {
        root.querySelectorAll('[data-cs-facet]').forEach(function (fs) {
          var key = fs.getAttribute('data-cs-facet');
          var counts = facetCounts(key);
          var box = fs.querySelector('.cs-facet-options');
          var known = {};
          fs.querySelectorAll('input[data-cs-facet-value]').forEach(function (input) { known[input.value] = true; });

          // Boolean/relation facets have no static options — fill from data.
          Object.keys(counts).sort().forEach(function (val) {
            if (known[val]) return;
            known[val] = true;
            var label = document.createElement('label');
            label.style.cssText = 'display:flex;align-items:center;gap:.45rem;cursor:pointer;';
            var input = document.createElement('input');
            input.type = 'checkbox';
            input.value = val;
            input.setAttribute('data-cs-facet-value', '');
            var span = document.createElement('span');
            var type = fs.getAttribute('data-cs-facet-type');
            span.textContent = type === 'boolean' ? (val === 'true' ? 'Yes' : 'No') : val;
            var count = document.createElement('span');
            count.className = 'cs-count';
            count.style.cssText = 'opacity:.5;font-size:.8rem;';
            label.appendChild(input); label.appendChild(span); label.appendChild(count);
            box.appendChild(label);
            bindFacetInput(input, key);
          });

          fs.querySelectorAll('input[data-cs-facet-value]').forEach(function (input) {
            input.checked = (state.facets[key] || []).indexOf(input.value) !== -1;
            var c = counts[input.value] || 0;
            var countEl = input.parentElement.querySelector('.cs-count');
            if (countEl) countEl.textContent = c ? '(' + c + ')' : '(0)';
            input.parentElement.style.opacity = c || input.checked ? '' : '.45';
          });
        });
      });
    }

    function setStatus(text) {
      resultRoots.forEach(function (root) {
        var s = root.querySelector('.cs-status');
        if (s) s.textContent = text;
      });
    }

    // ── Events ───────────────────────────────────────────────────────────
    var debounceTimer;
    function apply() {
      writeUrl();
      load().then(render);
    }

    searchInputs.forEach(function (input) {
      // Lazy-load the index the moment the user shows intent.
      input.addEventListener('focus', function () { load(); }, { once: true });
      input.addEventListener('input', function () {
        state.q = input.value.trim();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(apply, 150);
      });
    });

    function bindFacetInput(input, key) {
      input.addEventListener('change', function () {
        var vals = state.facets[key] || [];
        if (input.checked) { if (vals.indexOf(input.value) === -1) vals.push(input.value); }
        else vals = vals.filter(function (v) { return v !== input.value; });
        state.facets[key] = vals;
        apply();
      });
    }

    facetRoots.forEach(function (root) {
      root.querySelectorAll('[data-cs-facet]').forEach(function (fs) {
        var key = fs.getAttribute('data-cs-facet');
        fs.querySelectorAll('input[data-cs-facet-value]').forEach(function (input) { bindFacetInput(input, key); });
        // Load on first pointerover — facet counts need the index.
        fs.addEventListener('pointerover', function () { load().then(renderFacets); }, { once: true });
      });
    });

    window.addEventListener('popstate', function () {
      readUrl();
      searchInputs.forEach(function (i) { i.value = state.q; });
      load().then(render);
    });

    // Eager start when the URL already carries state (shared/bookmarked link).
    readUrl();
    if (state.q || Object.keys(state.facets).length) {
      searchInputs.forEach(function (i) { i.value = state.q; });
      load().then(render);
    }
  }
})();
