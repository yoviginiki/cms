<?php

namespace App\Domain\Collections\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Pages\Services\PageService;
use App\Models\ContentCollection;
use App\Models\Page;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * S6 — deterministic (no-AI) scaffolder shared by the Database, Search and
 * App wizards. Every primitive here is an existing, tested service call
 * (CollectionService, PageService, BlockService, ThemeTemplate) — the
 * wizards only orchestrate; they add no new persistence path.
 */
class AppScaffolder
{
    public function __construct(
        private CollectionService $collections,
        private PageService $pages,
        private BlockService $blocks,
    ) {}

    /**
     * Create a batch of collections in two passes: scalar fields first, then
     * relation fields (so relations can target collections by name, and
     * self-relations work — they need the collection id). Hierarchy is set
     * via CollectionService (which validates the parent field).
     *
     * @param array<int, array{name: string, tier?: string, fields: array, hierarchical?: bool}> $specs
     * @return array<string, ContentCollection> created collections keyed by lowercased name
     */
    public function createCollections(Site $site, array $specs): array
    {
        return DB::transaction(function () use ($site, $specs) {
            $byName = [];

            // Pass 1 — collections with their scalar (non-relation) fields.
            foreach ($specs as $spec) {
                $name = trim((string) ($spec['name'] ?? ''));
                if ($name === '') {
                    throw ValidationException::withMessages(['collections' => 'Every collection needs a name.']);
                }
                $scalarFields = array_values(array_filter(
                    $spec['fields'] ?? [],
                    fn ($f) => ($f['type'] ?? 'text') !== 'relation',
                ));
                if ($scalarFields === []) {
                    throw ValidationException::withMessages(['collections' => "Collection '{$name}' needs at least one field."]);
                }
                $titleField = $this->titleFieldKey($scalarFields);
                $tier = $spec['tier'] ?? 'static';
                $collection = $this->collections->create($site, [
                    'name' => $name,
                    'tier' => in_array($tier, ContentCollection::TIERS, true) ? $tier : 'static',
                    'schema' => ['fields' => $this->normalizeFields($scalarFields), 'title_field' => $titleField],
                ]);
                $byName[mb_strtolower($name)] = $collection;
            }

            // Pass 2 — add relation fields, resolving targets by name.
            foreach ($specs as $spec) {
                $relationFields = array_values(array_filter(
                    $spec['fields'] ?? [],
                    fn ($f) => ($f['type'] ?? 'text') === 'relation',
                ));
                if ($relationFields === [] && empty($spec['hierarchical'])) {
                    continue;
                }
                $collection = $byName[mb_strtolower((string) $spec['name'])];
                $fields = $collection->fields();
                foreach ($relationFields as $rf) {
                    $targetName = mb_strtolower((string) ($rf['target'] ?? ''));
                    $target = $byName[$targetName] ?? null;
                    if (!$target) {
                        throw ValidationException::withMessages([
                            'collections' => "Relation '{$rf['label']}' on '{$spec['name']}' targets unknown collection '{$rf['target']}'.",
                        ]);
                    }
                    $fields[] = [
                        'key' => $this->fieldKey($rf['label'] ?? 'related'),
                        'label' => $rf['label'] ?? 'Related',
                        'type' => 'relation',
                        'relation' => [
                            'collection_id' => $target->id,
                            'mode' => ($rf['mode'] ?? 'one') === 'many' ? 'many' : 'one',
                        ],
                    ];
                }

                $settings = [];
                if (!empty($spec['hierarchical'])) {
                    // Add a self-relation parent field + point hierarchy at it.
                    $fields[] = [
                        'key' => 'parent',
                        'label' => 'Parent',
                        'type' => 'relation',
                        'relation' => ['collection_id' => $collection->id, 'mode' => 'one'],
                    ];
                    $settings['hierarchy_field'] = 'parent';
                }

                $updated = $this->collections->update($collection, $site, [
                    'name' => $collection->name,
                    'schema' => ['fields' => $fields, 'title_field' => $collection->titleField()],
                    'settings' => $settings ?: null,
                ]);
                $byName[mb_strtolower((string) $spec['name'])] = $updated['collection'];
            }

            return $byName;
        });
    }

    /**
     * Turn on searchable/facetable flags and (optionally) build a search page
     * with the three island blocks bound to the collection.
     *
     * @param array<int, string> $searchable field keys
     * @param array<int, string> $facets field keys
     */
    public function configureSearch(Site $site, ContentCollection $collection, array $searchable, array $facets): ContentCollection
    {
        $fields = array_map(function ($field) use ($searchable, $facets) {
            $field['searchable'] = in_array($field['key'], $searchable, true);
            if (in_array($field['type'], ['select', 'multi_select', 'boolean', 'relation'], true)) {
                $field['facetable'] = in_array($field['key'], $facets, true);
            }

            return $field;
        }, $collection->fields());

        $result = $this->collections->update($collection, $site, [
            'name' => $collection->name,
            'schema' => ['fields' => $fields, 'title_field' => $collection->titleField()],
        ]);

        return $result['collection'];
    }

    /** Build a search page (search-box + facet-filter + results-grid). */
    public function buildSearchPage(Site $site, ContentCollection $collection, string $title, array $facets, array $cardFields): Page
    {
        $page = $this->pages->createPage(['title' => $title, 'status' => 'draft'], $site);
        $this->blocks->syncBlocks($page, [
            $this->section([
                $this->module('search-box', ['collectionId' => $collection->id, 'placeholder' => "Search {$collection->name}…"]),
                $facets !== [] ? $this->module('facet-filter', ['collectionId' => $collection->id, 'fields' => $facets, 'style' => 'checkbox']) : null,
                // eager: show all records + populated facets on load (search-first page).
                $this->module('results-grid', ['collectionId' => $collection->id, 'eager' => true, 'columns' => 3, 'showImage' => true, 'cardFields' => $cardFields]),
            ]),
        ]);
        $this->flagStale($page);

        return $page;
    }

    /**
     * Cross-collection search page (v3): search-box + Type facet + results
     * grid all pointed at the site-level /search/index.json manifest via the
     * '*' collectionId sentinel the blades understand.
     */
    public function buildCrossSearchPage(Site $site, string $title): Page
    {
        $page = $this->pages->createPage(['title' => $title, 'status' => 'draft'], $site);
        $this->blocks->syncBlocks($page, [
            $this->section([
                $this->module('search-box', ['collectionId' => '*', 'placeholder' => 'Search the whole site…']),
                $this->module('facet-filter', ['collectionId' => '*', 'style' => 'checkbox']),
                $this->module('results-grid', ['collectionId' => '*', 'eager' => true, 'columns' => 3, 'showImage' => true]),
            ]),
        ]);
        $this->flagStale($page);

        return $page;
    }

    /**
     * Customize a collection's auto-archive (published at /{prefix}/) with a
     * record-archive TEMPLATE — a heading + a record-loop that inherits the
     * paginated archive records + pagination. This must NOT be a standalone
     * page: a page at the collection's own prefix path collides with the
     * collection archive and the path-collision guard skips the whole
     * collection (no detail pages, no search index). The template renders
     * the same /{prefix}/ URL without occupying it as a page.
     */
    public function buildArchiveTemplate(Site $site, ContentCollection $collection, ?string $userId = null): ThemeTemplate
    {
        $template = ThemeTemplate::create([
            'site_id' => $site->id,
            'name' => "{$collection->name} listing",
            'slug' => Str::slug("{$collection->name}-listing") . '-' . Str::lower(Str::random(4)),
            'type' => 'record-archive',
            'collection_id' => $collection->id,
            'is_default' => true,
            'created_by' => $userId,
        ]);

        $this->blocks->syncBlocks($template, [
            $this->section([
                $this->module('heading', ['text' => $collection->name, 'level' => 'h1']),
                // No collectionId → inherits the archive's paginated $__archiveRecords.
                $this->module('record-loop', [
                    'layout' => 'cards',
                    'columns' => 3,
                    'limit' => 48,
                    'showImage' => true,
                    'linkToRecord' => true,
                ]),
                $this->module('archive-pagination', []),
            ]),
        ]);

        return $template;
    }

    /**
     * Build a record-single template for a collection: title, image, and a
     * field-value per non-title/non-image field.
     */
    public function buildRecordTemplate(Site $site, ContentCollection $collection, ?string $userId = null): ThemeTemplate
    {
        $template = ThemeTemplate::create([
            'site_id' => $site->id,
            'name' => "{$collection->name} detail",
            'slug' => Str::slug("{$collection->name}-detail") . '-' . Str::lower(Str::random(4)),
            'type' => 'record-single',
            'collection_id' => $collection->id,
            'is_default' => true,
            'created_by' => $userId,
        ]);

        $titleField = $collection->titleField();
        $imageField = null;
        $detailModules = [$this->module('record-title', ['tag' => 'h1'])];
        foreach ($collection->fields() as $field) {
            if ($field['type'] === 'image' && !$imageField) {
                $imageField = $field['key'];
                $detailModules[] = $this->module('record-image', ['field' => $field['key'], 'aspectRatio' => '4:3', 'objectFit' => 'cover']);
                continue;
            }
            if ($field['key'] === $titleField || $field['type'] === 'relation') {
                continue;
            }
            $detailModules[] = $this->module('field-value', ['field' => $field['key'], 'showLabel' => true]);
        }

        $sections = [$this->section($detailModules)];

        // Hierarchical collections (category trees): the detail page lists its
        // direct children so visitors can navigate down the tree.
        if ($collection->hierarchyField()) {
            $sections[] = $this->section([
                $this->module('heading', ['text' => 'Browse', 'level' => 'h2']),
                $this->module('record-loop', ['sourceMode' => 'children', 'layout' => 'list', 'columns' => 1, 'limit' => 100, 'linkToRecord' => true]),
            ]);
        }

        $this->blocks->syncBlocks($template, $sections);

        return $template;
    }

    // ─── Block-tree helpers (single column section) ─────────────────────

    private function section(array $modules): array
    {
        $modules = array_values(array_filter($modules));

        return [
            'id' => Str::uuid()->toString(),
            'type' => 'section',
            'level' => 'section',
            'order' => 0,
            'data' => ['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1100px'],
            'children' => [[
                'id' => Str::uuid()->toString(),
                'type' => 'row',
                'level' => 'row',
                'order' => 0,
                'data' => ['layout' => '1', 'gap' => '24px'],
                'children' => [[
                    'id' => Str::uuid()->toString(),
                    'type' => 'column',
                    'level' => 'column',
                    'order' => 0,
                    'data' => [],
                    'children' => array_map(fn ($m, $i) => $m + ['order' => $i], $modules, array_keys($modules)),
                ]],
            ]],
        ];
    }

    private function module(string $type, array $data): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'level' => 'module',
            'order' => 0,
            'data' => $data,
            'children' => [],
        ];
    }

    private function flagStale(Page $page): void
    {
        Page::whereKey($page->id)->toBase()->update([
            'needs_republish' => true,
            'needs_republish_reason' => 'scaffolded',
        ]);
    }

    /** @param array<int, array> $fields */
    private function titleFieldKey(array $fields): string
    {
        foreach ($fields as $f) {
            if (in_array($f['type'] ?? 'text', ['text', 'sku'], true)) {
                return $this->fieldKey($f['label'] ?? $f['key'] ?? 'title');
            }
        }

        return $this->fieldKey($fields[0]['label'] ?? 'title');
    }

    /** @return array<int, array> */
    private function normalizeFields(array $fields): array
    {
        return array_map(function ($f) {
            $out = [
                'key' => $this->fieldKey($f['label'] ?? $f['key'] ?? 'field'),
                'label' => $f['label'] ?? 'Field',
                'type' => $f['type'] ?? 'text',
            ];
            foreach (['required', 'unique', 'searchable', 'facetable', 'show_in_list'] as $flag) {
                if (!empty($f[$flag])) {
                    $out[$flag] = true;
                }
            }
            if (in_array($out['type'], ['select', 'multi_select'], true)) {
                $out['options'] = array_values(array_filter(array_map('strval', (array) ($f['options'] ?? []))));
            }

            return $out;
        }, $fields);
    }

    private function fieldKey(string $label): string
    {
        $key = Str::slug($label, '_');

        return $key !== '' ? $key : 'field';
    }
}
