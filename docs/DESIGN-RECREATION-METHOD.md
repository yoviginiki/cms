# Design Recreation Method (Методология за пресъздаване на дизайн)

Канонният процес за пресъздаване на съществуващ сайт/страница в CMS-а като
**редактируеми native блокове** с вярност към оригинала — съдържание,
подредба, цветове, типография, анимации и ефекти. Изведен и валидиран на
две реални миграции (heikotera.com — Divi, vioiv — Elementor, 2026-07).

**Не започвай от нулата.** Всичко по-долу вече е код в платформата.

---

## Принципи (научени скъпо)

1. **Извличай от най-точния наличен източник, никога „на око“.**
   Builder JSON (Elementor `_elementor_data`) > rendered DOM > screenshot.
   Визуалното четене на отворен accordion е неотличимо от статичен текст —
   DOM-ът/JSON-ът знаят разликата.
2. **Мери, не преценявай.** Всяка итерация започва и завършва с обективна
   метрика (text coverage %, per-page missing списъци, pixel mismatch %,
   секционни височини). „Изглежда добре“ не е измерване.
3. **Всеки фикс отива в екстрактора/компилатора/блока — не в страницата.**
   Така следващият сайт го получава наготово. Ръчни са само site-specific
   неща (футър данни, custom_css рецепти).
4. **Проверявай визуално в пълен мащаб.** Смален screenshot крие 15px
   шевове, отрязани aspect-и и off-screen елементи. DOM пробите също лъжат
   (елемент с валиден rect може да е изхвърчал с absolute/left:-85%) —
   решаващ е кадърът.
5. **Анимациите се снимат „уталожено“.** Entrance анимации правят празни
   секции в headless capture; Ken Burns мърда пикселите. Инструментите
   вече инжектират animation-kill CSS на двете страни.

## Стъпките

### 1. Съдържание
- WordPress: WXR import (вкл. менюта) — но той дава ~50% вярност сам по себе си.
- Постове от източника: `elementor:import --posts=N --posts-lang=bg --posts-exclude=…`

### 2. Layout extraction
- **Elementor**: `php artisan elementor:import --tenant=… --site=… --wp-db=… --wp-user=… --wp-pass=… --wp-prefix=… --origin=… --catalog-post=… --pages=WPID:slug,…`
  (`ElementorTreeCompiler`: widget мапинг, kit `__globals__` цветове,
  рекурсивни row/column от flex контейнерите, width% → 12-grid col_spans,
  hero → **animated slider** със слоеве, `_animation` → `__animation`,
  hover → effects incl. `shine`, динамични widgets през WP-context.)
- **Компилаторът ВЕЧЕ прави следното наготово — не го преоткривай (валидирано на vioiv):**
  - **Hero (heroSection)**: пази двата `<p>` параграфа (не flatten); checklist →
    сини `fa-check-circle` SVG икони; видео pop-out ("Заявете консултация" + play
    бутон от `elementskit-video` `ekit_video_popup_url`); слоеве по %.
  - **icon-box (iconBoxModules)**: рендерира `ekit_icon_box_header_icons` SVG —
    `icon_position=left` → бяла икона в синя плочка (icon-left); иначе → brand-blue
    икона отгоре (без плочка). Компактно 17px title, НЕ гигантски h3.
  - **card tiles (cardStyle)**: container bg/border/radius/padding → column card
    (glass service grids); gated на реален "card" (fill или border+radius).
  - **stats**: `counter/funfact/progressbar` → `plain` (безрамкови) числа+етикети.
  - **news**: `elementskit-blog-posts` → `latestposts` limit:3 columns:3 cards.
  - **icon-list** widget → сини check-circle (html-embed, не bullet list).
  - **Санитизацията гълта SVG/inline-style/`data:` в text-блок** → всяка цветна
    иконография се emit-ва като `html-embed` module (raw). Слой/колона може да е
    ЛЮБОЙ блок тип, вкл. `html-embed`.
  - **nav CTA бутон**: `settings.nav_cta = {text,url}` (MenuRenderer, per-site).
- **Divi / друг builder**: `php artisan migration:spider {site} {origin}`
  (`LiveContentExtractor` — rendered DOM → блокове; accordion/tabs/gallery/
  counters имат explicit поддръжка).
- **Тема** (цветове/шрифтове/лого): Theme Wizard token profile (ръчен при
  липса на AI кредити — heikotera pattern); шрифтовете се взимат VERBATIM
  (Google Fonts проверка). Спот-проверки: мери computed font-size/family
  на h1/h2/body на двете страници.

### 3. Обективна оценка (след всеки build)
- **Site Wizard я изпълнява САМ**: стъпката „verify" (Measuring fidelity
  against the source) рендерира всяка построена draft страница in-process и
  я сравнява с HTML-а на източника през `MigrationDiffChecker::compareHtml`.
  Per-page покритие + липси излизат като badge в списъка със страници на
  wizard UI; roll-up в детайла на стъпката. Ingest стъпката също detect-ва
  builder-а (Elementor/Divi/WordPress) и препоръчва native import пътя.
- `php artisan migration:diff {site} {origin} --include-home [--mobile]`
  → text coverage % + missing headings/images/links/texts per page.
- `node scripts/migration-shots.mjs pairs.json outDir` → pixel mismatch %
  (settled-state capture: scroll + animation-kill на двете страни).
- Playwright таблица на секционните височини (origin `div.elementor > .e-con`
  vs наш `main > section.section-block`) — таргетирай най-голямата делта.
- PIL side-by-side ленти В ПЪЛЕН МАЩАБ, section-anchored (не същия offset).

### 4. Итерации (редът, който работи)
1. Съдържателен цикъл до ≥95% text coverage (липсващи текстове/снимки/постове).
2. Височинен цикъл — секция по секция до изравняване на сумите.
3. Типографска скала + цветове от кита (вкл. „Black Color“ капана — кит
   имената лъжат, гледай hex-а).
4. Композиционни: full-bleed, overlay header, hero slider, колонни пропорции.
5. Поведенчески: scroll entrances (default!), hover ефекти, count-up, Ken Burns.
6. Финални: site-specific custom_css рецепти (виж по-долу).

### 5. Site-specific рецепти (custom_css / settings) — COPY-PASTE
Слагат се в `sites.settings.custom_css` (grid-layout инжектира в `<style>`).
**Прозрачно меню върху hero slider** (валидирано vioiv):
```css
body:has(.pos-main > section:first-child .sp-slider) .site-grid{position:relative}
body:has(.pos-main > section:first-child .sp-slider) .pos-nav{position:absolute;top:0;left:0;right:0;z-index:1000}
body:has(.pos-main > section:first-child .sp-slider) .pos-nav .site-nav{position:static!important;background:transparent!important;border-bottom:none!important;box-shadow:none!important;backdrop-filter:none!important}
body:has(.pos-main > section:first-child .sp-slider) .pos-nav .menu-top-link,body:has(.pos-main > section:first-child .sp-slider) .pos-nav .menu-custom-link{color:#fff!important}
```
**Типографска скала** (генераторът има defaults, НЕ документни токени — мери source h1/h2 computed px, дели на 16 за rem):
```css
:root{--font-size-3xl:4.125rem;--font-size-2xl:2.875rem;--line-height-heading:1.2;--line-height-tight:1.2}
```
**Hero heading 2-реда** (широк слой за да не се чупи на 3 реда при голям шрифт): `.sp-slider .sp-slide .sp-layer:has(h1){width:60%!important}`
- Тъмна титулна лента на вътрешни страници: first-section override с `:not(:has(…))`.
- Settings (per-site): `nav_cta={text,url}` (header бутон), `logo_url`, `favicon_url`.
- Футър: Menu location=footer + settings `footer_columns=[{heading,lines[]}]`,
  `footer_copyright`, `logo_url`, `favicon_url`; grid сайтове: footer
  позицията → `type=menu, config_json={location:footer}`.

### 6. Бърза recipe за СЛЕДВАЩ Elementor сайт (минимум ръчна работа)
1. WP DB достъп: чети `wp-config.php` (DB_NAME/USER/PASSWORD, `$table_prefix`).
2. Мап WP post id→slug: `SELECT ID,post_name FROM {prefix}posts WHERE post_type='page' AND post_status='publish'` (+ провери `_elementor_data` през postmeta).
3. Backup: `DB::table('blocks')->where('blockable_id',<pageid>)` преди import.
4. `elementor:import --tenant --site --wp-db --wp-user --wp-pass --wp-prefix --origin --catalog-post --pages=ID:slug` (БЕЗ `--publish` = draft).
5. Build in-process → сравни (виж §3) → чак тогава `PublishOrchestrator::publish($site,$user,'full')` (terminal статус = **'live'**).
6. Приложи §5 custom_css рецептите + `nav_cta`. Мери с `migration:diff`.
7. Останалите разлики са СЕКЦИОННИ → фикс в компилатора (§2 списъка вече покрива hero/icon-box/cards/stats/news), НЕ на страницата. Blade edit → `view:clear`+`queue:restart`.

## Платформени правила, въведени от този метод (пазени с тестове)

- **Scroll-triggered entrance анимации СА DEFAULT** за всички публикувани
  страници (paused до влизане във viewport, no-JS safe, reduced-motion
  bypass) — `ScrollEntranceAnimationTest` пази механизма.
- Full-bleed секции наистина стигат ръбовете (breakout през padded wrappers).
- Галериите прилагат hover ефекти PER IMAGE (`.gallery-item` wrapper);
  `shine` е наличен preset в Card Effects.
- Stats блокът брои от 0 при показване.
- Slider runtime се инжектира и за raw `slider` блокове и се публикува в
  АКТИВНИЯ atomic build target.

## Капани (не ги преоткривай)

- Канонични block shapes: `gallery.images` и `logostrip.logos` са МАСИВИ ОТ
  URL СТРИНГОВЕ — обекти рендерират празно.
- Generated CSS в blade: `{{ }}` ескейпва кавички (`content:''` → счупен) —
  печатай code-built CSS с `{!! !!}`.
- CSS селектор със запетая в shared builder (`shineCss`) се прилага на
  ДВЕТЕ части — подавай единичен селектор.
- Slider layer координати са СТРИНГОВЕ с единици (`'6%'`), числата тихо
  падат на 0%.
- Blade edit → `php artisan view:clear` + `php artisan queue:restart`.
- `update()` с несъществуваща колона (`config` vs `config_json`) е тих no-op.
- Soft-deleted записи държат unique slugs — проверявай `withTrashed()`.
- Edge cache (~2h) — всички проверки с cache-buster.
- RLS: CLI/tinker изисква `SELECT set_config('app.current_tenant_id', …)`.

## Definition of done

- text coverage ≥95% на всички страници (100% цел за главните);
- секционни височини в рамките на ~1% от оригинала;
- поведенчески паритет: entrance по скрол, hover ефекти, брояци, слайдер;
- mobile audit чист (`migration:diff --mobile`);
- всичко редактируемо в builder-а; нула ръчни блокове по страниците.
