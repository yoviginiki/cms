<?php

namespace App\Console\Commands;

use App\Console\Commands\Migration\ResolvesSiteForCli;
use App\Domain\Collections\Services\CollectionCategoryService;
use App\Models\CollectionCategoryNode;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use Illuminate\Console\Command;

/**
 * One-off (idempotent, re-runnable) migration of a collection's flat
 * category/subcategory select fields into the real category tree.
 *
 * For each record it reads two data keys (default `category` → root,
 * `subcategory` → leaf), find-or-creates the matching tree nodes, and files the
 * record under the leaf node (or the root when there's no subcategory). Fields
 * kept as-is on the record (manufacturer, language, etc.) are left untouched —
 * they're orthogonal facets, not tree levels.
 *
 * Idempotent: nodes are matched by (collection, parent, name) so re-running
 * neither duplicates nodes nor re-files records that are already correct.
 */
class CollectionsMigrateTreeCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'collections:migrate-tree
        {--site= : Site id or slug}
        {--collection= : Collection id or slug on that site}
        {--category-key=category : Record data key holding the top-level category}
        {--subcategory-key=subcategory : Record data key holding the leaf category}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Build a collection category tree from flat category/subcategory record values';

    public function handle(CollectionCategoryService $service): int
    {
        $site = $this->resolveSite((string) $this->option('site'));
        if (!$site) {
            $this->error('Site not found: ' . $this->option('site'));

            return self::FAILURE;
        }

        $target = (string) $this->option('collection');
        $collection = ContentCollection::where('site_id', $site->id)
            ->where(fn ($q) => \Illuminate\Support\Str::isUuid($target)
                ? $q->where('id', $target)->orWhere('slug', $target)
                : $q->where('slug', $target))
            ->first();
        if (!$collection) {
            $this->error("Collection not found on {$site->slug}: {$target}");

            return self::FAILURE;
        }

        $catKey = (string) $this->option('category-key');
        $subKey = (string) $this->option('subcategory-key');
        $dry = (bool) $this->option('dry-run');

        $this->info(($dry ? '[dry-run] ' : '') . "Migrating '{$collection->name}' on {$site->slug} using {$catKey} → {$subKey}");

        // Local caches of resolved nodes so we hit find-or-create once per name.
        $roots = [];   // catName => node
        $leaves = [];  // catName."\0".subName => node
        $createdRoots = 0;
        $createdLeaves = 0;
        $assigned = 0;
        $unchanged = 0;

        $records = Record::where('collection_id', $collection->id)
            ->orderBy('created_at')->get(['id', 'data', 'category_node_id']);

        foreach ($records as $record) {
            $data = $record->data ?? [];
            $catName = trim((string) ($data[$catKey] ?? ''));
            $subName = trim((string) ($data[$subKey] ?? ''));

            if ($catName === '' && $subName === '') {
                continue; // nothing to file
            }

            // Root node for the category value.
            $rootNode = null;
            if ($catName !== '') {
                if (!array_key_exists($catName, $roots)) {
                    $roots[$catName] = $this->findOrCreate($service, $collection, $site, null, $catName, $dry, $createdRoots);
                }
                $rootNode = $roots[$catName];
            }

            // Leaf node for the subcategory value under that root.
            $targetNode = $rootNode;
            if ($subName !== '') {
                $parentId = $rootNode?->id;
                $leafKey = $catName . "\0" . $subName;
                if (!array_key_exists($leafKey, $leaves)) {
                    $leaves[$leafKey] = $this->findOrCreate($service, $collection, $site, $parentId, $subName, $dry, $createdLeaves);
                }
                $targetNode = $leaves[$leafKey];
            }

            $targetId = $targetNode?->id;
            if ($record->category_node_id === $targetId) {
                $unchanged++;
                continue;
            }
            if (!$dry && $targetId) {
                $record->update(['category_node_id' => $targetId]);
            }
            $assigned++;
        }

        $this->line('');
        $this->info('Roots (created this run): ' . $createdRoots);
        $this->info('Leaves (created this run): ' . $createdLeaves);
        $this->info('Records (re)assigned: ' . $assigned);
        $this->info('Records already correct: ' . $unchanged);
        if ($dry) {
            $this->warn('Dry run — no changes written.');
        }

        return self::SUCCESS;
    }

    /**
     * Find a node by (collection, parent, name) or create it. On a dry run a
     * missing node is faked (id null) so the record pass can proceed.
     */
    private function findOrCreate(
        CollectionCategoryService $service,
        ContentCollection $collection,
        Site $site,
        ?string $parentId,
        string $name,
        bool $dry,
        int &$createdCounter,
    ): ?CollectionCategoryNode {
        $existing = CollectionCategoryNode::where('collection_id', $collection->id)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->first();
        if ($existing) {
            return $existing;
        }

        $createdCounter++;
        if ($dry) {
            return null; // no id available; records under it are counted as "would assign"
        }

        return $service->create($collection, $site, [
            'name' => $name,
            'parent_id' => $parentId,
        ]);
    }
}
