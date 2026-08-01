/*
 * Inline Edit — parent-side controller / floating toolbar.
 *
 * Runs in the SAME document as overlay.js on the standalone web preview
 * (/sites/{slug}/...). Because the preview is a top-level tab, window.parent
 * === window, so the exact parent↔iframe postMessage protocol still applies —
 * overlay.js posts sp-overlay messages, this controller answers with sp-parent
 * messages, and the seam is preserved for a future iframe/tenant-origin split.
 *
 * See docs/inline-edit-protocol.md. No build step, no dependencies.
 */
(function () {
  'use strict';

  var CONFIG = window.__SP_EDIT || {};
  var ORIGIN = CONFIG.parentOrigin || window.location.origin;

  var dirty = {};
  var toolbar, dirtyBadge;

  function toOverlay(msg) {
    // window.parent === window here → loops back to overlay.js in this document.
    window.postMessage(Object.assign({ source: 'sp-parent' }, msg), ORIGIN);
  }

  // ---- persistence (session + debounced autosave) ------------------------

  var session = { ready: false, version: null, hashes: {} };
  var pending = {};           // "block::field" -> { block, field, value }
  var saveTimer = null;
  var DEBOUNCE_MS = 2000;      // Phase 3.2: autosave draft, never publish

  function cookie(name) {
    var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return m ? decodeURIComponent(m.pop()) : '';
  }

  function api(method, path, body) {
    return fetch(path, {
      method: method,
      credentials: 'include',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': cookie('XSRF-TOKEN'),
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: body ? JSON.stringify(body) : undefined,
    });
  }

  function openSession() {
    if (!CONFIG.apiBase) return; // no API wired (e.g. isolated preview) → edit only
    api('POST', CONFIG.apiBase + '/session')
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j) return;
        session.version = j.version;
        (j.blocks || []).forEach(function (b) { session.hashes[b.block] = b.hash; });
        session.ready = true;
      })
      .catch(function () { /* editing still works; just no persistence */ });
  }

  function queuePatch(block, field, value) {
    pending[block + '::' + field] = { block: block, field: field, value: value };
  }

  function scheduleSave() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(flushSave, DEBOUNCE_MS);
  }

  function flushSave() {
    if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
    if (!CONFIG.apiBase) return;
    // Session not open yet (still fetching hashes) → retry after the debounce so
    // an early blur isn't lost.
    if (!session.ready) { if (Object.keys(pending).length) scheduleSave(); return; }

    var keys = Object.keys(pending);
    if (!keys.length) return;

    var patches = keys.map(function (k) {
      var p = pending[k];
      return { block: p.block, field: p.field, value: p.value, block_hash: session.hashes[p.block] };
    });
    pending = {};
    status('Записва…', '#3b82f6');

    api('PATCH', CONFIG.apiBase + '/blocks', { expected_version: session.version, patches: patches })
      .then(function (r) {
        if (r.status === 409) {
          status('Конфликт — презаредете', '#ef4444');
          patches.forEach(function (p) { toOverlay({ type: 'sp:conflict', block: p.block, field: p.field }); });
          return null;
        }
        if (!r.ok) { status('Грешка при запис', '#ef4444'); return null; }
        return r.json();
      })
      .then(function (j) {
        if (!j) return;
        session.version = j.version;
        (j.blocks || []).forEach(function (b) { session.hashes[b.block] = b.hash; });
        dirty = {};
        status('Записано ✓', '#22c55e');
        toOverlay({ type: 'sp:saved', version_id: j.version, blocks: j.blocks });
        setTimeout(function () { if (dirtyBadge.textContent === 'Записано ✓') status('', ''); }, 2000);
      })
      .catch(function () { status('Грешка при запис', '#ef4444'); });
  }

  // ---- UI -----------------------------------------------------------------

  function injectStyles() {
    var css =
      '.sp-editable{outline:1px dashed #6366f1;outline-offset:2px;transition:outline-color .15s}' +
      '.sp-editable:hover{outline-color:#818cf8}' +
      '.sp-editable:focus{outline:2px solid #6366f1}' +
      '.sp-locked{outline:1px dashed #94a3b8;outline-offset:2px;cursor:not-allowed}' +
      '.sp-locked:hover{outline-color:#64748b}' +
      '#sp-toolbar{position:fixed;z-index:2147483000;display:none;align-items:center;gap:4px;' +
      'background:#111827;color:#fff;padding:6px 8px;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.35);' +
      'font:13px system-ui,-apple-system,sans-serif}' +
      '#sp-toolbar button{background:#374151;color:#fff;border:0;border-radius:5px;padding:4px 8px;cursor:pointer;font-size:13px}' +
      '#sp-toolbar button:hover{background:#4b5563}' +
      '#sp-toolbar .sp-sep{width:1px;height:16px;background:#4b5563;margin:0 2px}' +
      '#sp-dirty{position:fixed;bottom:12px;right:12px;z-index:2147483000;background:#f59e0b;color:#111;' +
      'font:12px system-ui;padding:4px 10px;border-radius:999px;display:none;box-shadow:0 4px 12px rgba(0,0,0,.3)}';
    var style = document.createElement('style');
    style.setAttribute('data-sp', 'toolbar');
    style.textContent = css;
    document.head.appendChild(style);
  }

  function makeToolbar() {
    toolbar = document.createElement('div');
    toolbar.id = 'sp-toolbar';
    toolbar.innerHTML =
      '<button data-cmd="bold" data-rich="1" title="Bold"><b>B</b></button>' +
      '<button data-cmd="italic" data-rich="1" title="Italic"><i>I</i></button>' +
      '<button data-cmd="createLink" data-rich="1" title="Link">🔗</button>' +
      '<span class="sp-sep" data-rich="1"></span>' +
      '<button data-cmd="undo" title="Undo">↶</button>' +
      '<button data-cmd="open" title="Отвори в Page Editor">Page Editor</button>';
    // Keep the caret/selection in the edited element while clicking the toolbar.
    toolbar.addEventListener('mousedown', function (e) { e.preventDefault(); });
    toolbar.addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn) return;
      var cmd = btn.getAttribute('data-cmd');
      if (cmd === 'open') {
        if (CONFIG.editUrl) window.location.href = CONFIG.editUrl;
        return;
      }
      if (cmd === 'createLink') {
        var url = window.prompt('Връзка (URL):');
        if (!url) return;
        toOverlay({ type: 'sp:command', command: 'createLink', value: url });
        return;
      }
      toOverlay({ type: 'sp:command', command: cmd });
    });
    document.body.appendChild(toolbar);

    dirtyBadge = document.createElement('div');
    dirtyBadge.id = 'sp-dirty';
    document.body.appendChild(dirtyBadge);
  }

  function showToolbar(rect, isRich) {
    Array.prototype.forEach.call(toolbar.querySelectorAll('[data-rich]'), function (el) {
      el.style.display = isRich ? '' : 'none';
    });
    toolbar.style.display = 'flex';
    // rect is already in this document's viewport coordinates (same document).
    var top = rect.top - 44;
    if (top < 4) top = rect.top + rect.height + 6;
    toolbar.style.top = top + 'px';
    toolbar.style.left = Math.max(4, rect.left) + 'px';
  }

  function status(text, color) {
    if (!dirtyBadge) return;
    dirtyBadge.textContent = text;
    dirtyBadge.style.background = color || '#f59e0b';
    dirtyBadge.style.display = text ? 'block' : 'none';
  }

  function trackDirty(d) {
    dirty[d.block + '::' + d.field] = d.value;
    status(Object.keys(dirty).length + ' незапазени промени', '#f59e0b');
  }

  // ---- protocol -----------------------------------------------------------

  window.addEventListener('message', function (event) {
    if (event.origin !== ORIGIN) return;
    var d = event.data;
    if (!d || d.source !== 'sp-overlay') return;

    switch (d.type) {
      case 'sp:ready':
        toOverlay({ type: 'sp:mode', mode: 'edit' });
        break;
      case 'sp:field:focus':
        showToolbar(d.rect, d.fieldType === 'richtext');
        break;
      case 'sp:field:dirty':
        trackDirty(d);
        queuePatch(d.block, d.field, d.value);
        scheduleSave(); // debounced autosave
        break;
      case 'sp:field:blur':
        trackDirty(d);
        queuePatch(d.block, d.field, d.value);
        flushSave(); // blur = final value → save now
        break;
      case 'sp:image:request': {
        // Phase 3 opens the real media library. Phase 2: pick a URL by hand so
        // the round-trip is exercised without an upload.
        var url = window.prompt('URL на изображение от библиотеката:');
        if (url) toOverlay({ type: 'sp:field:set', block: d.block, field: d.field, value: url });
        break;
      }
    }
  });

  function boot() {
    injectStyles();
    makeToolbar();
    openSession();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
