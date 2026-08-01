# Inline Edit — scope wall

The inline editor is a **quick-fix layer over the preview**, not a second page
builder. This boundary is a **product decision**, not a technical limitation:
structural work stays in the Page Editor, where it has the right affordances,
history, and validation.

## The inline editor DOES

- Edit the value of a single existing field in place: heading text, rich-text
  content, image choice (pilot set). More field types follow the same contract.
- Show a floating toolbar (bold / italic / link for rich text, undo, "Open in
  Page Editor").
- Save edited values into a **draft** (Phase 3), never directly into published
  state.

## The inline editor does NOT

- Add, delete, or reorder blocks.
- Change columns, sections, layout, or hierarchy.
- Change block settings (style, animation, responsive, advanced).
- Edit shared entities — slider, menu, magazine document, theme. Fields that
  resolve to a shared entity are rendered **locked** with an "edit in the
  library" affordance.
- Upload files. Images are **chosen** from the existing media library only.
- Publish without an explicit user action. Autosave writes drafts; it never
  publishes.

Everything in the "does NOT" list remains in the Page Editor.

## Draft model (resolved in Phase 0)

"Draft" in this system means the **live `blocks` rows** owned by a page with
`status = draft`, plus the existing publish path (`PublishSiteJob`). It does
**not** mean a `page_versions` snapshot row — those stay append-only
publish/restore artifacts. See the Phase 0 audit verdict.

## Parent surface (resolved in Phase 2)

The parent shell is the React `PreviewPane` in the admin SPA, which already
embeds the auth-protected API preview (`PreviewController`). Edit mode is a gated
toggle on that same preview; the overlay is injected server-side only in
`RenderMode::Edit` after a policy check.
