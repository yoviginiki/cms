# Inline Edit — карта на системата

Справочник на главните части: **какво прави** всяка и **с какво е свързана**.
Целта е бърза ориентация — не изчерпателна документация.

## Потокът накратко

```
Потребител отваря preview + ?sp_edit=1
        │
        ▼
DynamicSiteController::renderContent  ── gate: PagePolicy/PostPolicy::inlineEdit
        │   (RenderContext = Edit)
        ▼
BuildPageService::renderBlock ──► Blade partial ──► sp_editable()  → data-sp-* атрибути
        │
        ▼ (инжектира)
overlay.js  ◄──postMessage──►  toolbar.js  ──HTTP──►  InlineEditController ──► InlineEditService
 (редактиране)                 (toolbar+save)          (session/patch/draft/publish/export)
        │                                                      │
        ▼ клик на картинка                                     ▼ пише в живите blocks (draft)
   AssetPickerPopup (React)                             PublishOrchestrator (при publish)
```

---

## 1. Render слой (сървър, Blade) — «показва редактируемото»

| Име | Файл | Какво прави | Свързано с |
|---|---|---|---|
| `RenderMode` (enum) | `app/Domain/Publishing/Rendering/RenderMode.php` | Двете състояния: `Publish` / `Edit` | RenderContext, sp_editable |
| `RenderContext` | `app/Domain/Publishing/Rendering/RenderContext.php` | Носи текущия режим; `isEdit()`, `set()`, **`runIn()`** | sp_editable (чете го), контролерите (задават го) |
| **`sp_editable($blockId,$path,$type,$locked)`** | `app/Support/Rendering/sp_helpers.php` | Единствената точка, която емитва `data-sp-block/field/type`; в Publish връща `''` | RenderContext, Blade partial-ите, overlay.js (чете атрибутите) |
| `BuildPageService::renderBlock()` | `app/Domain/Publishing/Services/BuildPageService.php` | Подава `__blockId`/`__blockType` на всеки partial | sp_editable (през partial-ите) |
| Block partial-и (25 бр.) | `resources/views/blocks/*.blade.php` | Викат `sp_editable()` на редактируемия елемент | sp_editable; golden теста ги пази byte-identical |

**Golden гаранция:** `tests/Unit/InlineEdit/SpEditableRenderTest.php` — доказва, че Publish изходът е байт-за-байт същият.

---

## 2. Edit runtime (браузър, vanilla JS) — «самото редактиране»

Двата скрипта говорят помежду си **само през postMessage** (контракт: `docs/inline-edit-protocol.md`).

### `public/inline-edit/overlay.js` — edit runtime (страната на съдържанието)
| Функция | Какво прави | Свързано с |
|---|---|---|
| `boot()` | Индексира `[data-sp-block]`, праща `sp:ready` | toolbar.js |
| `enableEl()` | Прави елемента contenteditable / image клик / locked | sp_editable атрибутите |
| `sp:field:focus/dirty/blur` (`send`) | Праща фокус+rect / промяна / финална стойност | toolbar.js |
| `stripRichText()` | Ограничава richtext до b/i/a/br | — |
| **`openAssetPicker()` / `applyImage()`** | Отваря media library popup-а; прилага избора + autosave | AssetPickerPopup, toolbar (autosave) |
| `handleParent()` | Изпълнява `sp:mode / sp:command / sp:field:set / sp:conflict` | toolbar.js |

### `public/inline-edit/toolbar.js` — parent контролер + toolbar + save
| Функция | Какво прави | Свързано с |
|---|---|---|
| `openSession()` | `POST inline/session` → version + block hashes | InlineEditController |
| `scheduleSave()` / **`flushSave()`** | Autosave (debounce 2s / веднага при blur) → `PATCH inline/blocks` | InlineEditController; overlay (dirty събития) |
| `showToolbar()` | Позиционира плаващия toolbar по `rect` | overlay (sp:field:focus) |
| `trackDirty()` / `status()` | Badge «N незапазени / Записано ✓ / Конфликт» | overlay |
| toolbar бутони | bold/italic/link → `sp:command`; image → `sp:image:request` (fallback) | overlay |

---

## 3. Save / Draft / Export / Publish API (сървър)

### `InlineEditController` — `app/Http/Controllers/Api/V1/InlineEditController.php`
Тънки public методи (pages + posts) → общи private helper-и за `Page|Post`.

| Endpoint (public метод) | Private helper | Прави |
|---|---|---|
| `session` / `sessionPost` | `openSession()` | Отваря сесия: version + per-block hash |
| `patchBlocks` / `patchBlocksPost` | `patchContentBlocks()` | Batch patch на полета (409 lock) |
| `draft` / `draftPost` | `makeDraft()` | Materializira draft `page_version` (page_id/post_id) |
| `publish` / `publishPost` | `publishContent()` | Промотва към published + rebuild |
| `export` / `exportPost` | `exportContent()` | json (blocks) / html (Publish render) |

Свързан с: `InlineEditService`, `BlockService` (`blocksVersion`, `getBlockTree`), `PublishOrchestrator`, `routes/api.php` (10 route-а).

### `InlineEditService` — `app/Domain/InlineEdit/Services/InlineEditService.php`
Чиста логика (DB-free тестваема). Свързан с: `BlockRegistry` (схема), `SanitizationService` (санитайзер).

| Функция | Прави |
|---|---|
| **`sanitizeField()`** | Валидира field path (flat + nested `items.0.title`) → **422** при непознат; санитизира със същия конфиг |
| `applyPatches()` | Прилага patch-овете (dot-path през `Arr::set`) |
| `assertPatchable()` | **403** ако блокът е споделено ентити (slider_ref/global_ref/menu/...) |
| `assertHashMatches()` | **409** при разминат block hash (optimistic lock) |
| `blockHash()` | Хеш на блока (lock handle) |

---

## 4. RBAC (сървър) — «кой може»

| Име | Файл | Прави |
|---|---|---|
| `PagePolicy::inlineEdit` / `inlinePublish` | `app/Policies/PagePolicy.php` | editor+ (или author на своя страница) / admin+ |
| `PostPolicy::inlineEdit` / `inlinePublish` | `app/Policies/PostPolicy.php` | същото за постове |
| `pages.author_id` / `posts.author_id` | миграция / модели | Ownership за author-scoped edit |

Проверява се **двойно**: при render (gate в DynamicSiteController) и при запис (`authorize()` в контролера).
Матрица тест: `tests/Unit/InlineEdit/PageInlinePolicyTest.php`.

---

## 5. Preview host + entry (сървър + React) — «откъде се влиза»

| Име | Файл | Прави | Свързано с |
|---|---|---|---|
| `DynamicSiteController::renderContent` | `app/Http/Controllers/DynamicSiteController.php` | Web preview; пали Edit при `?sp_edit=1`+policy; инжектира overlay+toolbar | RenderContext, inlineEditAssets, buildToolbar |
| `inlineEditAssets()` | (същия файл) | Инжектира config (`apiBase`, `assetPickerUrl`, ...) + overlay.js + toolbar.js | overlay/toolbar |
| `buildToolbar()` | (същия файл) | Admin toolbar-а + бутон **«✏️ Редактирай на място»** (toggle `?sp_edit=1`) | editModeFor |
| `AssetPickerPopup` | `resources/admin/src/pages/AssetPickerPopup.tsx` | Popup route, обвива реалния AssetPicker; връща избора през postMessage | overlay.js (opener), AssetPicker |
| `AssetPicker` | `resources/admin/src/components/ui/AssetPicker.tsx` | Реалната media library (browse/search/upload) — съществуващ | AssetPickerPopup |

---

## 6. Документи / тестове

| Файл | Какво |
|---|---|
| `docs/inline-edit-protocol.md` | postMessage контракт (overlay ↔ toolbar) |
| `docs/inline-edit-scope.md` | Какво НЕ прави inline редакторът (scope wall) |
| `docs/inline-edit-map.md` | Този файл |
| `tests/Unit/InlineEdit/*` | golden byte-identity, write rules, RBAC матрица (DB-free) |
| `tests/Feature/InlineEdit/*` | HTTP тестове (CI-ready) |
| `STATUS.md` (секция Inline Edit Layer) | Статус матрица + известни отклонения |
