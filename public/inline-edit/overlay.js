/*
 * Inline Edit — overlay runtime (iframe side).
 *
 * Injected by PreviewController ONLY in RenderMode::Edit. Owns editing gestures
 * and field addressing; holds no business logic and never persists. Talks to the
 * parent admin shell exclusively via postMessage (see docs/inline-edit-protocol.md).
 *
 * No build step, no dependencies. Safe to no-op if the page carries no
 * [data-sp-block] elements.
 */
(function () {
  'use strict';

  var CONFIG = window.__SP_EDIT || {};
  var PARENT_ORIGIN = CONFIG.parentOrigin || window.location.origin;
  var RICH_ALLOWED = { B: 1, I: 1, A: 1, BR: 1, STRONG: 1, EM: 1 };

  var editing = false;

  // ---- messaging ---------------------------------------------------------

  function send(type, payload) {
    // Envelope `type` is written LAST so a payload key can never clobber the
    // message discriminator (e.g. sp:field:focus carries a field type too).
    var msg = Object.assign({ source: 'sp-overlay' }, payload || {}, { type: type });
    try {
      window.parent.postMessage(msg, PARENT_ORIGIN);
    } catch (e) {
      /* parent gone / origin mismatch — nothing we can do from here */
    }
  }

  function fieldsFor(el) {
    return {
      block: el.getAttribute('data-sp-block') || '',
      field: el.getAttribute('data-sp-field') || '',
      type: el.getAttribute('data-sp-type') || 'text',
      locked: el.getAttribute('data-sp-locked') === '1',
    };
  }

  function valueOf(el, type) {
    // richtext keeps markup; everything else is plain text.
    return type === 'richtext' ? el.innerHTML : (el.textContent || '');
  }

  // ---- editable elements -------------------------------------------------

  function editableEls() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-sp-block][data-sp-field]'));
  }

  function stripRichText(el) {
    // Enforce the allowed-tag whitelist (b/i/a/br). Anything else is unwrapped
    // to its text. This mirrors the server sanitizer's intent; the authoritative
    // serialization still happens through the Page Editor's TipTap path on save.
    var walker = document.createTreeWalker(el, NodeFilter.SHOW_ELEMENT, null);
    var doomed = [];
    var node;
    while ((node = walker.nextNode())) {
      if (!RICH_ALLOWED[node.tagName]) doomed.push(node);
    }
    doomed.forEach(function (n) {
      while (n.firstChild) n.parentNode.insertBefore(n.firstChild, n);
      n.parentNode.removeChild(n);
    });
  }

  function onFocus(el, f) {
    var rect = el.getBoundingClientRect();
    send('sp:field:focus', {
      block: f.block,
      field: f.field,
      // `fieldType` (not `type`) so it never collides with the envelope's
      // message `type`. See docs/inline-edit-protocol.md.
      fieldType: f.type,
      rect: { top: rect.top, left: rect.left, width: rect.width, height: rect.height },
    });
  }

  function onInput(el, f) {
    if (f.type === 'richtext') stripRichText(el);
    if (f.type === 'number') {
      var cleaned = (el.textContent || '').replace(/[^0-9.\-]/g, '');
      if (cleaned !== el.textContent) el.textContent = cleaned;
    }
    send('sp:field:dirty', { block: f.block, field: f.field, value: valueOf(el, f.type) });
  }

  function onBlur(el, f) {
    send('sp:field:blur', { block: f.block, field: f.field, value: valueOf(el, f.type) });
  }

  function lockAffordance(el) {
    el.setAttribute('title', 'Редактирай в библиотеката');
    el.style.cursor = 'not-allowed';
    el.classList.add('sp-locked');
  }

  function enableEl(el) {
    var f = fieldsFor(el);
    el.classList.add('sp-editable');

    if (f.locked) {
      lockAffordance(el);
      return;
    }

    if (f.type === 'image') {
      // Images are replaced from the media library, never typed into.
      el.style.cursor = 'pointer';
      el.addEventListener('click', function (ev) {
        ev.preventDefault();
        send('sp:image:request', { block: f.block, field: f.field });
      });
      return;
    }

    // text / number / richtext → contenteditable
    if (f.type === 'text' || f.type === 'number') {
      el.setAttribute('contenteditable', 'plaintext-only');
      // Fallback for engines that don't honor plaintext-only: block rich paste.
      el.addEventListener('paste', function (ev) {
        ev.preventDefault();
        var text = (ev.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
      });
    } else {
      el.setAttribute('contenteditable', 'true');
    }

    // An editable link (e.g. a button label) must not navigate while editing.
    if (el.tagName === 'A') {
      el.addEventListener('click', function (ev) { ev.preventDefault(); });
    }

    el.addEventListener('focus', function () { onFocus(el, f); });
    el.addEventListener('input', function () { onInput(el, f); });
    el.addEventListener('blur', function () { onBlur(el, f); });
  }

  function disableEl(el) {
    el.removeAttribute('contenteditable');
    el.classList.remove('sp-editable', 'sp-locked');
  }

  function setMode(mode) {
    editing = mode === 'edit';
    editableEls().forEach(editing ? enableEl : disableEl);
  }

  // ---- parent → overlay --------------------------------------------------

  function findEl(block, field) {
    return document.querySelector(
      '[data-sp-block="' + (window.CSS && CSS.escape ? CSS.escape(block) : block) + '"]' +
      '[data-sp-field="' + (window.CSS && CSS.escape ? CSS.escape(field) : field) + '"]'
    );
  }

  function handleParent(data) {
    switch (data.type) {
      case 'sp:mode':
        setMode(data.mode);
        break;
      case 'sp:field:set': {
        var el = findEl(data.block, data.field);
        if (!el) return;
        var f = fieldsFor(el);
        if (f.type === 'richtext') el.innerHTML = data.value;
        else if (f.type === 'image') el.setAttribute('data-sp-image', data.value);
        else el.textContent = data.value;
        break;
      }
      case 'sp:field:lock': {
        var le = findEl(data.block, data.field);
        if (!le) return;
        if (data.locked) { le.setAttribute('data-sp-locked', '1'); disableEl(le); lockAffordance(le); }
        else { le.removeAttribute('data-sp-locked'); if (editing) enableEl(le); }
        break;
      }
      case 'sp:command': {
        // Rich-text formatting from the parent toolbar. Best-effort execCommand
        // on the focused editable; selection survives because the parent toolbar
        // buttons preventDefault on mousedown. richtext only.
        var active = document.activeElement;
        if (!active || active.getAttribute('data-sp-type') !== 'richtext') return;
        try { document.execCommand(data.command, false, data.value || null); } catch (e) { /* noop */ }
        var cf = fieldsFor(active);
        stripRichText(active);
        send('sp:field:dirty', { block: cf.block, field: cf.field, value: valueOf(active, cf.type) });
        break;
      }
      case 'sp:conflict': {
        // Someone changed this block since the session opened — flag the field.
        var ce = findEl(data.block, data.field);
        if (ce) {
          ce.classList.add('sp-conflict');
          ce.style.outline = '2px solid #ef4444';
          ce.setAttribute('title', 'Този блок е променен другаде — презаредете');
        }
        break;
      }
      case 'sp:saved':
        // Persisted — nothing to change in the edit runtime.
        break;
    }
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== PARENT_ORIGIN) return;
    var data = event.data;
    if (!data || data.source !== 'sp-parent') return;
    try {
      handleParent(data);
    } catch (e) {
      send('sp:error', { code: 'handler', message: String(e && e.message || e) });
    }
  });

  // ---- boot --------------------------------------------------------------

  function boot() {
    var blocks = editableEls().map(function (el) {
      var f = fieldsFor(el);
      return { block: f.block, field: f.field, type: f.type, locked: f.locked };
    });
    send('sp:ready', {
      page_id: CONFIG.pageId || null,
      version_id: CONFIG.versionId || null,
      blocks: blocks,
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
