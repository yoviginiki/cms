<?php

namespace App\Services\Magazine;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagElement;
use App\Domain\Magazine\Models\MagPage;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IssueRendererService
{
    // A4 points
    private const PW = 595;
    private const PH = 842;
    private const ML = 48;
    private const MT = 60;
    private const CW = 499; // PW - ML*2 roughly

    public function __construct(private TemplateRegistry $templateRegistry) {}

    public function materialize(MagazineIssue $issue): Page
    {
        $layout = $issue->layout_final;
        if (!$layout || empty($layout)) {
            throw new \RuntimeException('No layout data');
        }

        $issue->load('contentItems');
        $site = $issue->site;

        // Archive previous handoff
        if ($issue->linked_page_id) {
            $oldPage = Page::find($issue->linked_page_id);
            if ($oldPage) {
                $oldPage->update([
                    'status' => 'archived',
                    'slug' => $oldPage->slug . '-archived-' . time(),
                ]);
            }
        }

        // Create the page
        $page = Page::create([
            'site_id' => $site->id,
            'title' => $issue->title,
            'slug' => Str::slug($issue->title) . '-' . substr(md5($issue->id . time()), 0, 8),
            'status' => 'draft',
            'editor_mode' => 'magazine',
        ]);

        // Resolve all content items to posts with full text
        $postsByItemId = [];
        foreach ($issue->contentItems as $item) {
            if ($item->source_type === 'post' && $item->source_id) {
                $post = Post::find($item->source_id);
                if ($post) {
                    $postsByItemId[$item->id] = $post;
                }
            }
        }

        // Track which posts we've already started (for multi-page articles)
        $postPageIndex = []; // post_id => current page index within that article

        foreach ($layout as $pageSpec) {
            $pageNum = $pageSpec['page_number'] ?? 1;
            $templateId = $pageSpec['template_id'] ?? 'text_one_column';
            $sectionId = $pageSpec['section_id'] ?? 'content';
            $density = $pageSpec['density'] ?? 'standard';
            $rawSlots = $pageSpec['slots'] ?? [];

            // Create mag_page
            MagPage::create([
                'page_id' => $page->id,
                'page_number' => $pageNum,
                'page_size' => ['width' => self::PW, 'height' => self::PH],
                'margins' => ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
                'bleed' => ['top' => 9, 'right' => 9, 'bottom' => 9, 'left' => 9],
                'columns' => ['count' => 1, 'gutter' => 12],
                'baseline_grid' => ['increment' => 14, 'start' => 36],
                'spread_role' => $sectionId,
                'spread_density' => $density,
            ]);

            // Resolve slots with real post content
            $slots = $this->resolveSlots($rawSlots, $issue, $postsByItemId);

            // Render template into positioned elements
            $blocks = $this->templateRegistry->render($templateId, $slots);

            foreach ($blocks as $idx => $block) {
                MagElement::create([
                    'page_id' => $page->id,
                    'type' => $block['type'] ?? 'text_frame',
                    'x' => $block['x'] ?? 36,
                    'y' => $block['y'] ?? 36,
                    'width' => $block['width'] ?? 523,
                    'height' => $block['height'] ?? 100,
                    'z_index' => $idx + 1,
                    'page_number' => $pageNum,
                    'data' => $block['data'] ?? [],
                    'typography' => $block['typography'] ?? null,
                    'style' => $block['style'] ?? [],
                    'text_wrap' => [
                        'type' => 'none',
                        'offset' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
                        'side' => 'both',
                    ],
                    'created_by' => Auth::id(),
                ]);
            }
        }

        // Link issue to page
        $issue->update([
            'linked_page_id' => $page->id,
            'status' => 'handed_off',
        ]);

        return $page;
    }

    /**
     * Resolve slot values — pulls real content from posts.
     */
    private function resolveSlots(array $slots, MagazineIssue $issue, array $postsByItemId): array
    {
        $resolved = [];

        foreach ($slots as $key => $value) {
            if (is_array($value)) {
                if (isset($value['item_id'])) {
                    $item = $issue->contentItems->firstWhere('id', $value['item_id']);
                    $post = $item ? ($postsByItemId[$item->id] ?? null) : null;

                    if ($post) {
                        $field = $value['field'] ?? 'title';
                        $resolved[$key] = match ($field) {
                            'title' => $post->title,
                            'body_excerpt' => $post->excerpt ?: mb_substr(strip_tags($this->getPostBody($post)), 0, 500),
                            'hero_image' => $post->featured_image ?: '',
                            'pullquote' => $this->extractPullquote($post),
                            'body' => $this->getPostBody($post),
                            default => $post->title,
                        };
                    } elseif ($item && $item->source_type === 'extra_text') {
                        $resolved[$key] = $item->extra_payload['text'] ?? '';
                    }
                } elseif (isset($value['asset_id'])) {
                    $resolved[$key] = "/api/v1/assets/{$value['asset_id']}/serve";
                } else {
                    $resolved[$key] = json_encode($value);
                }
            } else {
                $resolved[$key] = (string) $value;
            }
        }

        return $resolved;
    }

    /**
     * Get full body HTML from a post's blocks.
     */
    private function getPostBody(Post $post): string
    {
        $blocks = $post->blocks()
            ->whereIn('type', ['text', 'paragraph', 'rich_text', 'heading', 'quote'])
            ->orderBy('order')
            ->get();

        $html = '';
        foreach ($blocks as $block) {
            $content = $block->data['content'] ?? '';
            if (trim(strip_tags($content))) {
                $html .= $content;
            }
        }

        return $html;
    }

    /**
     * Extract a compelling sentence for pullquote use.
     */
    private function extractPullquote(Post $post): string
    {
        $body = strip_tags($this->getPostBody($post));
        if (!$body) return $post->excerpt ?: '';

        $sentences = preg_split('/(?<=[.!?])\s+/u', $body, 8);
        foreach ($sentences as $s) {
            $s = trim($s);
            if (mb_strlen($s) > 30 && mb_strlen($s) < 180) {
                return $s;
            }
        }

        return $post->excerpt ?: '';
    }
}
