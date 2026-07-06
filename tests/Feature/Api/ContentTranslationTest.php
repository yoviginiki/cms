<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use Tests\TestCase;

class ContentTranslationTest extends TestCase
{
    private function multilingualSite()
    {
        $site = $this->createSiteWithPages(1);
        $site->update(['settings' => array_merge($site->settings ?? [], [
            'default_language' => 'en',
            'languages' => ['bg', 'de'],
        ])]);

        return [$site, Page::where('site_id', $site->id)->firstOrFail()];
    }

    public function test_translate_creates_a_locale_copy_with_blocks(): void
    {
        [$site, $page] = $this->multilingualSite();

        $resp = $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/pages/{$page->id}/translate", [
            'locale' => 'bg',
        ]);

        $resp->assertCreated()
            ->assertJsonPath('data.slug', $page->slug . '-bg')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.seo_meta.locale', 'bg');

        $translationId = $resp->json('data.id');
        $this->assertSame(
            2,
            \App\Models\Block::where('blockable_type', 'page')->where('blockable_id', $translationId)->count(),
            'blocks are copied to the translation'
        );
    }

    public function test_translations_lists_siblings_by_locale(): void
    {
        [$site, $page] = $this->multilingualSite();

        $this->actingAsOwner()->postJson("/api/v1/sites/{$site->id}/pages/{$page->id}/translate", ['locale' => 'bg'])
            ->assertCreated();

        $resp = $this->actingAsOwner()->getJson("/api/v1/sites/{$site->id}/pages/{$page->id}/translations");

        $resp->assertOk();
        $rows = collect($resp->json('data'));
        $this->assertSame('bg', $rows->firstWhere('slug', $page->slug . '-bg')['locale']);
        $this->assertSame('draft', $rows->firstWhere('locale', 'bg')['status']);
    }

    public function test_duplicate_translation_and_disabled_locale_are_rejected(): void
    {
        [$site, $page] = $this->multilingualSite();
        $base = "/api/v1/sites/{$site->id}/pages/{$page->id}";

        $this->actingAsOwner()->postJson("$base/translate", ['locale' => 'bg'])->assertCreated();
        $this->actingAsOwner()->postJson("$base/translate", ['locale' => 'bg'])->assertStatus(422);
        $this->actingAsOwner()->postJson("$base/translate", ['locale' => 'fr'])->assertStatus(422);
    }
}
