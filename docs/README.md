# Documentation Index

Ensodo CMS Platform documentation. Available online at `https://sys.ensodo.eu/docs` (requires login) or download as ZIP from the docs sidebar.

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
- [Magazine Wizard](magazine-wizard.md) -- AI-powered magazine creation workflow

## Block Properties Audit

- [Block Properties Audit](BLOCK-PROPERTIES-AUDIT.md) — comprehensive audit of shared block property system
- [Hero Properties Demo Data](fixtures/hero-properties-demo-data.json) — test fixtures for Hero block properties
- [Block Properties Manual Checklist](BLOCK-PROPERTIES-MANUAL-CHECKLIST.md) — manual QA checklist for property verification

## Recovery & Quality

- [Project Recovery Plan](PROJECT-RECOVERY-PLAN.md) -- technical debt assessment, verified counts, priority phases, definition of done
- [Block Quality Contract](BLOCK-CONTRACT.md) -- comprehensive standard for block development, repair, and evaluation. Covers data contracts, editor UX, inline editing, theme safety, accessibility, security, testing, and readiness levels.
- [Block Templates](templates/) -- starter templates for new block development (`definition.ts`, `Editor.tsx`, `Preview.tsx`, `index.ts`, Blade template, PHP BlockDefinition). All templates follow the Block Quality Contract.
