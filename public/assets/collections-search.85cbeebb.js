/**
 * Stillopress Collections — search island (Track G2/G3). Vanilla JS, no
 * framework, ≤15KB-gzipped budget.
 *
 * Two data modes, chosen at publish via data-cs-mode (the blocks are
 * tier-agnostic):
 *  - static: fetch the flat JSON index (manifest + shards) lazily, filter
 *    in memory. Zero backend.
 *  - api: every query hits the read-only public API (debounced, cancellable,
 *    skeleton + error/retry states); facet counts come from the server.
 *
 * Contracts:
 *  static manifest: { collection, name, count, currency, fields:[{key,label,type,facet}], shards:[url] }
 *  static shard / api row: { u:url, t:title, s?:search text, f?:{facets}, d?:{display}, i?:thumb }
 *  api response: { data:[rows], meta:{ total, next_cursor, facets:{key:{value:n}} } }
 *
 * URL state: ?q=…&<facetKey>=v1,v2 — shareable, back-button-safe.
 * Progressive enhancement: with JS off the static archive listing stands.
 */
(function () {
  'use strict';

  var islands = document.querySelectorAll('[data-cs-role][data-cs-collection]');
  if (!islands.length) return;

  var groups = {};
  islands.forEach(function (el) {
    var key = el.getAttribute('data-cs-collection');
    if (!groups[key]) {
      groups[key] = { els: [], source: el.getAttribute('data-cs-source'), mode: el.getAttribute('data-cs-mode') || 'static' };
    }
    groups[key].els.push(el);
  });

  Object.keys(groups).forEach(function (slug) { initGroup(groups[slug]); });

  function initGroup(group) {
    var state = { q: '', facets: {}, manifest: null, records: null, loading: false, fieldMeta: {}, currency: '€' };
    var isApi = group.mode === 'api';
    var abortCtl = null;

    var searchInputs = [];
    var facetRoots = [];
    var resultRoots = [];
    group.els.forEach(function (el) {
      var role = el.getAttribute('data-cs-role');
      if (role === 'search-box') { var i = el.querySelector('input[type=search]'); if (i) searchInputs.push(i); }
      else if (role === 'facets') facetRoots.push(el);
      else if (role === 'results') resultRoots.push(el);
    });
    // Eager: a dedicated search page shows all records + facets on load,
    // instead of hiding results until the visitor applies a filter.
    var eager = group.els.some(function (el) { return el.hasAttribute('data-cs-eager'); });

    // ── URL state ────────────────────────────────────────────────────────
    function facetKeys() {
      var keys = [];
      facetRoots.forEach(function (root) {
        root.querySelectorAll('[data-cs-facet]').forEach(function (fs) { keys.push(fs.getAttribute('data-cs-facet')); });
      });
      return keys;
    }

    function readUrl() {
      var params = new URLSearchParams(location.search);
      state.q = (params.get('q') || '').trim();
      state.facets = {};
      facetKeys().forEach(function (key) {
        var raw = params.get(key);
        if (raw) state.facets[key] = raw.split(',').filter(Boolean);
      });
    }

    function writeUrl() {
      var params = new URLSearchParams(location.search);
      if (state.q) params.set('q', state.q); else params.delete('q');
      facetKeys().forEach(function (key) {
        var vals = state.facets[key];
        if (vals && vals.length) params.set(key, vals.join(',')); else params.delete(key);
      });
      var qs = params.toString();
      history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
    }

    function hasFilter() {
      return !!state.q || Object.keys(state.facets).some(function (k) { return state.facets[k].length; });
    }

    // ── Static mode: lazy full-index load + in-memory filter ────────────
    function loadIndex() {
      if (state.records || state.loading || !group.source) return Promise.resolve();
      state.loading = true;
      setStatus('Loading…');
      return fetch(group.source)
        .then(function (r) { if (!r.ok) throw new Error('index ' + r.status); return r.json(); })
        .then(function (manifest) {
          state.manifest = manifest;
          state.currency = manifest.currency || '€';
          (manifest.fields || []).forEach(function (f) { state.fieldMeta[f.key] = f; });
          return Promise.all((manifest.shards || []).map(function (u) {
            return fetch(u).then(function (r) { if (!r.ok) throw new Error('shard ' + r.status); return r.json(); });
          }));
        })
        .then(function (shards) { state.records = [].concat.apply([], shards); state.loading = false; })
        .catch(function () { state.loading = false; showError(applyStatic); });
    }

    function matchesQ(row, q) { return !q || (row.s || '').indexOf(q) !== -1; }

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

    function filterStatic() {
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

    function staticFacetCounts(key) {
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

    function applyStatic() {
      loadIndex().then(function () {
        if (!state.records) return;
        var rows = filterStatic();
        renderRows(rows.slice(0, 120));
        setStatus((hasFilter() || eager)
          ? rows.length + ' result' + (rows.length === 1 ? '' : 's') + (rows.length > 120 ? ' — showing first 120, refine your search' : '')
          : '');
        var counts = {};
        facetKeys().forEach(function (k) { counts[k] = staticFacetCounts(k); });
        renderFacets(counts);
      });
    }

    // ── API mode: debounced server queries ──────────────────────────────
    function applyApi() {
      if (abortCtl) abortCtl.abort();
      abortCtl = new AbortController();
      setStatus('Searching…');

      var params = new URLSearchParams();
      if (state.q) params.set('q', state.q);
      Object.keys(state.facets).forEach(function (k) {
        if (state.facets[k].length) params.set(k, state.facets[k].join(','));
      });

      fetch(group.source + (params.toString() ? '?' + params.toString() : ''), { signal: abortCtl.signal })
        .then(function (r) { if (!r.ok) throw new Error('api ' + r.status); return r.json(); })
        .then(function (payload) {
          renderRows(payload.data || []);
          setStatus(hasFilter()
            ? (payload.meta && typeof payload.meta.total === 'number' ? payload.meta.total : (payload.data || []).length) + ' results'
            : '');
          renderFacets((payload.meta && payload.meta.facets) || {});
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          showError(applyApi);
        });
    }

    // ── Shared rendering ─────────────────────────────────────────────────
    function fmtValue(key, raw) {
      var meta = state.fieldMeta[key] || {};
      if (raw === undefined || raw === null || raw === '') return '';
      if (meta.type === 'price' || key === 'price') {
        var n = Number(raw);
        return isFinite(n) ? n.toFixed(2) + ' ' + state.currency : String(raw);
      }
      if (raw === true) return '✓';
      if (raw === false) return '';
      if (Array.isArray(raw)) return raw.join(', ');
      return String(raw);
    }

    function renderRows(rows) {
      var active = hasFilter() || eager;
      resultRoots.forEach(function (root) {
        var grid = root.querySelector('.cs-results');
        var empty = root.querySelector('.cs-empty');
        var tpl = root.querySelector('template[data-cs-card]');
        if (!grid || !tpl) return;

        grid.textContent = '';
        grid.style.display = active ? '' : 'none';
        empty.hidden = !active || rows.length !== 0;

        if (active) {
          rows.forEach(function (row) {
            var card = tpl.content.cloneNode(true);
            card.querySelectorAll('[data-cs-slot="url"]').forEach(function (a) {
              a.setAttribute('href', row.u);
              if (a.getAttribute('data-cs-slot-text') === 'title') a.textContent = row.t;
            });
            var img = card.querySelector('[data-cs-slot="image"]');
            if (img) {
              if (row.i) { img.src = row.i; img.alt = row.t; }
              else (img.closest('a') || img).style.display = 'none';
            }
            card.querySelectorAll('[data-cs-slot-field]').forEach(function (el) {
              var key = el.getAttribute('data-cs-slot-field');
              el.textContent = fmtValue(key, row.d ? row.d[key] : undefined);
            });
            grid.appendChild(card);
          });
        }
      });

      // Static archive listing yields to the results grid while filtering.
      if (resultRoots.length) {
        document.querySelectorAll('.record-loop-block').forEach(function (el) {
          el.style.display = active ? 'none' : '';
        });
      }
    }

    function renderFacets(countsByKey) {
      facetRoots.forEach(function (root) {
        root.querySelectorAll('[data-cs-facet]').forEach(function (fs) {
          var key = fs.getAttribute('data-cs-facet');
          var counts = countsByKey[key] || {};
          var box = fs.querySelector('.cs-facet-options');
          var known = {};
          fs.querySelectorAll('input[data-cs-facet-value]').forEach(function (input) { known[input.value] = true; });

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
            if (countEl) countEl.textContent = '(' + c + ')';
            input.parentElement.style.opacity = c || input.checked ? '' : '.45';
          });
        });
      });
    }

    function setStatus(text) {
      resultRoots.forEach(function (root) {
        var s = root.querySelector('.cs-status');
        if (s) { s.textContent = text; }
        var retry = root.querySelector('.cs-retry');
        if (retry) retry.remove();
      });
    }

    function showError(retryFn) {
      resultRoots.forEach(function (root) {
        var s = root.querySelector('.cs-status');
        if (!s) return;
        s.textContent = 'Search is unavailable right now. ';
        if (!root.querySelector('.cs-retry')) {
          var btn = document.createElement('button');
          btn.className = 'cs-retry';
          btn.type = 'button';
          btn.textContent = 'Retry';
          btn.style.cssText = 'font:inherit;font-size:.85rem;text-decoration:underline;background:none;border:0;cursor:pointer;color:inherit;';
          btn.addEventListener('click', function () { retryFn(); });
          s.appendChild(btn);
        }
      });
    }

    // ── Events ───────────────────────────────────────────────────────────
    var debounceTimer;
    function apply() {
      writeUrl();
      isApi ? applyApi() : applyStatic();
    }

    searchInputs.forEach(function (input) {
      if (!isApi) {
        input.addEventListener('focus', function () { loadIndex(); }, { once: true });
      }
      input.addEventListener('input', function () {
        state.q = input.value.trim();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(apply, isApi ? 250 : 150);
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
        fs.addEventListener('pointerover', function () {
          isApi ? applyApi() : loadIndex().then(applyStatic);
        }, { once: true });
      });
    });

    window.addEventListener('popstate', function () {
      readUrl();
      searchInputs.forEach(function (i) { i.value = state.q; });
      isApi ? applyApi() : applyStatic();
    });

    // Eager start when the URL already carries state (shared link), OR when a
    // block opts in via data-cs-eager (a dedicated search page shows all
    // records + populated facets on load instead of waiting for interaction).
    readUrl();
    if (hasFilter()) {
      searchInputs.forEach(function (i) { i.value = state.q; });
      isApi ? applyApi() : applyStatic();
    } else if (eager) {
      isApi ? applyApi() : loadIndex().then(applyStatic);
    }
  }
})();
