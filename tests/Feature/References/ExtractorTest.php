<?php

namespace Tests\Feature\References;

use App\Domain\References\ExtractionContext;
use App\Domain\References\Services\ReferenceExtractorRegistry;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * Extractor correctness per block type: given block data, the right edges
 * (and ONLY the right edges) come out.
 */
class ExtractorTest extends TestCase
{
    private Site $site;
    private ReferenceExtractorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => ['auto_publish' => false]]);
        $this->registry = new ReferenceExtractorRegistry();
    }

    private function extract(string $type, array $data): array
    {
        return $this->registry->for($type)->extract($data, new ExtractionContext($this->site));
    }

    private function serveUrl(string $assetId): string
    {
        return "/api/v1/sites/{$this->site->id}/assets/{$assetId}/serve";
    }

    public function test_image_block_extracts_asset_edge_from_asset_id(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        $edges = $this->extract('image', ['asset_id' => $asset->id, 'url' => '']);

        $this->assertSame([
            ['target_type' => 'asset', 'target_id' => $asset->id, 'kind' => 'uses_asset'],
        ], $edges);
    }

    public function test_image_block_extracts_asset_edge_from_serve_url(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        $edges = $this->extract('image', ['url' => $this->serveUrl($asset->id)]);

        $this->assertSame([
            ['target_type' => 'asset', 'target_id' => $asset->id, 'kind' => 'uses_asset'],
        ], $edges);
    }

    public function test_external_urls_produce_no_edges(): void
    {
        $this->assertSame([], $this->extract('image', ['url' => 'https://images.pexels.com/photo.jpg']));
        $this->assertSame([], $this->extract('video', ['url' => 'https://youtube.com/watch?v=x', 'poster' => 'https://cdn.example.com/p.jpg']));
        $this->assertSame([], $this->extract('socialembed', ['url' => 'https://twitter.com/x/status/1']));
    }

    public function test_hero_extracts_bg_asset_and_internal_cta_link(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);
        $target = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'contact']);

        $edges = $this->extract('hero', [
            'bg_asset_id' => $asset->id,
            'ctaUrl' => '/contact',
        ]);

        $this->assertContains(['target_type' => 'asset', 'target_id' => $asset->id, 'kind' => 'uses_asset'], $edges);
        $this->assertContains(['target_type' => 'page', 'target_id' => $target->id, 'kind' => 'links'], $edges);
        $this->assertCount(2, $edges);
    }

    public function test_gallery_extracts_assets_from_string_and_object_items(): void
    {
        $a = Asset::factory()->create(['site_id' => $this->site->id]);
        $b = Asset::factory()->create(['site_id' => $this->site->id]);

        $edges = $this->extract('gallery', ['images' => [
            $this->serveUrl($a->id),                                   // plain string item
            ['src' => $this->serveUrl($b->id), 'alt' => 'legacy'],     // legacy object item
            'https://images.unsplash.com/external.jpg',                // external, ignored
        ]]);

        $this->assertContains(['target_type' => 'asset', 'target_id' => $a->id, 'kind' => 'uses_asset'], $edges);
        $this->assertContains(['target_type' => 'asset', 'target_id' => $b->id, 'kind' => 'uses_asset'], $edges);
        $this->assertCount(2, $edges);
    }

    public function test_menu_block_embeds_menu_and_links_custom_items(): void
    {
        $menu = Menu::create(['site_id' => $this->site->id, 'name' => 'Nav', 'slug' => 'nav']);
        $target = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'about']);

        $edges = $this->extract('menu', [
            'menuId' => $menu->id,
            'customItems' => [
                ['label' => 'About', 'url' => '/about'],
                ['label' => 'Ext', 'url' => 'https://elsewhere.example'],
            ],
        ]);

        $this->assertContains(['target_type' => 'menu', 'target_id' => $menu->id, 'kind' => 'embeds'], $edges);
        $this->assertContains(['target_type' => 'page', 'target_id' => $target->id, 'kind' => 'links'], $edges);
        $this->assertCount(2, $edges);
    }

    public function test_postcard_embeds_post(): void
    {
        $post = Post::factory()->published()->create(['site_id' => $this->site->id]);

        $edges = $this->extract('postcard', ['postId' => $post->id]);

        $this->assertSame([
            ['target_type' => 'post', 'target_id' => $post->id, 'kind' => 'embeds'],
        ], $edges);
    }

    public function test_listing_blocks_list_category_when_filtered_and_wildcard_when_not(): void
    {
        $category = Category::factory()->create(['site_id' => $this->site->id]);

        foreach (['latestposts', 'postgrid'] as $type) {
            $this->assertSame(
                [['target_type' => 'category', 'target_id' => $category->id, 'kind' => 'lists']],
                $this->extract($type, ['categoryId' => $category->id]),
                "{$type} with category filter",
            );
            $this->assertSame(
                [['target_type' => 'post', 'target_id' => null, 'kind' => 'lists']],
                $this->extract($type, []),
                "{$type} without category filter",
            );
        }
    }

    public function test_rich_text_extracts_internal_links_and_inline_asset_srcs(): void
    {
        $target = Page::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'pricing']);
        $post = Post::factory()->published()->create(['site_id' => $this->site->id, 'slug' => 'hello']);
        $post->category()->associate(Category::factory()->create(['site_id' => $this->site->id, 'slug' => 'news']));
        $post->save();
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        $html = '<p>See <a href="/pricing">pricing</a>, read <a href="/news/hello/">the post</a>, '
            . '<img src="' . $this->serveUrl($asset->id) . '"> and <a href="https://google.com">google</a>.</p>';

        $edges = $this->extract('rich-text', ['content' => $html]);

        $this->assertContains(['target_type' => 'page', 'target_id' => $target->id, 'kind' => 'links'], $edges);
        $this->assertContains(['target_type' => 'post', 'target_id' => $post->id, 'kind' => 'links'], $edges);
        $this->assertContains(['target_type' => 'asset', 'target_id' => $asset->id, 'kind' => 'uses_asset'], $edges);
        $this->assertCount(3, $edges);
    }

    public function test_flipbook_extracts_pdf_asset_and_category(): void
    {
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);
        $category = Category::factory()->create(['site_id' => $this->site->id]);

        $edges = $this->extract('flipbook', ['pdf_asset_id' => $asset->id, 'category_id' => $category->id]);

        $this->assertContains(['target_type' => 'asset', 'target_id' => $asset->id, 'kind' => 'uses_asset'], $edges);
        $this->assertContains(['target_type' => 'category', 'target_id' => $category->id, 'kind' => 'lists'], $edges);
    }

    public function test_structural_blocks_extract_nothing(): void
    {
        foreach (['heading', 'divider', 'spacer', 'columns', 'toc', 'breadcrumbs', 'post-content'] as $type) {
            $this->assertSame([], $this->extract($type, ['content' => 'anything', 'level' => 2]), $type);
        }
    }

    public function test_slider_ref_embeds_slider_and_slide_extracts_background_asset(): void
    {
        $sliderId = (string) \Illuminate\Support\Str::uuid();
        $asset = Asset::factory()->create(['site_id' => $this->site->id]);

        $this->assertSame(
            [['target_type' => 'slider', 'target_id' => $sliderId, 'kind' => 'embeds']],
            $this->extract('slider_ref', ['sliderId' => $sliderId]),
        );
        $this->assertSame(
            [['target_type' => 'asset', 'target_id' => $asset->id, 'kind' => 'uses_asset']],
            $this->extract('slide', ['background' => ['type' => 'image', 'assetId' => $asset->id]]),
        );
        $this->assertSame([], $this->extract('slider', ['height' => ['desktop' => '70vh']]));
        $this->assertSame([], $this->extract('shape', ['color' => '#E63B2E']));
    }

    public function test_invalid_uuid_in_id_field_is_ignored(): void
    {
        $this->assertSame([], $this->extract('postcard', ['postId' => 'not-a-uuid']));
        $this->assertSame([], $this->extract('image', ['asset_id' => '../../etc/passwd']));
    }
}
