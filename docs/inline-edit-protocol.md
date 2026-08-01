# Inline Edit — postMessage protocol

The inline-edit layer runs across a **parent ↔ iframe** boundary and communicates
**exclusively via `window.postMessage`**, even when both frames share an origin.
The parent never touches the iframe DOM through `contentWindow`, and the overlay
never reaches into `window.parent` beyond posting messages. This keeps the two
halves swappable if the preview ever moves to a tenant origin.

- **Parent** = the admin shell (React `PreviewPane`), hosts the floating toolbar,
  the media library, and the save/session logic (Phase 3).
- **iframe** = the server-rendered preview in `RenderMode::Edit`, into which the
  preview controller injects `overlay.js`. The overlay owns editing gestures and
  addressing; it holds no business logic.

## Envelope

Every message is a plain object with a `source` discriminator so the two sides
ignore unrelated traffic (analytics, devtools, other embeds):

```js
// overlay → parent
{ source: 'sp-overlay', type: 'sp:ready', ...payload }
// parent → overlay
{ source: 'sp-parent',  type: 'sp:mode',  ...payload }
```

Receivers MUST verify `event.origin === expectedOrigin` and the `source` field,
and drop anything else. `targetOrigin` is always the concrete admin origin, never
`'*'`.

Field addressing uses the Phase 1 contract: `block` is the block uuid
(`data-sp-block`), `field` is the canonical dot-path into `blocks.data`
(`data-sp-field`), `type` is one of `text | richtext | number | image | url`
(`data-sp-type`).

## iframe → parent

| type | payload | when |
|------|---------|------|
| `sp:ready` | `{ page_id, version_id, blocks: [{ block, field, type, locked }] }` | overlay loaded, DOM indexed |
| `sp:field:focus` | `{ block, field, fieldType, rect }` | an editable element gained focus; `rect` is the element box in **iframe viewport** coordinates. The field type is carried as `fieldType` (not `type`) so it never collides with the envelope's message `type`. |
| `sp:field:dirty` | `{ block, field, value }` | value changed (debounced by the parent, not here) |
| `sp:field:blur` | `{ block, field, value }` | element lost focus; final value for the field |
| `sp:image:request` | `{ block, field }` | user clicked an image field; parent opens the media library |
| `sp:error` | `{ code, message }` | overlay-side failure (unknown field, serialize error, …) |

`value` semantics by type: `text` / `number` → plain string (`textContent`);
`richtext` → HTML string. In Phase 2 the richtext value is transported verbatim
for in-memory preview only. **It is never sent to the API from here** — Phase 3
routes richtext through the Page Editor's TipTap serializer (`WysiwygEditor`)
before any write, so there is a single serialization source of truth.

## parent → iframe

| type | payload | effect |
|------|---------|--------|
| `sp:mode` | `{ mode: 'edit' \| 'view' }` | enable/disable editing gestures |
| `sp:field:set` | `{ block, field, value }` | write a value into the element (toolbar formatting, media pick, undo) |
| `sp:field:lock` | `{ block, field, locked }` | toggle read-only + the "edit in library" affordance |
| `sp:command` | `{ command, value? }` | rich-text formatting on the focused editable (`bold`, `italic`, `createLink` + url). Best-effort `execCommand`; richtext fields only; re-emits `sp:field:dirty` after applying |
| `sp:saved` | `{ version_id, blocks: [...] }` | clear dirty state after a successful save (Phase 3) |
| `sp:conflict` | `{ block, field }` | mark a field as conflicted after a 409 (Phase 3) |

## Coordinates

`rect` from `sp:field:focus` is measured with `getBoundingClientRect()` inside the
iframe. The parent adds the iframe's own on-screen offset before positioning the
floating toolbar, so the toolbar lives in the parent shell (never inside the
iframe) yet tracks the focused element.

## Handshake

```
overlay: DOMContentLoaded → index [data-sp-block] → postMessage sp:ready
parent : on sp:ready       → postMessage sp:mode { mode:'edit' }
overlay: on sp:mode edit   → wire contenteditable / click handlers
user   : focus/type/blur   → sp:field:focus / sp:field:dirty / sp:field:blur
parent : positions toolbar, tracks dirty values (no persistence in Phase 2)
```
