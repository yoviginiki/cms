<?php

namespace App\Domain\Magazine\Services;

use App\Domain\Magazine\Models\MagElement;
use App\Domain\Magazine\Models\MagPage;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

class MagazineService
{
    /**
     * Get full document for a page (pages + elements).
     */
    public function getDocument(Page $page): array
    {
        $magPages = MagPage::where('page_id', $page->id)
            ->orderBy('page_number')
            ->get();

        // If page has no mag_pages yet, create first blank page
        if ($magPages->isEmpty()) {
            $firstPage = MagPage::create([
                'page_id' => $page->id,
                'page_number' => 1,
                'page_size' => ['width' => 595, 'height' => 842],
                'margins' => ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
            ]);
            $magPages = collect([$firstPage]);
        }

        $elements = MagElement::where('page_id', $page->id)
            ->orderBy('page_number')
            ->orderBy('z_index')
            ->get();

        return [
            'pages' => $magPages->toArray(),
            'elements' => $elements->toArray(),
        ];
    }

    /**
     * Atomic sync — replaces all pages and elements in a transaction.
     */
    public function syncDocument(Page $page, array $pagesData, array $elementsData): void
    {
        DB::transaction(function () use ($page, $pagesData, $elementsData) {
            // Delete all existing mag_pages and mag_elements for this page
            MagElement::where('page_id', $page->id)->delete();
            MagPage::where('page_id', $page->id)->delete();

            // Insert new mag_pages
            foreach ($pagesData as $pd) {
                $pageAttrs = [
                    'page_id' => $page->id,
                    'page_number' => $pd['page_number'],
                    'page_size' => $pd['page_size'] ?? ['width' => 595, 'height' => 842],
                    'margins' => $pd['margins'] ?? ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
                    'is_master' => $pd['is_master'] ?? false,
                    'master_page_id' => $pd['master_page_id'] ?? null,
                    'spread_with' => $pd['spread_with'] ?? null,
                    'background_color' => $pd['background_color'] ?? null,
                    'background_asset_id' => $pd['background_asset_id'] ?? null,
                ];
                // Only set jsonb fields if provided (DB has NOT NULL defaults)
                if (isset($pd['bleed'])) $pageAttrs['bleed'] = $pd['bleed'];
                if (isset($pd['columns'])) $pageAttrs['columns'] = $pd['columns'];
                if (isset($pd['baseline_grid'])) $pageAttrs['baseline_grid'] = $pd['baseline_grid'];

                MagPage::create($pageAttrs);
            }

            // Insert new mag_elements
            foreach ($elementsData as $el) {
                $elAttrs = [
                    'page_id' => $page->id,
                    'parent_id' => $el['parent_id'] ?? null,
                    'type' => $el['type'],
                    'name' => $el['name'] ?? null,
                    'data' => $el['data'] ?? [],
                    'x' => $el['x'],
                    'y' => $el['y'],
                    'width' => $el['width'],
                    'height' => $el['height'],
                    'rotation' => $el['rotation'] ?? 0,
                    'scale_x' => $el['scale_x'] ?? 1,
                    'scale_y' => $el['scale_y'] ?? 1,
                    'z_index' => $el['z_index'] ?? 0,
                    'locked' => $el['locked'] ?? false,
                    'visible' => $el['visible'] ?? true,
                    'layer_name' => $el['layer_name'] ?? null,
                    'style' => $el['style'] ?? [],
                    'thread_id' => $el['thread_id'] ?? null,
                    'thread_order' => $el['thread_order'] ?? null,
                    'page_number' => $el['page_number'],
                    'on_master' => $el['on_master'] ?? false,
                    'created_by' => $el['created_by'] ?? null,
                ];
                // Only set jsonb fields if provided (DB has NOT NULL defaults)
                if (isset($el['typography'])) $elAttrs['typography'] = $el['typography'];
                if (isset($el['text_wrap'])) $elAttrs['text_wrap'] = $el['text_wrap'];
                if (isset($el['responsive_overrides'])) $elAttrs['responsive_overrides'] = $el['responsive_overrides'];

                MagElement::create($elAttrs);
            }
        });
    }

    /**
     * Add a single page after the given page number.
     */
    public function addPage(Page $page, int $afterPageNumber): MagPage
    {
        return DB::transaction(function () use ($page, $afterPageNumber) {
            $newPageNumber = $afterPageNumber + 1;

            // Increment page_number for all pages after
            MagPage::where('page_id', $page->id)
                ->where('page_number', '>=', $newPageNumber)
                ->increment('page_number');

            // Also increment page_number on elements for shifted pages
            MagElement::where('page_id', $page->id)
                ->where('page_number', '>=', $newPageNumber)
                ->increment('page_number');

            // Create new page
            return MagPage::create([
                'page_id' => $page->id,
                'page_number' => $newPageNumber,
                'page_size' => ['width' => 595, 'height' => 842],
                'margins' => ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
            ]);
        });
    }

    /**
     * Delete a page and its elements, then renumber subsequent pages.
     */
    public function deletePage(Page $page, int $pageNumber): void
    {
        DB::transaction(function () use ($page, $pageNumber) {
            // Delete elements on this page
            MagElement::where('page_id', $page->id)
                ->where('page_number', $pageNumber)
                ->delete();

            // Delete the mag_page
            MagPage::where('page_id', $page->id)
                ->where('page_number', $pageNumber)
                ->delete();

            // Decrement page_numbers for pages after
            MagPage::where('page_id', $page->id)
                ->where('page_number', '>', $pageNumber)
                ->decrement('page_number');

            MagElement::where('page_id', $page->id)
                ->where('page_number', '>', $pageNumber)
                ->decrement('page_number');
        });
    }
}
