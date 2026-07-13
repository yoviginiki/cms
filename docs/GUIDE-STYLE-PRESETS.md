# Style Presets — design once, use everywhere

Presets are your site's design system layer: named styles that blocks *link to* (not copy), built on the theme's design tokens.

## Element presets

Save any block's style as a named preset for that block type. Blocks that use it stay **linked** — edit the preset and every linked block updates on the next publish. Local overrides on a block sit on top of its preset.

## Option-group presets

Partial presets covering just one aspect — spacing, typography, or borders — usable on **any** block type, and **stackable**: a block can combine an element preset + several option-group presets + local overrides. Resolution order is element preset → option groups → local overrides; last wins.

## Default presets

Each block type can have a default preset, so new blocks are born on-brand. Every first-party theme ships a default preset set, which is why a fresh site looks coherent before you touch anything.

## Tokens, not hex codes

Presets reference theme tokens (`$color.accent`, spacing steps) rather than raw values. Change a token in the Theme Studio and every preset — and every block linked to it — follows. Everything resolves to plain CSS at publish; nothing is computed at runtime.

## The Preset Manager

Grouped by block type: create, edit (with live preview on sample blocks — your real pages are never used as a test surface), duplicate, reorder, set default, and delete (usage-aware). Search included.

## Moving a design system between sites

**Export** bundles your presets and site token overrides into one JSON file; **import** applies them to another site. Combined with Library export/import, a whole design language travels in two files.

## Everyday shortcuts

- **Apply Preset** lives in every block's right-click menu.
- **Copy Style / Paste Style** moves one block's look to another (with granularity: all, typography, spacing, colors, borders).
- **Extend Style** applies the current block's style to all blocks of its type in the section, page, or site — the site-wide version creates a preset and links everything, keeping the system connected instead of sprayed with copies.
