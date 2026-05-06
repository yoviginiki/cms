# Site Settings — JSON shape reference

The `sites` table has two JSON columns that hold per-site configuration:

| Column | Type | Description |
|--------|------|-------------|
| `settings` | `jsonb` | All operational settings (front page, SEO code injection, AI keys, magazine viewer) |
| `seo_defaults` | `jsonb` | Default SEO meta values applied to pages/posts that have no page-specific override |

`SiteService::updateSite()` **merges** incoming `settings` with the existing stored object
(`array_merge($existing, $incoming)`), so you can update a single key without sending the
full object. `seo_defaults` follows the same merge behaviour.

---

## `settings` keys

### General

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `auto_publish` | `bool` | `true` | When `true`, saving any content triggers a publish job automatically |

### Front Page

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `homepage_type` | `string` | `"page"` | One of `"page"`, `"grid"`, `"blog"` |
| `homepage_id` | `string\|null` | `null` | UUID of the Page to render as the home page (when `homepage_type = "page"`) |
| `homepage_grid_id` | `string\|null` | `null` | UUID of the Grid to render as the home page (when `homepage_type = "grid"`) |
| `blog_page_id` | `string\|null` | `null` | UUID of the Page that acts as the blog index (used in all modes) |

`DynamicSiteController::findHomePage()` reads `homepage_id` first, then falls back to
the page with `slug = "home"`, then the page with the lowest `sort_order`.

### Custom Code

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `head_scripts` | `string\|null` | `null` | Raw HTML injected into `<head>` of every published page (max 65 536 chars) |
| `body_scripts` | `string\|null` | `null` | Raw HTML injected before `</body>` of every published page |
| `custom_css` | `string\|null` | `null` | CSS injected as a `<style>` block into every published page |

### AI

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `anthropic_api_key` | `string\|null` | `null` | Anthropic Claude API key — required for AI generate/rewrite/translate/SEO/alt-text and Magazine Wizard |
| `openai_api_key` | `string\|null` | `null` | OpenAI API key — declared but not currently consumed by any controller |

Keys are stored in plaintext in the DB. Do not log or expose them in API responses beyond the truncated display in the Settings UI.

### Magazine Viewer

Applied to every flipbook reader (`/magazine/{slug}`, `/issue/{slug}`).

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `mag_transition` | `string` | `"turn"` | Page-turn animation: `"turn"` \| `"fade"` \| `"slide"` |
| `mag_spread` | `string` | `"spread"` | Layout: `"spread"` (2 pages side by side) \| `"single"` (one page at a time) |
| `mag_bg` | `string` | `"#0a0a0a"` | Background color behind the pages (CSS color string) |
| `mag_speed` | `number` | `500` | Transition duration in milliseconds |
| `mag_page_numbers` | `bool` | `true` | Whether page numbers are shown |
| `mag_pn_position` | `string` | `"bottom"` | Page number position: `"top"` \| `"bottom"` |
| `mag_pn_align` | `string` | `"outer"` | Page number alignment: `"outer"` \| `"center"` \| `"inner"` |
| `mag_pn_size` | `string` | `"9"` | Page number font size in px (stored as string) |

---

## `seo_defaults` keys

Applied to pages/posts that have no page-specific SEO values set.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `title_template` | `string\|null` | `null` | Title tag template. Supports `%page_title%` and `%site_name%` placeholders. Example: `"%page_title% | %site_name%"` |
| `description` | `string\|null` | `null` | Default meta description (max 500 chars recommended) |
| `og_image` | `string\|null` | `null` | Absolute URL of the default Open Graph image |

---

## Example full settings object

```json
{
  "auto_publish": true,
  "homepage_type": "page",
  "homepage_id": "018f1a2b-3c4d-7e5f-a6b7-c8d9e0f1a2b3",
  "homepage_grid_id": null,
  "blog_page_id": null,
  "head_scripts": null,
  "body_scripts": null,
  "custom_css": null,
  "anthropic_api_key": "sk-ant-...",
  "openai_api_key": null,
  "mag_transition": "turn",
  "mag_spread": "spread",
  "mag_bg": "#0a0a0a",
  "mag_speed": 500,
  "mag_page_numbers": true,
  "mag_pn_position": "bottom",
  "mag_pn_align": "outer",
  "mag_pn_size": "9"
}
```

```json
// seo_defaults
{
  "title_template": "%page_title% | Acme Magazine",
  "description": "The best articles on design and culture.",
  "og_image": "https://acme.ensodo.eu/assets/og-default.jpg"
}
```

---

## Validation (`UpdateSiteRequest`)

| Field | Rules |
|-------|-------|
| `settings` | `sometimes\|array` |
| `seo_defaults` | `sometimes\|array` |
| `seo_defaults.title_template` | `sometimes\|nullable\|string\|max:255` |
| `seo_defaults.description` | `sometimes\|nullable\|string\|max:500` |
| `seo_defaults.og_image` | `sometimes\|nullable\|string\|max:2048` |

Individual `settings.*` sub-keys are not explicitly validated — the merge strategy means
unknown keys pass through silently. If you add a new settings key, consider adding an
explicit rule to `UpdateSiteRequest` to catch typos early.
