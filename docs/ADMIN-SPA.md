# Admin SPA (Frontend)

## Tech Stack

| Technology | Purpose |
|-----------|---------|
| React 18 | UI framework |
| TypeScript | Type safety |
| Vite | Build tool (with laravel-vite-plugin) |
| Zustand | State management (stores) |
| Tailwind CSS 4 | Styling |
| React Router | Client-side routing |

## Source Location

```
resources/admin/src/
```

## Directory Structure

```
src/
├── App.tsx                    ← Root component, router setup
├── components/
│   ├── blocks/                ← 60+ block editor/preview components
│   │   ├── {type}/
│   │   │   ├── Editor.tsx     ← Edit interface for the block
│   │   │   ├── Preview.tsx    ← In-canvas preview rendering
│   │   │   ├── definition.ts  ← Block type metadata + defaults
│   │   │   └── index.ts      ← Barrel export
│   │   ├── index.ts           ← Master block index
│   │   └── registry.ts       ← Frontend block registry
│   ├── editor/                ← Page/post editor components
│   │   ├── AiAssistant.tsx
│   │   ├── BackgroundEditor.tsx
│   │   ├── BlockPicker.tsx    ← Block type selection modal
│   │   ├── BlockSettings.tsx  ← Right panel: block properties
│   │   ├── BlockToolbar.tsx   ← Floating toolbar on selected block
│   │   ├── BuilderCanvas.tsx  ← Main editing canvas
│   │   ├── BuilderSidebar.tsx ← Left sidebar (layers, settings)
│   │   ├── DragOverlay.tsx    ← Drag-and-drop visual feedback
│   │   ├── LayersPanel.tsx    ← Block tree view
│   │   ├── MagazineEditorCanvas.tsx ← Magazine visual editor
│   │   ├── PresenceIndicator.tsx   ← Who else is editing
│   │   ├── PreviewPane.tsx    ← Live preview iframe
│   │   ├── PublishButton.tsx  ← Publish with status tracking
│   │   ├── PublishDiffModal.tsx ← Show changes before publish
│   │   ├── SortableBlock.tsx  ← Drag-sortable block wrapper
│   │   ├── WysiwygEditor.tsx  ← Rich text editor (TipTap/ProseMirror)
│   │   ├── fields/           ← Reusable form field components
│   │   └── properties/       ← Block property panels
│   ├── layout/
│   │   └── AdminLayout.tsx    ← Shell layout (nav, sidebar, content area)
│   ├── magazine/              ← Magazine-specific editor components
│   └── ui/                    ← Reusable UI primitives (buttons, modals, etc.)
├── hooks/                     ← Custom React hooks
│   ├── useAutoSave.ts        ← Auto-save content periodically
│   ├── useDeploymentStatus.ts ← Poll deployment progress
│   ├── useEditorPresence.ts  ← Real-time collaboration presence
│   ├── useEditorShortcuts.ts ← Keyboard shortcuts (Ctrl+S, etc.)
│   └── usePageData.ts        ← Load page/post data + blocks
├── lib/                       ← Utility libraries
│   ├── api.ts                ← Axios/fetch API client (base URL, auth, error handling)
│   ├── slugify.ts            ← URL slug generation
│   ├── smartGuides.ts        ← Smart alignment guides for drag
│   ├── textThreading.ts      ← Text threading for magazine layout
│   └── textWrap.ts           ← Text wrapping utilities
├── pages/                     ← Route-level page components
│   ├── Analytics.tsx         ← Analytics dashboard
│   ├── Assets.tsx            ← Media library
│   ├── Categories.tsx        ← Category management
│   ├── ContentGraph.tsx      ← Dependency graph visualization
│   ├── Dashboard.tsx         ← Main dashboard
│   ├── DebugConsole.tsx      ← Admin debug tools
│   ├── GridAssignments.tsx   ← Grid assignment rules
│   ├── GridEditor.tsx        ← Visual grid editor
│   ├── Grids.tsx             ← Grid list/management
│   ├── ImportPage.tsx        ← WordPress import wizard
│   ├── Login.tsx             ← Login form
│   ├── MagazineEditorV2.tsx  ← Magazine visual editor (full page)
│   ├── MagazineList.tsx      ← Magazine listing
│   ├── MenuEditor.tsx        ← Menu item tree editor
│   ├── Menus.tsx             ← Menu management
│   ├── PageEditor.tsx        ← Page block editor
│   ├── PagesList.tsx         ← Pages listing
│   ├── PostEditor.tsx        ← Post block editor
│   ├── PostsList.tsx         ← Posts listing
│   ├── SiteSettings.tsx      ← Site configuration
│   ├── Tags.tsx              ← Tag management
│   ├── ThemeEditor.tsx       ← Token editor
│   ├── ThemeEngine.tsx       ← Theme listing + management
│   ├── ThemeStudio.tsx       ← Live theme preview frames
│   ├── Users.tsx             ← User management
│   └── wizard/               ← Magazine wizard multi-step flow
├── stores/                    ← Zustand state stores
│   ├── editorStore.ts        ← Block editor state (selected block, dirty state, undo)
│   └── magazineStore.ts      ← Magazine editor state (pages, elements, zoom)
└── types/                     ← TypeScript type definitions
```

## State Management

### editorStore (Zustand)

Manages the page/post block editor state:
- Current block tree
- Selected block ID
- Dirty/unsaved state
- Undo/redo history
- Clipboard (copy/paste blocks)
- Drag state

### magazineStore (Zustand)

Manages the magazine visual editor:
- Magazine pages array
- Current page index
- Selected element
- Zoom level
- Canvas dimensions

## Key Pages

### PageEditor / PostEditor

Full-screen block editor with:
- Left sidebar: layers tree, page settings
- Center: `BuilderCanvas` with sortable blocks
- Right sidebar: `BlockSettings` panel for selected block
- Top bar: publish button, preview, presence indicator

### GridEditor

Visual CSS Grid editor:
- Interactive grid cell drawing
- Position naming and configuration
- Responsive breakpoint editing
- Live CSS preview

### ThemeEngine / ThemeStudio

- ThemeEngine: list themes, fork, assign, compare
- ThemeStudio: iframe-based preview of theme tokens applied to sample content frames

### MagazineEditorV2

Canvas-based magazine layout editor:
- Fixed-size pages (configurable dimensions)
- Drag/resize elements on page
- Text threading between elements
- Style presets

## API Client

**File:** `src/lib/api.ts`

Centralized HTTP client:
- Base URL: `/api/v1`
- Automatic CSRF cookie refresh
- Auth error handling (redirect to login on 401)
- Request/response interceptors

## Custom Hooks

| Hook | Purpose |
|------|---------|
| `useAutoSave` | Debounced auto-save of block tree (configurable interval) |
| `useDeploymentStatus` | Polls deployment status, shows progress |
| `useEditorPresence` | Heartbeat + display other editors on same content |
| `useEditorShortcuts` | Registers keyboard shortcuts (save, undo, redo, delete) |
| `usePageData` | Fetches page/post + blocks, handles loading state |

## Block Editor Components

Each block type has:

| File | Role |
|------|------|
| `Editor.tsx` | The editing interface (inputs, toggles, rich text) |
| `Preview.tsx` | Visual preview in the canvas (matches published look) |
| `definition.ts` | Type name, category, icon, default data, validation |
| `index.ts` | Exports Editor + Preview + definition |

The `registry.ts` collects all block definitions for the BlockPicker UI.

## Build & Development

```bash
# Development (with HMR)
npm run dev

# Production build
npm run build
```

Vite config uses `laravel-vite-plugin` for:
- Hot Module Replacement in development
- Asset versioning in production
- Proper public path resolution

Output: `public/build/` (compiled JS/CSS bundles)

## Routing

The SPA is served from a single Laravel route (`/admin`). React Router handles client-side navigation. All `/api/v1/*` routes are API-only.
