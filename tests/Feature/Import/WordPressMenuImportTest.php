<?php

namespace Tests\Feature\Import;

use App\Domain\Import\Services\WordPressImporter;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Tests\TestCase;

/**
 * WXR menu import — nav_menu_item entries become real Menu/MenuItem records
 * linked to the imported pages/posts, with hierarchy and order preserved.
 */
class WordPressMenuImportTest extends TestCase
{
    private Site $site;
    private string $xmlPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->xmlPath = tempnam(sys_get_temp_dir(), 'wxr') . '.xml';
        file_put_contents($this->xmlPath, $this->wxr());
    }

    protected function tearDown(): void
    {
        @unlink($this->xmlPath);
        parent::tearDown();
    }

    private function importer(): WordPressImporter
    {
        return app(WordPressImporter::class);
    }

    public function test_preview_counts_menus(): void
    {
        $preview = $this->importer()->preview($this->xmlPath);

        $this->assertSame(1, $preview['menus']);
        $this->assertSame(3, $preview['menu_items']);
    }

    public function test_import_builds_menu_tree_linked_to_content(): void
    {
        $result = $this->importer()->import($this->site, $this->xmlPath, ['import_media' => false]);

        $this->assertSame(1, $result->menus);

        $menu = Menu::where('site_id', $this->site->id)->first();
        $this->assertNotNull($menu);
        $this->assertSame('Main Menu', $menu->name);
        $this->assertSame('main-menu', $menu->slug);
        $this->assertSame('header', $menu->location, 'first imported menu should take the free header slot');

        $items = MenuItem::where('menu_id', $menu->id)->orderBy('sort_order')->get();
        $this->assertCount(3, $items);

        // Page item: linked by id, label falls back to the page title
        $page = Page::where('site_id', $this->site->id)->where('slug', 'about')->first();
        $this->assertSame($page->id, $items[0]->page_id);
        $this->assertSame('About', $items[0]->label);
        $this->assertNull($items[0]->parent_id);

        // Custom item: internal absolute URL rewritten to a relative path
        $this->assertSame('/kakvo-e-zen/', $items[1]->url);
        $this->assertSame('External', $items[1]->label);
        $this->assertNull($items[1]->parent_id);

        // Post item: nested under the custom item, linked to the imported post
        $post = Post::where('site_id', $this->site->id)->where('slug', 'hello-zen')->first();
        $this->assertSame($post->id, $items[2]->post_id);
        $this->assertSame('Hello Zen', $items[2]->label);
        $this->assertSame($items[1]->id, $items[2]->parent_id);
    }

    public function test_reimport_skips_existing_menu(): void
    {
        $this->importer()->import($this->site, $this->xmlPath, ['import_media' => false]);
        $result = $this->importer()->import($this->site, $this->xmlPath, ['import_media' => false]);

        $this->assertSame(0, $result->menus);
        $this->assertSame(1, Menu::where('site_id', $this->site->id)->count());
        $this->assertSame(3, MenuItem::whereIn(
            'menu_id',
            Menu::where('site_id', $this->site->id)->pluck('id'),
        )->count());
        $this->assertContains('menu', array_column($result->skipped, 'type'));
    }

    public function test_menus_can_be_excluded(): void
    {
        $result = $this->importer()->import($this->site, $this->xmlPath, [
            'import_media' => false,
            'import_menus' => false,
        ]);

        $this->assertSame(0, $result->menus);
        $this->assertSame(0, Menu::where('site_id', $this->site->id)->count());
    }

    private function wxr(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
  xmlns:wp="http://wordpress.org/export/1.2/"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
  <title>Heiko Test</title>
  <description>Zen therapy</description>
  <link>https://heikotera.com</link>
  <wp:category>
    <wp:term_id>5</wp:term_id>
    <wp:category_nicename>zen</wp:category_nicename>
    <wp:category_parent></wp:category_parent>
    <wp:cat_name><![CDATA[Zen]]></wp:cat_name>
  </wp:category>
  <wp:term>
    <wp:term_id>10</wp:term_id>
    <wp:term_taxonomy>nav_menu</wp:term_taxonomy>
    <wp:term_slug>main-menu</wp:term_slug>
    <wp:term_name><![CDATA[Main Menu]]></wp:term_name>
  </wp:term>
  <item>
    <title>About</title>
    <wp:post_id>100</wp:post_id>
    <wp:post_date>2024-01-01 10:00:00</wp:post_date>
    <wp:post_name>about</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_parent>0</wp:post_parent>
    <wp:menu_order>0</wp:menu_order>
    <wp:post_type>page</wp:post_type>
    <content:encoded><![CDATA[<!-- wp:paragraph --><p>About us</p><!-- /wp:paragraph -->]]></content:encoded>
  </item>
  <item>
    <title>Hello Zen</title>
    <wp:post_id>200</wp:post_id>
    <wp:post_date>2024-01-02 10:00:00</wp:post_date>
    <wp:post_name>hello-zen</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_parent>0</wp:post_parent>
    <wp:post_type>post</wp:post_type>
    <content:encoded><![CDATA[<!-- wp:paragraph --><p>Post body</p><!-- /wp:paragraph -->]]></content:encoded>
    <category domain="category" nicename="zen"><![CDATA[Zen]]></category>
  </item>
  <item>
    <title></title>
    <wp:post_id>301</wp:post_id>
    <wp:post_date>2024-01-03 10:00:00</wp:post_date>
    <wp:post_name>301</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_parent>0</wp:post_parent>
    <wp:menu_order>1</wp:menu_order>
    <wp:post_type>nav_menu_item</wp:post_type>
    <category domain="nav_menu" nicename="main-menu"><![CDATA[Main Menu]]></category>
    <wp:postmeta><wp:meta_key>_menu_item_type</wp:meta_key><wp:meta_value><![CDATA[post_type]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_object</wp:meta_key><wp:meta_value><![CDATA[page]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_object_id</wp:meta_key><wp:meta_value><![CDATA[100]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_menu_item_parent</wp:meta_key><wp:meta_value><![CDATA[0]]></wp:meta_value></wp:postmeta>
  </item>
  <item>
    <title>External</title>
    <wp:post_id>302</wp:post_id>
    <wp:post_date>2024-01-03 10:00:00</wp:post_date>
    <wp:post_name>302</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_parent>0</wp:post_parent>
    <wp:menu_order>2</wp:menu_order>
    <wp:post_type>nav_menu_item</wp:post_type>
    <category domain="nav_menu" nicename="main-menu"><![CDATA[Main Menu]]></category>
    <wp:postmeta><wp:meta_key>_menu_item_type</wp:meta_key><wp:meta_value><![CDATA[custom]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_object</wp:meta_key><wp:meta_value><![CDATA[custom]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_object_id</wp:meta_key><wp:meta_value><![CDATA[302]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_menu_item_parent</wp:meta_key><wp:meta_value><![CDATA[0]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_url</wp:meta_key><wp:meta_value><![CDATA[https://heikotera.com/kakvo-e-zen/]]></wp:meta_value></wp:postmeta>
  </item>
  <item>
    <title></title>
    <wp:post_id>303</wp:post_id>
    <wp:post_date>2024-01-03 10:00:00</wp:post_date>
    <wp:post_name>303</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_parent>0</wp:post_parent>
    <wp:menu_order>3</wp:menu_order>
    <wp:post_type>nav_menu_item</wp:post_type>
    <category domain="nav_menu" nicename="main-menu"><![CDATA[Main Menu]]></category>
    <wp:postmeta><wp:meta_key>_menu_item_type</wp:meta_key><wp:meta_value><![CDATA[post_type]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_object</wp:meta_key><wp:meta_value><![CDATA[post]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_object_id</wp:meta_key><wp:meta_value><![CDATA[200]]></wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>_menu_item_menu_item_parent</wp:meta_key><wp:meta_value><![CDATA[302]]></wp:meta_value></wp:postmeta>
  </item>
</channel>
</rss>
XML;
    }
}
