<?php

namespace App\Domain\Magazine\Services;

use App\Models\Page;
use Illuminate\Support\Str;

/**
 * Converts a magazine-mode page (mag_pages + mag_elements) into a canvas block
 * tree: each magazine page → a Section block, each visible element → a child
 * block positioned via style.layout. Text-flow/threading, master pages and
 * page-number elements are dropped (they have no static-web equivalent). The
 * geometry (pt) is carried straight through as canvas px.
 */
class MagazineToCanvasConverter
{
    public function __construct(private MagazineService $magazineService)
    {
    }

    /** @return array the canvas block tree (section blocks with positioned children) */
    public function convert(Page $page): array
    {
        $doc = $this->magazineService->getDocument($page);
        $elements = collect($doc['elements']);
        $sections = [];
        $sectionOrder = 0;

        foreach (collect($doc['pages'])->sortBy('page_number') as $mp) {
            $pageEls = $elements
                ->where('page_number', $mp['page_number'])
                ->filter(fn ($el) => ($el['visible'] ?? true) && ! ($el['on_master'] ?? false))
                ->sortBy('z_index')
                ->values();

            $children = [];
            $order = 0;
            foreach ($pageEls as $el) {
                $mapped = $this->mapElement($el);
                if ($mapped === null) {
                    continue; // unmappable (ellipse, page_number, unknown) — skipped
                }
                $children[] = [
                    'id' => (string) Str::uuid(),
                    'type' => $mapped['type'],
                    'level' => 'module',
                    'data' => $mapped['data'],
                    'order' => $order++,
                    'children' => [],
                    'style' => ['layout' => [
                        'position' => 'absolute',
                        'x' => (int) round($el['x'] ?? 0),
                        'y' => (int) round($el['y'] ?? 0),
                        'width' => ((int) round($el['width'] ?? 200)) . 'px',
                        'height' => ((int) round($el['height'] ?? 100)) . 'px',
                        'rotation' => (float) ($el['rotation'] ?? 0),
                        'zIndex' => (int) ($el['z_index'] ?? 0),
                    ]],
                ];
            }

            $size = is_array($mp['page_size'] ?? null) ? $mp['page_size'] : [];
            $bg = $mp['background_color'] ?? '';
            $sections[] = [
                'id' => (string) Str::uuid(),
                'type' => 'section',
                'level' => 'section',
                'order' => $sectionOrder++,
                'data' => ['canvas' => [
                    'height' => (int) round($size['height'] ?? 842),
                    'bleed' => false,
                    'background' => is_string($bg) ? $bg : '',
                ]],
                'style' => [],
                'children' => $children,
            ];
        }

        return $sections;
    }

    /** Canvas design width for the duplicated page = widest magazine page. */
    public function designWidth(Page $page): int
    {
        $doc = $this->magazineService->getDocument($page);
        $max = 0;
        foreach ($doc['pages'] as $mp) {
            $size = is_array($mp['page_size'] ?? null) ? $mp['page_size'] : [];
            $max = max($max, (int) round($size['width'] ?? 0));
        }

        return $max > 0 ? min(3000, max(320, $max)) : 1200;
    }

    /** Map a magazine element to a canvas block type + data, or null to skip. */
    private function mapElement(array $el): ?array
    {
        $type = $el['type'] ?? '';
        $data = is_array($el['data'] ?? null) ? $el['data'] : [];
        $content = is_string($data['content'] ?? null) ? $data['content'] : '';

        return match (true) {
            $type === 'headline_frame' => ['type' => 'heading', 'data' => ['text' => trim(strip_tags($content)), 'level' => 'h2']],
            $type === 'pullquote_frame' => ['type' => 'pullquote', 'data' => ['text' => trim(strip_tags($content))]],
            in_array($type, ['text_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'], true)
                => ['type' => 'text', 'data' => ['content' => $content]],
            in_array($type, ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'background_image', 'gallery_frame'], true)
                => ['type' => 'image', 'data' => ['src' => $data['src'] ?? ($data['url'] ?? ''), 'alt' => $data['alt'] ?? '']],
            $type === 'video_frame' => ['type' => 'video', 'data' => ['url' => $data['url'] ?? ($data['src'] ?? '')]],
            $type === 'button' => ['type' => 'button', 'data' => ['text' => $data['text'] ?? 'Button', 'url' => $data['url'] ?? '#']],
            $type === 'line' || $type === 'decorative_rule' => ['type' => 'divider', 'data' => []],
            default => null,
        };
    }
}
