# Documentation Index

Ensodo CMS Platform documentation. Available online at `https://sys.ensodo.eu/docs` (requires login) or download as ZIP from the docs sidebar.

## User Guides (how to do things in the CMS)

Editing:
- [The Block Editor](GUIDE-BLOCK-EDITOR.md) -- Section→Row→Column→Module, context menu, presets, responsive controls, revisions
- [The Canvas Editor](GUIDE-CANVAS-EDITOR.md) -- freeform section canvases, website/single types, mobile auto-stack, mode switching
- [Magazine Editor Guide](magazine-editor-guide.md) -- the freeform DTP editor
- [The Library & Global Sections](GUIDE-LIBRARY-GLOBALS.md) -- save/reuse designs, edit-once globals, headers & footers
- [Style Presets](GUIDE-STYLE-PRESETS.md) -- element + option-group presets, defaults, tokens, design-system export
- [Menus](GUIDE-MENUS.md) -- navigation, nesting, locations, draft filtering
- [The Media Library](GUIDE-MEDIA-LIBRARY.md) -- uploads, WebP variants, alt text, dedup, usage
- [Forms](GUIDE-FORMS.md) -- contact form block, submissions, email delivery
- [Languages & Translations](GUIDE-TRANSLATIONS.md) -- locales, hreflang, language switcher
- [Publishing & Deploys](GUIDE-PUBLISHING.md) -- auto-publish, quick vs full, staleness, deploy log & lint, rollback
- [Analytics](GUIDE-ANALYTICS.md) -- built-in page views/referrers + optional Google Analytics

SEO & AI:
- [SEO in Stillopress](SEO-IN-STILLOPRESS.md) -- what's automatic, per-page SEO panel, site settings, publish-time lint
- [Writing Content AI Assistants Love to Cite](GEO-AEO-WRITING.md) -- answer-first pattern, FAQ blocks, llms.txt/feeds/AI-crawler settings
- [Structured Data Reference](STRUCTURED-DATA-REFERENCE.md) -- every JSON-LD node emitted, when, and from which fields

Themes & AI generation:
- [Choosing a Theme](THEMES-CHOOSING.md) -- the first-party themes and customization (live demos: stillopress.com/themes)
- [Theme Wizard](THEME-WIZARD.md) -- creating a theme from a reference site or a conversation
- [Issue Studio](issue-studio.md) -- conversational AI magazine creation
- [Page Generation Guide](page-generation-guide.md) -- AI full-site generation

## Architecture & Setup

- [Architecture](ARCHITECTURE.md) -- tech stack, folder structure, two Vite contexts, request lifecycle, multi-tenancy model
- [Auth & Permissions](AUTH.md) -- Sanctum auth, role hierarchy, policies, PostgreSQL RLS

## API & Data

- [API Reference](API-REFERENCE.md) -- all ~150 REST endpoints grouped by controller (32 controllers)
- [Models](MODELS.md) -- 30 Eloquent models with fields, types, and relationships

## Backend Services

- [Services](SERVICES.md) -- all domain services with method signatures (Publishing, Grid, Theme, etc.)
- [Publishing Pipeline](PUBLISHING.md) -- build flow, deploy strategies (local/SSH/ZIP), rollback

## Frontend

- [Admin SPA](ADMIN-SPA.md) -- React 19 architecture, pages, stores, routing, API client
- [Block System](BLOCKS.md) -- 68 frontend blocks, three-layer model, known gaps, audit script

## Design Systems

- [Theme Engine](THEME-ENGINE.md) -- W3C Design Tokens, resolver, compiler, overrides
- [Theme Spec](THEME-SPEC.md) -- token format specification
- [Grid System](GRID-SYSTEM.md) -- CSS Grid layouts, positions, assignments, presets

## Features

- [Site Settings](SITE-SETTINGS.md) -- all JSONB settings fields, SEO defaults, deploy config
- [Magazine Editor](MAGAZINE-EDITOR.md) -- freeform InDesign-like page layout editor
- [Issue Studio](issue-studio.md) -- conversational AI magazine creation (interview -> flatplan -> spreads)

## Block Properties Audit

- [Block Properties Audit](BLOCK-PROPERTIES-AUDIT.md) — comprehensive audit of shared block property system
- [Hero Properties Demo Data](fixtures/hero-properties-demo-data.json) — test fixtures for Hero block properties
- [Block Properties Manual Checklist](BLOCK-PROPERTIES-MANUAL-CHECKLIST.md) — manual QA checklist for property verification

## Block Architecture

- [Ultimate Block System](ULTIMATE-BLOCK-SYSTEM.md) — architecture specification for the Base Block model, professional Hero spec, proposed JSON schema, current-vs-target mapping, and phased roadmap
- [Base Block Property Engine](BASE-BLOCK-PROPERTY-ENGINE.md) — shared property engine contract, supported properties, security rules, adoption guide
- [BaseBlock Inheritance](BASE-BLOCK-INHERITANCE.md) — global shared property inheritance model: property tables, adoption levels, audit strategy, architecture
- [Inline Editing](INLINE-EDITING.md) — general inline editing system: InlineTextField component, InlineEditingConfig contract, safety rules, adoption guide
- [Inline Editing Adoption Plan](INLINE-EDITING-ADOPTION-PLAN.md) — block-by-block adoption schedule for inline editing with fields, risks, and phased rollout
- [Responsive Overrides](RESPONSIVE-OVERRIDES.md) — responsive breakpoint override system: data model, inheritance, Hero pilot, published CSS scoping
- [Animations & Interactions](ANIMATIONS-INTERACTIONS.md) — CSS entrance animations: supported types, timing, easing, reduced motion, security
- [Advanced Shadow Builder Audit](ADVANCED-SHADOW-BUILDER-AUDIT.md) — shadow system inventory, custom shadow schema, ShadowField design, adoption plan

## Builder Master Plan

- **[Builder Master Plan v3](cms-builder-master-plan.md)** -- canonical roadmap for Divi-inspired builder rebuild. 4-level hierarchy (Section→Row→Column→Module), dual editor modes (Wireframe + Visual), compound presets, vertical slices with manual acceptance gates. 46 weeks, 8 tracks.
- [Hero Baseline Acceptance Checklist](acceptance/B-0-hero-baseline.md) -- 20-point manual regression baseline for current Hero block.

## Recovery & Quality

- [Project Recovery Plan](PROJECT-RECOVERY-PLAN.md) -- technical debt assessment, verified counts, priority phases, definition of done
- [Block Quality Contract](BLOCK-CONTRACT.md) -- comprehensive standard for block development, repair, and evaluation.
- [Hero Controls UX Audit](HERO-CONTROLS-UX-AUDIT.md) -- Hero block control inventory, P0-P3 implementation status.
- [Block Templates](templates/) -- starter templates for new block development.
- [Archived plans](_archive/) -- previous stabilization and builder plans (superseded by v3).
