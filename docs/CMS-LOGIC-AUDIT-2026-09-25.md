# CMS Logic Audit 2026-09-25

A first-time-user walk through the whole site-building process, done while writing the [Manual](/docs/MANUAL). A throwaway tenant and site were created on production, driven through the same API calls the admin makes, and deleted afterwards. The rest of the admin was mapped from the code.

**Verification legend** — **LIVE**: reproduced on the test site. **CODE**: read in the code with file:line evidence, not reproduced. **FIXED**: fixed in this pass, with a test.

## Summary

The core loop works: create site → publish → live in about 5 seconds; page edits go live by themselves in about 30–45 seconds. What breaks the first-time experience is not the engine but the seams between screens:

- A new site publishes **without navigation or footer**, and the admin's own footer/header tools (Global Footer template, footer menu) are ignored on it because every new site renders through a grid.
- **Site Settings silently dropped most of what it saves** (Branding, Languages, Analytics…) since 2026-05-06. Fixed.
- **The first block added to a new page failed to save** (422). Fixed.
- Many edits do not republish and do not flag anything, so "I changed it but the site didn't change" is a normal outcome.

## A. Fixed in this pass

| # | Problem | Status |
|---|---------|--------|
| A1 | **Site Settings saved nothing outside ~50 whitelisted keys.** `UpdateSiteRequest` has `settings => array` plus per-key rules; Laravel's `excludeUnvalidatedArrayKeys` then drops every unruled key, returning 200. Lost: `footer_text`, `footer_copyright`, `logo_url`, `logo_show_name`, `tagline`, `social_links`, `languages`, `default_language`, `google_analytics_id`, `allowed_extensions`, `rich_footer`, … Since commit 9b93ee68 (2026-05-06). Fix: `validated()` passes unruled keys through (ruled keys still validated); server-owned `stale` and `custom_fonts` are ignored from the client. | LIVE, FIXED — `SiteTest::test_settings_tabs_keys_without_a_rule_are_saved` |
| A2 | **First block on a new page → 422.** The editor wraps a first block in a row with `layout: '1/1'` (`editorStore.ts:331, 552`); `RowBlockDefinition` allowed only `1`. The renderer already supported `1/1`. Fix: allow `1/1`. | LIVE, FIXED — `BlockTreeValidationTest::test_editor_single_column_row_layout_is_accepted` |
| A3 | **Admin "Page Guide" link 500s** (`/docs/page-generation-guide.md` → `findFile` null → reads the docs directory). Fix: `.md` links 301 to the slug, unknown docs 404. | FIXED — `DocsSearchTest` |
| A4 | Docs had no search; `# comments` inside code fences rendered as headings. | FIXED |
| A5 | `GUIDE-LIBRARY-GLOBALS` said header/footer are set in Site Settings with per-page override — neither exists. | FIXED (docs) |
| A6 | **Category "Grid" was saved and shown but never used.** The Categories screen offers a grid per category; `GridResolver` ignored `categories.grid_id`. Now: post override → category grid → assignments. Only monikcreations/portfolio had one set (affects 1 post that goes through a grid). | FIXED — `EffectiveGridTest` |
| A7 | **No way to see which grid a page/post publishes with.** New `EffectiveGridResolver` (shared with the publisher's grid-skip rule) → Grid column in Pages and Posts, "Used by" + **Site default grid** selector on Grids, skip reasons in the page editor. | FIXED — `EffectiveGridTest` |
| A8 | **Posts and pages had different footers** on the same site: posts without a template skipped the grid (workaround from commit 21d1948b for the grid canvas ignoring the post's "none" template). New sites (`settings.post_grid = unified`) render posts in the grid and honour the post's template choice; existing sites unchanged (render parity checked on artday, ensodo, monikcreations). | FIXED for new sites |
| A9 | Admin sidebar: one flat list of 18 items + ADVANCED; active item never highlighted. Now grouped Content / Structure / Design / Tools / Publish & insights / Settings / Account; highlight fixed. | FIXED |
| A10 | **"Promote to live" 500s on every custom-domain site.** The manual promote copied files from the web request; the web PHP pool's `open_basedir` excludes `/home/cytechno/web/{domain}/public_html` ("open_basedir restriction in effect"). Full publishes and auto-promotes worked because they run on the CLI worker. Now `PromoteStagedBatchJob` on the builds worker; the Stale screen polls and shows `promote_error`. | LIVE (monikcreations.eu), FIXED — `StalePromoteAndRideAlongTest` |
| A11 | **With auto-publish on, dependents never republished.** Editing a post flags pages that list it, but the delta publish built only the edited item and `StaleAutoRepublisher` stands down while auto-publish is on — so those pages (and "content save pending" flags) sat in Stale pages forever. The delta now takes every flagged item along (up to 100). | LIVE (monikcreations), FIXED — `StalePromoteAndRideAlongTest` |

**Consequence of A1 for existing sites:** values that users entered in those tabs since May were never stored. Anyone who "set the logo / footer text / GA id and it didn't show" needs to enter it again.

## B. First-time experience — high impact

| # | Problem | Evidence | Status |
|---|---------|----------|--------|
| B1 | **New sites have no navigation and no footer.** Starter templates create pages but no menu (`StarterTemplateService.php:103-185`); the default Full Width grid's header and footer areas are empty `fixed` areas (`GridPresetSeeder.php:84-87`). The Site Wizard (import) does build a menu — the dashboard dialog doesn't. | Business template published: empty `pos-header`, `pos-nav`, `pos-footer` | LIVE |
| B2 | **Global Footer/Header templates and the footer menu are ignored on every new site.** New sites get the Full Width grid as default assignment; the grid path never calls `renderGlobalTemplate`, and footer menus only render if a grid area is type Menu/footer. There is no UI to opt a site out of the grid. | Default footer template + footer menu created and published → footer still empty; switching the grid footer area to Menu/footer made it appear | LIVE |
| B3 | **Cyrillic-only site name cannot be created.** The dialog slugifies with `[^a-z0-9]` → empty slug → 422 "slug must be a string"; the server-side transliterating fallback is never reached. | `POST /sites {"name":"Моят сайт","slug":""}` → 422 | LIVE |
| B4 | **Page scheduling does nothing.** The page editor sends `scheduled_at`; `UpdatePageRequest` has no rule, so it is dropped. (Posts work.) | `PUT page {scheduled_at}` → 200, value null | LIVE |
| B5 | **Settings changes never republish or flag stale** (except homepage). The General tab's help text claims auto-publish covers "settings". | Settings PUT → no deployment, stale count 0 | LIVE |
| B6 | **A new site is flagged stale before it was ever published** ("Theme changed — published pages use the previous design"), because the dialog assigns the chosen theme right after creation. | `settings.stale` present right after creation | LIVE |
| B7 | New pages are not added to the header menu, and the menu location can only be typed in a `prompt()` at creation, never changed in the UI. | `Menus.tsx:68`; `MenuEditor.tsx` | LIVE / CODE |
| B8 | Starter pages have status *Published* while nothing is live; "published" means "included in the next build". The Site Wizard's "is live" toast has the same issue. | `SiteWizardPage.tsx:90` | LIVE |
| B9 | "View Site" on the Dashboard opens the login-only preview, not `ensodo.eu/{slug}`. | `Dashboard.tsx:97` | CODE |
| B10 | Dialog says "Theme can be changed later in Site Settings" — it is under Themes. | `Dashboard.tsx:423` | CODE |
| B11 | New users are offered the tenant-specific **Cytechno** theme; theme order is unsorted, so the pre-selected default is arbitrary. | `/available-themes` from a fresh tenant listed Cytechno | LIVE |

## C. Things that silently do nothing

| # | Problem | Evidence | Status |
|---|---------|----------|--------|
| C1 | Template "default" can only be set at creation (checkbox unticked by default); a non-default header/footer template is never used and cannot be made default in the UI. | `Templates.tsx:62,289`; `TemplateEditor.tsx:88-91,229-232`; `ThemeTemplate.php:140-146` | CODE |
| C2 | Global Sections cannot be edited — only publish/unpublish/delete, although the page says "Edit once, updates everywhere". API exists, no UI. | `GlobalSectionsList.tsx:70`; `api.ts:575-581` | CODE |
| C3 | Tags can never be attached to posts: no picker, and `tag_ids` is not in the post request rules. | `PostService.php:28-47`; `CreatePostRequest`, `UpdatePostRequest` | CODE |
| C4 | "Blog page URL" (`blog_page_id`), per-site AI keys, and the 404 / Search Results template types are stored but never used by the publisher. | grep; `SiteSecrets.php:21`; `PublishSiteJob.php:741-756` | CODE |
| C5 | Theme activation/edits never republish in either auto-publish mode (`StaleAutoRepublisher` returns early when auto-publish is on; nothing else fires). | `StaleAutoRepublisher.php:39-41` | CODE |
| C6 | With auto-publish OFF, page/post/template edits are not flagged stale either, so the Stale screen shows nothing. | `PageController.php:142-148`; `BlockController.php:90-92,148` | CODE |
| C7 | Menu name/style/location `PUT` flags stale but does not auto-publish (items save does). | `MenuController.php:91` vs `:149` | CODE |
| C8 | Editor **Publish** runs a site publish but leaves a draft page draft — two separate steps that look like one. | `PublishButton.tsx:51-63`; `PublishSiteJob.php:182` | LIVE (draft stayed 404 after site publish) |
| C9 | Site `status` paused/archived is not checked by publishing. | grep | CODE |
| C10 | Grid "fixed" area hint suggests blade partials `header`, `footer` that don't exist. | `GridEditor.tsx:1282`; `resources/views/positions/` | CODE |

## D. Data-loss and correctness risks

| # | Problem | Evidence | Status |
|---|---------|----------|--------|
| D1 | Switching a Blocks page to Canvas drops nested rows/columns/modules on first canvas save. | `canvasAdapter.ts:25-55,111-116` | CODE |
| D2 | Page lists stop at 15: Pages list, Front Page select, menu editor page picker and heading link picker send no `per_page`. Page 16+ is invisible; a homepage beyond 15 shows as not found. | `PageController.php:47`; `PagesList.tsx:32`; `SiteSettings.tsx:141`; `MenuEditor.tsx:426` | CODE |
| D3 | Library items that are rows/columns insert at page root → 422 on save. | `editorStore.ts:422-438` | CODE |
| D4 | Re-uploading an existing file from the "All" media view silently moves it to root (dedup + `folder=''`). | `Assets.tsx:87`; `AssetController.php:87-90` | CODE |
| D5 | Settings tabs send back the whole cached settings object — last writer wins across tabs/users. (Server-owned `stale`/`custom_fonts` are now protected by A1.) | `SiteSettings.tsx:231…` | CODE |
| D6 | Changing to a custom domain leaves the old `ensodo.eu/{slug}` symlink serving stale content. | `DeployService.php:135-151` | CODE |
| D7 | Two contact-form blocks: all legacy `contact-form` blocks on a site resolve to the first one's fields/recipient. | `FormController.php:199-201` | CODE |
| D8 | Rollback UI offers every `live` deployment; a rollback's own deployment is `rolled_back` and can't be targeted; the next auto-publish overwrites the rollback. | `PublishButton.tsx:200`; `PublishSiteJob.php:144-152` | CODE |
| D9 | **A site (or page/post) with version snapshots cannot be hard-deleted**: `page_versions` FKs are `ON DELETE SET NULL` but the `chk_page_or_post` CHECK requires one of them, so the cascade violates the check. Soft delete works; force delete / purge fails. | Force-deleting the test site → SQLSTATE 23514 `chk_page_or_post` | LIVE |

## E. Confusing but working

- Five things named library/section/template: Library, Global Sections, the editor's Section Library presets, Templates (theme templates), starter templates.
- Three places for fonts/colors: Theme Editor, Theme Studio (same data), Settings → Global Styles (silently overrides the theme).
- Duplicate controls in the page editor: two Editor Mode switches (the sidebar one saves without converting), two layout pickers, two status controls.
- Publish `type` partial vs full: identical behavior.
- Categories and Tags hidden under ADVANCED; form submissions under Settings → Forms; **Clear** (takes the site offline) sits next to **Create Page**.
- The top-bar Publish button appears on the Dashboard and targets the last opened site without naming it.
- Autosave default is 5 idle minutes; editor messages mix Bulgarian and English.

## Suggested fix order

1. **B1 + B2** — give new sites a working header and footer: seed the Full Width grid's footer area as Menu/footer and create header + footer menus from the starter template pages (or make the grid footer area fall back to the Global Footer template). Biggest first-impression win.
2. **B3** — slugify with transliteration in the dialog (or send no slug and let the server do it).
3. **B5 / C5 / C6** — one consistent rule: every save that changes published output either auto-publishes or flags stale.
4. **C1, C2, C3, B4** — small missing UI/request fields.
5. **D1, D2** — data loss and invisible pages.
