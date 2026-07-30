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
  - **Типография per-widget**: heading чете собствения `typography_font_size`/
    `line_height`/`letter_spacing` на widget-а (hero h1=66px точно, не theme scale).
  - **AUTO-RECIPE (import сам пише в `settings.custom_css`)**: kit `system_typography`
    → type scale (`--font-size-2xl/3xl/xl`, `--line-height-heading`) + overlay-header
    рецепта КОГАТО home hero е slider. Между маркери `/* >>> elementor auto-recipe */`
    … `/* <<< */` → re-import само refresh-ва тоя блок, ръчният CSS остава. **Значи
    типография + прозрачно меню вече НЕ са ръчна стъпка** (виж §5 — остава само
    footer/site-specific).
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
2. **Планер (assisted, не пази пароли)**: `php artisan elementor:plan --site --tenant --wp-db --wp-user --wp-pass --wp-prefix --origin` ИЛИ Migration admin страницата → "Elementor import planner" панел. Изброява Elementor страниците, matched към CMS slug-овете, и печата ГОТОВАТА `elementor:import` команда (парола = placeholder). Спестява ръчния id→slug мапинг (`ElementorImportPlanner`, host lock 127.0.0.1).
3. Backup: `DB::table('blocks')->where('blockable_id',<pageid>)` преди import.
4. `elementor:import --tenant --site --wp-db --wp-user --wp-pass --wp-prefix --origin --catalog-post --pages=ID:slug` (БЕЗ `--publish` = draft).
5. Build in-process → сравни (виж §3) → чак тогава `PublishOrchestrator::publish($site,$user,'full')` (terminal статус = **'live'**).
6. Типография + overlay меню се пишат АВТОМАТИЧНО от import (auto-recipe).
   Ръчно остава само: `nav_cta` (header CTA от Elementor header template, не от
   страницата), footer данни, и тъмна титулна лента на вътрешни стр. Мери с `migration:diff`.
7. Останалите разлики са СЕКЦИОННИ → фикс в компилатора (§2 списъка вече покрива hero/icon-box/cards/stats/news), НЕ на страницата. Blade edit → `view:clear`+`queue:restart`.

### 7. Mobile / responsive — DEFAULT, наготово (не е ръчна стъпка)

**Мобилната адаптивност е системна, не per-page.** Компилаторът произвежда
mobile-friendly output за ВСЕКИ сайт наготово; не пипай страници на ръка за
телефон. Логиката живее на едно място — критичния CSS, който всеки wrapper
инжектира (`BuildPageService::buildCriticalCss()`) — за да го наследяват и
`layout.blade`, и `grid-layout.blade` (Elementor/grid сайтове), и magazine.
Валидирано на vioiv (12 страници: 71–145px overflow → 0 на 390px).

Какво прави автоматично при `@media(max-width:768px)`:
- **Kill grid blowout**: `.site-grid,.site-grid *,main *,[class*="-block"]{min-width:0}`
  — без това единствен nowrap/широк наследник разпъва `1fr` (==`minmax(auto,1fr)`)
  трак по-широк от екрана и чупи цялата страница (това беше причина №1).
- `.site-grid{grid-template-columns:1fr!important}` — страничният grid става една колона.
- Стакват се всички multi-column блок-грид-ове (`stats`/`gallery`/`columns` +
  всеки inline `repeat(`/`1fr 1fr`).
- Секционен padding надолу, `img/video/iframe{max-width:100%}`, широки таблици скролват, sticky sidebar се стака.
- Заглавия се клампват (`h1/h2/h3` clamp с `!important`) — auto-recipe/токен може да е сложил h1 > 3rem.

Освен това:
- **`GridCssGenerator`**: всеки grid без изричен `breakpoints_json['mobile']`
  колабира на 1 колона на ≤768px (не само когато има `mobile_order`) +
  `.site-grid > *{min-width:0}`. Пазено с `GridCssMobileTest`.
- **Токени** (`DesignTokenGenerator`): `--font-size-xl…5xl` са `clamp()` по
  подразбиране (max == старата фиксирана стойност → desktop непроменен).

Site-specific капан (виж §5): full-bleed секция трябва да пробива до ръба на
viewport-а с `margin-left:calc(50% - 50vw)`, НЕ `margin-left:0` — при mobile
gutter на grid-а `0` я измества с ширината на gutter-а (vioiv slider hero =
20px overflow на 7 стр., докато custom_css правилото не го смени на `calc`).

Проверка: `node scripts/migration-shots.mjs` при 390px + програмен overflow
одит (`document.documentElement.scrollWidth > innerWidth`; изброй елементите с
`getBoundingClientRect().right > innerWidth`). „Изглежда добре" не е измерване —
чети кадъра И числото.

### 8. Performance / достъпност — DEFAULT (наготово)

Всичко е системно, всеки сайт го получава наготово при следващ publish.

- **Google Fonts self-hosted** (не render-blocking): `GoogleFontPublisher`
  (`app/Domain/Publishing/Services/`) сваля css2-а на Google с модерен UA,
  тегли всяко woff2, копира го в build-а под `/assets/fonts/{sha1}.woff2` и
  връща СЪЩИЯ CSS с пренаписани URL-и към нашия домейн. Само URL-ите се сменят
  → всички `unicode-range` subset-и (latin, latin-ext, **cyrillic**) остават →
  кирилицата рендерира. `DesignTokenGenerator::generateFontImports()` ползва
  локалния CSS, а при липса на deploy target / мрежа пада обратно на `@import`
  (admin preview). URL-ите се rebase-ват с `/{slug}` от
  `rewriteBaseForSlugHosting`. Байтовете се кешират на `assets` диска между
  build-ове. CSP вече позволява `font-src 'self'`. Пазено с
  `GoogleFontPublisherTest`.
- **Изображения**: библиотечните asset-и вече носят `dimensions` + `webp_*`
  варианти (Intervention v4/GD). Блоковете emit-ват `<picture>`+webp source +
  `width/height` (CLS). Покрити: `image`, `gallery`, `hero`,
  `collection-categories` (последният зарежда Asset-а за node/record снимките).
  Останалите post-thumbnail блокове ползват `featured_image` стринг → нямат
  dims (бъдеща работа). Пазено с `SemanticHtmlTest`, `AssetVariantsTest`.
- **Достъпност**: футър column заглавия са `h2` (heading-order след h1);
  футър muted текст ползва `--footer-muted` (color-mix от footer text→bg,
  контраст на тъмен футър); бутони ползват `--btn-color` (не hardcoded бяло —
  напр. search бутонът); nav/footer/lang линковете имат tap-target размер на
  ≤768px (в `buildCriticalCss`).

## Платформени правила, въведени от този метод (пазени с тестове)

- **Scroll-triggered entrance анимации СА DEFAULT** за всички публикувани
  страници (paused до влизане във viewport, no-JS safe, reduced-motion
  bypass) — `ScrollEntranceAnimationTest` пази механизма.
- Full-bleed секции наистина стигат ръбовете (breakout през padded wrappers).
- **Mobile-friendly е DEFAULT** за всеки сайт (§7): grid колабира на 1 колона,
  grid items shrink-ват (`min-width:0`), заглавия се клампват, блок-грид-ове се
  стакват — от `buildCriticalCss()` + `GridCssGenerator`. Пазено с `GridCssMobileTest`.
- **Performance/a11y са DEFAULT** (§8): Google Fonts се self-host-ват (сваляне на
  woff2 в build-а, локален `@font-face`, без render-blocking `@import`) —
  `GoogleFontPublisher` + `DesignTokenGenerator`. Raster изображения носят
  `<picture>`+webp+`width/height` (image/gallery/hero/collection-categories).
  Футър заглавия са `h2` (heading-order), футър текст ползва контрастен цвят,
  бутони ползват `--btn-color`, tap-targets имат размер на телефон.
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
