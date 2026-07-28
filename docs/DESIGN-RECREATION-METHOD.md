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
- **Divi / друг builder**: `php artisan migration:spider {site} {origin}`
  (`LiveContentExtractor` — rendered DOM → блокове; accordion/tabs/gallery/
  counters имат explicit поддръжка).
- **Тема** (цветове/шрифтове/лого): Theme Wizard token profile (ръчен при
  липса на AI кредити — heikotera pattern); шрифтовете се взимат VERBATIM
  (Google Fonts проверка). Спот-проверки: мери computed font-size/family
  на h1/h2/body на двете страници.

### 3. Обективна оценка (след всеки build)
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

### 5. Site-specific рецепти (custom_css / settings)
- Прозрачно меню върху hero: `body:has(main > section:first-child :is(.hero-block,.sp-slider)) .pos-header{position:absolute;…}` + бели линкове `!important`.
- Тъмна титулна лента на вътрешни страници: first-section override с `:not(:has(…))`.
- Типографска скала: `:root{--font-size-2xl:…}` (генераторът има defaults, не документни токени).
- Футър: Menu location=footer + settings `footer_columns=[{heading,lines[]}]`,
  `footer_copyright`, `logo_url`, `favicon_url`; grid сайтове: footer
  позицията → `type=menu, config_json={location:footer}`.

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
