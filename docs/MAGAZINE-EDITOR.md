# Magazine editor — user guide

The Magazine Editor is an InDesign-style page layout tool built into the CMS. It lets you create magazine pages, brochures, and visual layouts by placing and arranging elements freely on a canvas.

## Getting started

1. Go to **Magazines** in the admin sidebar
2. Click **New magazine** and give it a title
3. The editor opens with a blank page (A4 size by default)
4. Use the toolbar at the top to select tools and add elements
5. Use the **+ Add** tab in the right panel to browse all available elements
6. Click **Save** when done, then **Publish** to make it live

## The toolbar (top bar)

The toolbar contains your drawing tools. Each tool has an icon, a label, and a keyboard shortcut shown in grey.

### ↖ Select (V)

The default tool. Use it to:
- **Click** an element to select it (blue border appears with resize handles)
- **Drag** a selected element to move it on the page
- **Shift+click** to select multiple elements
- **Arrow keys** to nudge selected elements by 1 point (hold Shift for 10 points)
- **Delete** key to remove selected elements
- **Ctrl+D** to duplicate the selection

When you select an element, its properties appear in the right panel.

### T Text (T)

Creates a text frame on the page:
1. Press **T** or click the Text button in the toolbar
2. Click and drag on the canvas to draw the frame size
3. The frame appears and you can start typing
4. Switch back to Select tool (**V**) to move or resize it

Text frames support rich text — bold, italic, headings. Set font, size, color, alignment in the **Properties** panel (Typography section). A text frame can have **multiple columns** inside it.

### 🖼 Image (I)

Creates an image frame:
1. Press **I** or click the Image button
2. Click and drag on the canvas to draw the frame
3. In the **Properties** panel, paste an image URL
4. The image appears inside the frame

Image controls: **Fit mode** (fill/fit/stretch), **Focal point** (which part stays visible when cropped), **Scale** (zoom inside frame), **Filters** (brightness, contrast, saturation, grayscale).

### □ Rect (R)

Draws a rectangle shape:
1. Press **R** or click the Rect button
2. Click and drag on the canvas
3. Set fill color, stroke, corner radius in **Properties > Fill & Stroke**

Use rectangles as backgrounds, dividers, decorative elements, or containers.

### ○ Circle (E)

Draws a circle or ellipse:
1. Press **E** or click the Circle button
2. Click and drag (hold Shift for a perfect circle)
3. Customize in **Properties > Fill & Stroke**

### ╱ Line (L)

Draws a straight line:
1. Press **L** or click the Line button
2. Click and drag from start point to end point
3. Set stroke color and width in Properties

## Adding elements from the palette

Besides the 6 toolbar tools, there are **36 magazine elements** and **63 CMS blocks** available. To access them:

1. Click the **"+ Add"** tab in the right panel
2. Browse elements by category or use the search bar
3. Click any element to add it to the canvas

### Available element categories

**Text frames (6):** Text frame, Headline, Pull quote, Caption, Footnote, Marginalia

**Image frames (6):** Image frame, Circular image, Polygon image, Full-bleed image, Gallery, Background image

**Shapes (6):** Rectangle, Ellipse, Line, Polygon, Decorative rule, Gradient overlay

**Media (4):** Video, Audio player, Embed, SVG icon

**Interactive (5):** Button, Hotspot, Tooltip, Accordion, Slide-in panel

**Data (4):** Table, Chart, Stat number, Progress bar

**Page structure (3):** Page number, Running header, Column guides

**Grouping (2):** Group, Clipping group

Switch to the **"Blocks"** sub-tab to access all 63 CMS blocks (typography, layout, media, blog, interactive, data, commerce, forms, embeds).

## Page controls

### Zoom

- **Fit button** (expand icon) — fits the full page in your view
- **- button** — zoom out
- **% display** — shows current zoom level
- **+ button** — zoom in
- **Ctrl + scroll wheel** — zoom in/out with mouse

### Page navigation

- **Left/right arrows** — previous/next page
- **"Page X of Y"** — current page indicator
- **Page Navigator** (left panel) — thumbnails of all pages, click to jump
- **+ button** (bottom of left panel) — add a new page

## View toggles

Three buttons that show/hide alignment helpers on the canvas:

### Grid (Ctrl+;)

Shows a dot grid on the page. Elements snap to this grid when you drag them. Great for precise alignment.

### Guides

Shows page margins as pink dashed lines. Set margin values in **Properties > Page** when no element is selected.

### Baseline

Shows horizontal blue lines for text baseline alignment. Used in professional typography to align text across columns. Set spacing in **Properties > Page > Baseline Grid**.

## Undo and redo

- **Undo** button or **Ctrl+Z** — reverses your last action
- **Redo** button or **Ctrl+Shift+Z** — re-applies an undone action
- The editor remembers up to 50 steps

## The right panel

The right panel has four tabs:

### + Add tab

Browse and add elements to the canvas. Two sub-tabs:
- **Elements** — 36 magazine-specific elements organized by category, each with a description
- **Blocks** — 63 CMS blocks from the block editor (typography, layout, media, interactive, data, blog, commerce, forms, embeds)

Click any item to add it to the current page.

### Properties tab

Shows controls for the currently selected element:

**When nothing is selected** — Page settings:
- Page size (A4, A3, Letter, or custom width/height)
- Margins (top, right, bottom, left in points)
- Bleed (extra area beyond the page edge, for printing)
- Column grid (columns + gutter width)
- Baseline grid (increment + start position)
- Background color

**When an element is selected** — Element properties:

- **Transform** — X, Y position, Width, Height, Rotation (in points, 1pt = 1/72 inch)
- **Typography** (text frames) — Font family, size, weight, style, line height, letter spacing, alignment, color, paragraph spacing. Advanced: hyphenation, drop caps, OpenType features
- **Text Frame** (text frames) — Overflow behavior, auto-size, columns, column gap, text inset, vertical alignment
- **Image** (image frames) — Image URL, alt text, fit mode, focal point, scale, rotation, filters
- **Fill & Stroke** — Fill color + opacity + gradient, stroke color/width/style, corner radius per corner
- **Effects** — Opacity, drop shadow, inner shadow, blend mode, blur
- **Text Wrap** — How text in other frames flows around this element (none, bounding box, object shape, jump)

### Layers tab

All elements on the current page ordered front-to-back:
- Click a row to select that element on canvas
- **Eye icon** — hide/show element
- **Lock icon** — prevent accidental moves or edits
- **Arrow buttons** — reorder layers (move forward/backward)

### Styles tab

Paragraph and character styles for consistent typography:
- Click a style to apply it to the selected text frame
- **+** to create a new style
- Styles ensure consistent fonts, sizes, and spacing

## Keyboard shortcuts

| Shortcut | Action |
|----------|--------|
| **V** | Selection tool |
| **T** | Text frame tool |
| **I** | Image frame tool |
| **R** | Rectangle tool |
| **E** | Ellipse tool |
| **L** | Line tool |
| **Ctrl+Z** | Undo |
| **Ctrl+Shift+Z** | Redo |
| **Ctrl+A** | Select all elements on page |
| **Ctrl+D** | Duplicate selected elements |
| **Delete** | Delete selected elements |
| **Arrow keys** | Nudge 1pt |
| **Shift+Arrow** | Nudge 10pt |
| **Ctrl+;** | Toggle grid |
| **Ctrl+scroll** | Zoom in/out |

## Publishing

1. Click **Save** to save your work
2. Go back to the magazine list
3. Change status to **Published**
4. Your magazine is live at `ensodo.eu/magazine/your-slug`

The published magazine shows a flipbook-style reader with navigation controls, table of contents, thumbnails, and fullscreen mode.

## Tips

- **Start with page setup** — click empty canvas to see Page properties. Set size and margins first.
- **Use + Add** — the toolbar has 6 basic tools, but the + Add tab has all 99 elements.
- **Use the grid** — turn on Grid (Ctrl+;) for neat alignment.
- **Layer order matters** — elements higher in Layers panel appear in front.
- **Save often** — the yellow dot on the Save button means you have unsaved changes.
- **Hover for help** — every toolbar button has a tooltip explaining what it does.
