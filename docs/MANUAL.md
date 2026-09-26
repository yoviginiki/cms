# Manual: Build Your First Site

This is the start-here guide for someone who has never used the CMS. It walks the real process of making a website, in the order you would actually do it, and tells you what to expect at each step — including the places where the system currently behaves in surprising ways.

Every step below was walked on a real test site on 2026-09-25. Known problems found on the way are listed in the [logic audit](/docs/CMS-LOGIC-AUDIT-2026-09-25).

## How the system works (two minutes)

You build the site in the admin at **sys.ensodo.eu/admin**. Nothing you do there is visible to the public until it is **published**: publishing turns your pages into plain, fast HTML files and puts them live at **ensodo.eu/your-site** (or on your own domain).

| Word | Meaning |
|------|---------|
| **Site** | One website. You can have many; each has its own pages, menus, theme and settings. |
| **Page** | A standalone page (Home, About, Contact). Lives at `/slug/`. |
| **Post** | A dated article in a category (blog, news). Lives at `/category/slug/`. |
| **Block** | A piece of content: heading, text, image, button, form… Blocks sit in **sections → rows → columns**. |
| **Theme** | Colors, fonts and spacing for the whole site. |
| **Menu** | A list of links shown in the header (navigation) or footer. |
| **Grid** | The page skeleton: header area, navigation area, content, footer area. Every new site uses the **Full Width** grid. |
| **Status** | *Draft* (only you see it) or *Published* (included when the site is published). Published status does **not** mean it is live yet. |
| **Publish** | Build the site and put it live. **Auto-publish** does this for some edits by itself (see step 12). |

## Step 1 — Log in

Open **https://sys.ensodo.eu/admin**, enter email and password, **sign in**. You land on the **Dashboard**: one card per site.

To work on a site, click its card (or its **Pages** link). The left sidebar then shows that site's tools in groups: **Content** (pages, posts, categories, media), **Structure** (menus, grids, templates — header, footer, layout), **Design** (themes), **Tools** (wizards, import), **Publish & insights**, and **Settings**. **Account** (users, modules) is at the bottom.

## Step 2 — Create the site

On the Dashboard click the dashed card **Create new site**. A four-step dialog opens:

1. **Basics** — **Site Name** and **URL Slug**. The slug becomes the address: `ensodo.eu/{slug}`. Use lowercase Latin letters, digits and dashes (e.g. `bakery-sofia`). The name must contain at least one Latin letter or digit, otherwise creation fails — see audit A2.
2. **Theme** — pick a starting look. You can change it later under **Themes** (not in Site Settings, despite what the dialog says).
3. **Template** — the starter content: **Blank** (one Home page), **Blog**, **Portfolio**, **Business** (Home, About, Services, Team, Contact), or **Full Site**. Full Site can write draft copy for you if you describe the business.
4. **Confirm** → **Create Site** → **Site Created!** → **Open Pages**.

If you close the dialog instead, the new card may not appear until you reload the Dashboard.

## Step 3 — See what you got

**Pages** lists the template's pages. They already have status *Published*, and the one marked **Front Page** is the homepage.

**Nothing is live yet.** `ensodo.eu/{slug}` returns "not found" until the first publish. The **View Site** button on the Dashboard card opens an admin preview, not the public site.

## Step 4 — Publish for the first time

Click **Publish** in the top bar. The build takes a few seconds for a small site. Then open **https://ensodo.eu/{slug}/** in a new tab.

What you will see on a fresh site (created since 2026-09-26): a header with the site name and a menu of the template's pages, the page content, and a footer with the name, a footer menu, copyright and "Back to top". Header and footer are two block sections — **Site header** and **Site footer** (Structure → Global Sections) — shared by every page and post. Steps 5 and 6 explain how to change them.

## Step 5 — Add navigation (header menu)

A new site already has a **Main** menu (location header) and a **Footer** menu with its starter pages; edit them here. Sites created before 2026-09-26 need the steps below.

1. Sidebar → **Menus** → **Create**. Two small prompts appear: the menu **name** (e.g. `Main`) and the **location** — type exactly `header`.
2. Open the menu. Add items: pick pages (they track renames automatically), posts, categories, or type a custom URL. Drag to reorder; drag onto an item to make a submenu.
3. **Save**. With auto-publish on (the default), the site republishes itself within half a minute.

The location cannot be changed later from the admin, so type it correctly the first time. New pages are **not** added to the menu automatically — come back here whenever you add a page.

## Step 6 — Add a footer

**New sites:** the footer already exists — Structure → **Global Sections** → **Site footer** → Edit: add your social links (Social Links block), change texts, columns or colors, then **Publish**. The same goes for **Site header**. Older sites: use the steps below.

**Best way (blocks):** Structure → **Grids** → **Full Width** → click the **Footer** area → type **section** → **+ Нова секция** → build the footer with blocks in the editor that opens → **Publish** → save the grid. Use the same section for the other grids' footer areas. The steps below are the older menu-based way.

On a new site the footer comes from the grid, so it takes two places:

1. **Menus** → **Create** → name `Footer`, location `footer` → add links (Contact, Privacy…) → **Save**.
2. Sidebar → Structure → **Grids** → **Full Width** → click the **Footer** area → set type **Menu**, location **footer** → **Save**. The grid editor republishes the site.
3. Optional: **Settings → Branding** → **Footer Text** and **Copyright** → save → **Publish** (settings changes do not republish by themselves).

The footer then shows the site name, your links, the footer text and the copyright. For the full picture (Global Footer templates, rich footer, archives) see [Header & Footer](/docs/GUIDE-HEADER-FOOTER).

### Which grid does each page use?

**Pages** and **Posts** have a **Grid** column: the grid each item is published with and why ("site default", "set on this item", "from its category"), or "No grid" with the reason on hover. Click the name to open that grid. **Grids** shows, for every grid, what uses it, and at the top the **Site default grid** — change it there. To give one page (e.g. the homepage) its own grid: open the page → **Page** tab → **Grid**.

## Step 7 — Choose and adjust the look

Sidebar → **Themes**.

- **Use this theme / Activate** on a card switches the whole site to it.
- System themes are read-only. To change colors or fonts, **Fork** the theme (make your own copy), then **Edit** it: color pickers, fonts, spacing. **Studio** edits the same theme by clicking on a live preview.
- **Theme Wizard** (button on the Themes page) can create a theme from a website address, a screenshot or a description. It does not activate the result — activate it on the Themes page.

After any theme change click **Publish**. Theme changes never republish by themselves; the admin shows a "stale" banner as a reminder.

Settings → **Global Styles** can also set fonts and colors, and those **override** the theme. Use one place, preferably the theme.

## Step 8 — Edit a page

Pages → click a page (or **Create Page**, type a title → the editor opens with an empty draft).

The editor has the page in the middle and tabs on the right: **Page** (title, slug, status, grid, language), **Block** (settings of the selected block), **+ Add** (all blocks), **Tree** (structure), **SEO**, **History**.

1. On an empty page choose a ready-made section from the **Section Library**, or **Blank Section**. Later, use **+ Section** between sections, or the floating **+** for a quick heading, text, image or hero.
2. Click any block to edit its text in place; its settings appear in the **Block** tab.
3. **Save** often (toolbar or Ctrl+S). Autosave only kicks in after five idle minutes by default.
4. When the page is ready, set its status to **Published** (toolbar toggle or Page tab). With auto-publish on, that page goes live by itself in about half a minute.

Two things that confuse everyone:

- The editor's **Publish** button publishes the *site*; it does not change a draft page to Published. A draft stays offline until you switch its status.
- Switching an existing page from **Blocks** to **Canvas** mode can drop its nested content. Pick the editor mode on a new page, not on a finished one.

## Step 9 — Images and files

Sidebar → **Media** → upload (drag and drop). Add **alt text** — it is what screen readers and search engines read. Folders keep things tidy.

Inside the editor, image blocks open the same media library. Published images are automatically resized and served as WebP.

## Step 10 — Set the homepage

**Settings → Front Page** → type **Page** → choose the page → save. Changing the homepage marks the site for republish; click **Publish**.

## Step 11 — A contact form

Sidebar → **Form Wizard** → pick the page, the fields (name, email, message…), the email address that receives submissions and the thank-you text. The form is added at the bottom of that page; publish afterwards.

Submissions are stored and emailed; read or export them under **Settings → Forms**.

## Step 12 — Blog posts

- **Posts** → **Create** → the same editor as pages, plus a category, excerpt and featured image. Posts also have a **Simple** mode: one text body, like a word processor.
- **Categories** (Content group) creates the sections of the blog; each category gets its own listing page at `/{category-slug}/`.
- Tags currently cannot be attached to posts from the admin (audit C3).

## Step 13 — Settings worth filling in

| Tab | What to set |
|-----|-------------|
| General | Site name, **auto-publish** on/off, custom domain |
| Branding | Logo, tagline, social links, footer text, copyright |
| SEO | Title template (e.g. `%s — Bakery`), default description, share image, Google/Bing verification |
| Languages | Extra languages; see [Translations](/docs/GUIDE-TRANSLATIONS) |
| Custom Code | Google Analytics ID, extra head/body scripts, custom CSS (admins) |

Settings changes are not published automatically. Click **Publish** after saving.

**Custom domain**: the domain must first exist on the server (Hestia) and point to it; then enter it in General and publish. The site then lives at `https://yourdomain`, and the old `ensodo.eu/{slug}` address stops being updated.

## What publishes by itself, and what doesn't

| You change… | With auto-publish ON |
|-------------|----------------------|
| Page / post content or status | Goes live by itself (~30 s) |
| Menu items | Whole site rebuilds by itself |
| Grid (in the grid editor) | Whole site rebuilds by itself (even with auto-publish off) |
| Template blocks | Whole site rebuilds by itself |
| Theme (activate or edit) | **Publish manually** |
| Any Site Settings tab | **Publish manually** |
| Menu name, style or location | **Publish manually** |

With auto-publish OFF, nothing except grid edits publishes by itself, and edited pages are *not* listed as stale — click **Publish** when you are done.

**Stale pages** (appears in the sidebar only when something is stale) lists what needs rebuilding and offers **Rebuild entire site**. A previous version can be restored from the deployment history in the editor's Publish menu (**Rollback**).

## Where things are

| I want to… | Go to |
|------------|-------|
| Add or edit a page | Pages |
| Write an article | Posts |
| Change the navigation | Menus |
| Change the footer | Grids → Footer area (new sites) — see [Header & Footer](/docs/GUIDE-HEADER-FOOTER) |
| Change colors and fonts | Themes → Fork → Edit |
| Upload images | Media |
| Reuse a section on many pages | Library (copy) / Global Sections (linked) — see [Library & Globals](/docs/GUIDE-LIBRARY-GLOBALS) |
| Add a form | Form Wizard; submissions in Settings → Forms |
| Copy an existing website | Dashboard → **Import a website** |
| Import from WordPress | Tools → Import |
| Search these docs | The search box at the top of this sidebar (press `/`) |

## More guides

[Block editor](/docs/GUIDE-BLOCK-EDITOR) · [Canvas editor](/docs/GUIDE-CANVAS-EDITOR) · [Media library](/docs/GUIDE-MEDIA-LIBRARY) · [Menus](/docs/GUIDE-MENUS) · [Forms](/docs/GUIDE-FORMS) · [Publishing](/docs/GUIDE-PUBLISHING) · [Style presets](/docs/GUIDE-STYLE-PRESETS) · [Translations](/docs/GUIDE-TRANSLATIONS) · [Analytics](/docs/GUIDE-ANALYTICS)
