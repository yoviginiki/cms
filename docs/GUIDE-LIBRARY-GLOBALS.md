# The Library & Global Sections

Design something once, reuse it everywhere. The Library stores reusable copies; Globals are live references that update every page when edited.

## The Library

- **Save anything**: right-click a section, row, or selection in either editor → **Save to Library**. Give it a name, category, and tags. A preview thumbnail is generated automatically.
- **Insert**: open the Library panel in the editor, search or filter by tag, and insert — insertion is a **deep copy with fresh IDs**, fully independent of the original.
- **Manage**: the Library page lets you browse, rename, recategorize, preview, and delete items, and export/import a single item as JSON (validated and sanitized on import) — handy for moving designs between sites.
- **Starter sections**: every first-party theme ships a pack of pre-built system sections (heroes, feature grids, CTAs, testimonials, pricing, contact, footers) built from standard blocks and that theme's presets — the blank-page killer.

## Global sections

Promote a Library item to **Global** and it becomes *referenced, not copied*: pages hold a lightweight pointer, and the section's real tree lives in one place.

- **Edit once, update everywhere**: editing a global opens a focused single-section editor; saving flags every page that uses it as stale, and the auto-republish queue pushes the update out.
- **In the editor**, globals are visually tagged (vermilion corner) with an "editing a global — affects N pages" banner so you always know the blast radius.
- **Where used** lists every page referencing a global; deleting is protected by that list.
- **Detach** converts one page's reference back into a local copy (one-way, with confirmation) — future edits to the global no longer affect that page.
- Published output is flat HTML — the global's tree renders inline at publish; there is zero runtime cost.

## Headers & footers

The site header and footer are **not** Library globals — they are **Global Header / Global Footer templates** created under **Templates** and marked *Default*. There is no per-page override. See [Header & Footer](/docs/GUIDE-HEADER-FOOTER) for the full resolution order (rich footer → template → menu) and the archive/grid special cases.
