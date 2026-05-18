# Ensodo CMS — Page Generation Guide

Use this document as a reference to generate valid page content for the Ensodo CMS builder.
Feed this file to Claude/AI along with your request to create a page.

## Architecture

Pages use a **4-level hierarchy**:

```
Section (top-level container)
  └── Row (layout — defines column arrangement)
       └── Column (content container)
            └── Module (actual content: heading, text, image, etc.)
```

**Rules:**
- A page is an array of Sections
- A Section contains Rows
- A Row contains Columns (number must match the layout preset)
- A Column contains Modules (leaf nodes — actual content)
- Every block has: `id` (UUID), `type`, `level`, `data`, `order`, `children[]`

## Row Layouts

| Layout | Columns | Grid |
|--------|---------|------|
| `1` | 1 column (fullwidth) | `1fr` |
| `1/2+1/2` | 2 equal | `1fr 1fr` |
| `1/3+2/3` | 2 (narrow + wide) | `1fr 2fr` |
| `2/3+1/3` | 2 (wide + narrow) | `2fr 1fr` |
| `1/3+1/3+1/3` | 3 equal | `1fr 1fr 1fr` |
| `1/4+1/4+1/4+1/4` | 4 equal | `1fr 1fr 1fr 1fr` |
| `1/4+3/4` | 2 (quarter + three-quarter) | `1fr 3fr` |
| `3/4+1/4` | 2 (three-quarter + quarter) | `3fr 1fr` |

## Complete Page Example

```json
[
  {
    "id": "section-1",
    "type": "section",
    "level": "section",
    "order": 0,
    "data": {
      "background_color": "",
      "background_image": "",
      "padding_top": "60px",
      "padding_bottom": "60px",
      "max_width": "1200px",
      "anchor_id": ""
    },
    "children": [
      {
        "id": "row-1",
        "type": "row",
        "level": "row",
        "order": 0,
        "data": {
          "layout": "1/2+1/2",
          "gap": "32px",
          "vertical_align": "center"
        },
        "children": [
          {
            "id": "col-1",
            "type": "column",
            "level": "column",
            "order": 0,
            "data": { "padding": "", "vertical_align": "start", "background_color": "" },
            "children": [
              {
                "id": "heading-1",
                "type": "heading",
                "level": "module",
                "order": 0,
                "data": { "text": "Welcome to Ensodo", "level": "h1", "color": "", "fontSize": "" },
                "children": []
              },
              {
                "id": "para-1",
                "type": "paragraph",
                "level": "module",
                "order": 1,
                "data": { "content": "<p>Build beautiful websites with our visual page builder.</p>" },
                "children": []
              },
              {
                "id": "btn-1",
                "type": "button",
                "level": "module",
                "order": 2,
                "data": { "text": "Get Started", "url": "/signup", "style": "primary", "size": "lg", "target": "_self" },
                "children": []
              }
            ]
          },
          {
            "id": "col-2",
            "type": "column",
            "level": "column",
            "order": 1,
            "data": { "padding": "", "vertical_align": "center", "background_color": "" },
            "children": [
              {
                "id": "img-1",
                "type": "image",
                "level": "module",
                "order": 0,
                "data": { "src": "/images/hero.jpg", "alt": "Hero image", "width": "", "height": "", "caption": "" },
                "children": []
              }
            ]
          }
        ]
      }
    ]
  }
]
```

## Available Modules (70 blocks)

### Layout (hierarchy blocks — used automatically)

| Type | Data Fields |
|------|-------------|
| `section` | `background_color`, `background_image`, `padding_top`, `padding_bottom`, `max_width`, `anchor_id` |
| `row` | `layout` (see table above), `gap`, `max_width`, `vertical_align` (start/center/end/stretch) |
| `column` | `padding`, `vertical_align` (start/center/end), `background_color` |

### Typography

| Type | Data Fields |
|------|-------------|
| `heading` | `text`, `level` (h1-h6), `color`, `fontSize` |
| `paragraph` | `content` (HTML string) |
| `text` | `content` (plain text) |
| `rich-text` | `content` (full HTML) |
| `runningtext` | `content`, `columns` (1-4), `columnGap`, `columnRule` (bool) |
| `dropcap` | `content` (HTML), `capSize` (2-5), `capColor` |
| `pullquote` | `text`, `attribution`, `style` (large-text/bordered/background) |
| `code` | `code` (string), `language`, `show_line_numbers` (bool) |
| `caption` | `text`, `prefix` |
| `footnote` | `content`, `marker` |
| `sidenote` | `content`, `side` (left/right) |

### Media

| Type | Data Fields |
|------|-------------|
| `image` | `src`, `alt`, `width`, `height`, `caption`, `link` |
| `imagecaption` | `src`, `alt`, `caption`, `captionPosition` (below/overlay) |
| `gallery` | `images` (array of {src, alt, caption}), `layout` (grid/masonry/carousel), `columns` (2-6), `gap` |
| `video` | `url`, `autoplay` (bool), `muted` (bool), `poster` |
| `audio` | `url`, `title`, `artist` |
| `fullbleed` | `src`, `alt`, `overlayText`, `overlayPosition`, `scrimOpacity` |
| `beforeafter` | `beforeSrc`, `afterSrc`, `beforeLabel`, `afterLabel`, `initialPosition` (0-100) |

### Content

| Type | Data Fields |
|------|-------------|
| `button` | `text`, `url`, `style` (primary/secondary/outline/ghost), `size` (sm/md/lg), `target` (_self/_blank) |
| `divider` | `style` (solid/dashed/dotted), `color`, `thickness`, `width`, `alignment` |
| `spacer` | `height` (xs/sm/md/lg/xl) |
| `textdivider` | `style` (line/dots/symbol), `customSymbol`, `width` (full/half/third) |
| `icon` | `name` (Lucide icon name), `size`, `color` |
| `list` | `items` (array of strings), `style` (bullet/number/check), `icon` |
| `table` | `headers` (string[]), `rows` (string[][]), `striped` (bool), `compact` (bool) |
| `accordion` | `items` (array of {title, content}), `multiOpen` (bool), `iconStyle` |
| `tabs` | `tab_labels` (string[]), `style`, `alignment` — uses children for content |
| `tooltip` | `triggerText`, `tooltipText`, `position` (top/bottom/left/right) |
| `toc` | `maxDepth` (1-6), `style` (inline/sidebar), `sticky` (bool) |

### Navigation

| Type | Data Fields |
|------|-------------|
| `breadcrumbs` | `separator`, `showHome` (bool), `homeLabel`, `showCurrent` (bool) |
| `anchormenu` | `items` (array of {label, anchor}), `style`, `sticky` (bool) |
| `menu` | `menuId`, `style`, `orientation` |
| `readingprogress` | `style` (top-bar/circle), `color`, `height` |

### Blog & Editorial

| Type | Data Fields |
|------|-------------|
| `postgrid` | `categoryId`, `limit`, `columns`, `cardStyle`, `showExcerpt` (bool) |
| `postcard` | `postId`, `style` (vertical/horizontal), `showExcerpt`, `showDate`, `showCategory` |
| `latestposts` | `count`, `categoryId`, `showExcerpt`, `showImage` |
| `relatedposts` | `limit`, `basedOn` (category/tags) |
| `categorylist` | `style` (links/cards), `showCount` (bool), `parentOnly` (bool) |
| `authorbox` | `showAvatar` (bool), `showBio` (bool), `showSocialLinks` (bool), `layout` |
| `sharebuttons` | `platforms` (string[]), `style` (icons/buttons), `showLabels` (bool) |
| `timeline` | `items` (array of {date, title, description}), `layout` (left/center/alternate) |

### Data & Charts

| Type | Data Fields |
|------|-------------|
| `chart` | `chartType` (bar/line/pie/doughnut), `data` (array of {label, value}), `title` |
| `stats` | `items` (array of {value, label, prefix, suffix}), `columns` |
| `featuregrid` | `items` (array of {icon, title, description}), `columns`, `style` |
| `featurecomparison` | `plans` (array), `features` (array) |
| `logostrip` | `images` (array of {src, alt, url}), `speed`, `pauseOnHover` |

### Commerce

| Type | Data Fields |
|------|-------------|
| `pricingcard` | `planName`, `price`, `period`, `features` (array of {text, included}), `ctaText`, `ctaUrl`, `highlighted`, `badge` |
| `pricingtable` | `plans` (array of plan objects), `columns` |
| `testimonial` | `items` (array of {quote, author, role, avatar}), `layout` (single/grid/carousel) |
| `ctabanner` | `heading`, `text`, `buttonText`, `buttonUrl`, `backgroundStyle` |

### Forms

| Type | Data Fields |
|------|-------------|
| `contact-form` | `fields` (array of {label, type, required}), `submitText`, `recipientEmail` |
| `customform` | `fields` (array of {type, label, required, placeholder, options?}), `submitText` |
| `newsletter` | `placeholder`, `buttonText`, `provider`, `listId` |

### Embeds

| Type | Data Fields |
|------|-------------|
| `html-embed` | `code` (raw HTML string), `sandbox` (bool) |
| `socialembed` | `url`, `platform` (auto/twitter/youtube/instagram) |
| `map` | `address`, `zoom`, `height`, `style` |

### Advanced

| Type | Data Fields |
|------|-------------|
| `modal` | `triggerText`, `title`, `size` — uses children for content |
| `stickysidebar` | `sidebarSide`, `sidebarWidth`, `gap`, `stickyOffset` — uses children |
| `flipbook` | `mode`, `aspect_ratio`, `pdf_asset_id` |
| `scroll_page` | Complex — full scroll-based storytelling page |

## Style Properties (optional on any block)

```json
{
  "style": {
    "background": { "color": "#f5f5f5", "image": "", "size": "cover" },
    "spacing": { "marginTop": "20px", "marginBottom": "20px", "paddingTop": "16px", "paddingBottom": "16px" },
    "border": { "radius": "8px", "width": "1px", "color": "#ddd", "style": "solid" },
    "shadow": { "x": "0", "y": "4px", "blur": "12px", "color": "rgba(0,0,0,0.1)" }
  }
}
```

## Animation Properties (optional)

```json
{
  "animation": {
    "type": "fade-in-up",
    "duration": "0.6s",
    "delay": "0s",
    "easing": "ease-out"
  }
}
```

Types: `fade-in`, `fade-in-up`, `fade-in-down`, `fade-in-left`, `fade-in-right`, `zoom-in`, `slide-up`

## How to Use This Guide

### Option 1: Generate JSON for the API
Generate a valid blocks array and POST it to:
```
PUT /api/v1/sites/{siteId}/pages/{pageId}/blocks
Body: { "blocks": [...array from above...] }
```

### Option 2: Generate for the Raw HTML editor
If you just need custom HTML/scripts, put it in the `raw_html` field:
```
PUT /api/v1/sites/{siteId}/pages/{pageId}/blocks
Body: { "blocks": [], "raw_html": "<div>...</div>" }
```

## Prompt Template

Use this prompt to ask Claude to generate a page:

```
Using the Ensodo CMS block schema from docs/page-generation-guide.md,
generate a page with:
- [describe what you want]
- [layout preferences]
- [content/copy]

Output as a valid JSON blocks array following the Section > Row > Column > Module hierarchy.
Use realistic UUIDs for IDs. Match the number of columns to the row layout preset.
```
