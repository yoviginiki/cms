<?php

namespace Tests\Feature\Security;

use App\Domain\Sites\Support\SiteSecrets;
use App\Models\Site;
use App\Models\User;
use Tests\TestCase;

/**
 * F08 (audit 2026-09-22) — the site API returned the whole settings object,
 * including configured API keys, to every tenant member. Secrets are now
 * masked on every read, preserved when the UI round-trips the mask, and
 * stripped from backup/export.
 */
class SiteSecretsTest extends TestCase
{
    private Site $site;
    private const MARKER = 'sk-live-MARKER-9f8e7d';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function url(): string
    {
        return "/api/v1/sites/{$this->site->id}";
    }

    public function test_secrets_never_appear_in_reads_for_any_role(): void
    {
        $this->actingAsOwner()->putJson($this->url(), ['settings' => [
            'anthropic_api_key' => self::MARKER,
            'deploy_ssh_key' => '/home/x/.ssh/' . self::MARKER,
            'popularity' => ['cloudflare' => ['api_token' => 'cf-' . self::MARKER, 'zone_tag' => 'zone1'],
                             'ga' => ['property_id' => '123', 'service_account' => ['client_email' => 'a@b', 'private_key' => 'pk-' . self::MARKER]]],
            'custom_css' => 'body{}',
        ]], $this->apiHeaders())->assertOk();

        // stored for real
        $stored = $this->site->fresh()->settings;
        $this->assertSame(self::MARKER, $stored['anthropic_api_key']);
        $this->assertSame('cf-' . self::MARKER, $stored['popularity']['cloudflare']['api_token']);

        foreach (['viewer', 'author', 'editor', 'admin', 'owner'] as $role) {
            $u = $role === 'owner' ? $this->owner : User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => $role]);
            $show = $this->actingAs($u, 'sanctum')->getJson($this->url(), $this->apiHeaders())->assertOk();
            $this->assertStringNotContainsString(self::MARKER, $show->getContent(), "secret leaked to {$role} via show");
            $this->assertSame(SiteSecrets::MASK, $show->json('data.settings.anthropic_api_key'));
            $this->assertSame(SiteSecrets::MASK, $show->json('data.settings.popularity.cloudflare.api_token'));
            $this->assertSame('zone1', $show->json('data.settings.popularity.cloudflare.zone_tag'));
            $this->assertSame('a@b', $show->json('data.settings.popularity.ga.service_account.client_email'));
            $this->assertSame('body{}', $show->json('data.settings.custom_css'));

            $list = $this->actingAs($u, 'sanctum')->getJson('/api/v1/sites', $this->apiHeaders())->assertOk();
            $this->assertStringNotContainsString(self::MARKER, $list->getContent(), "secret leaked to {$role} via index");
        }
        // the update response itself is a read too
        $upd = $this->actingAsOwner()->putJson($this->url(), ['name' => 'Renamed'], $this->apiHeaders())->assertOk();
        $this->assertStringNotContainsString(self::MARKER, $upd->getContent());
    }

    public function test_round_tripping_the_mask_preserves_the_real_secret_and_null_clears_it(): void
    {
        $this->actingAsOwner()->putJson($this->url(), ['settings' => ['anthropic_api_key' => self::MARKER]], $this->apiHeaders())->assertOk();

        // The SPA spreads the (masked) settings it read and saves another tab.
        $this->actingAsOwner()->putJson($this->url(), ['settings' => [
            'anthropic_api_key' => SiteSecrets::MASK,
            'openai_api_key' => null,
            'custom_css' => 'p{}',
        ]], $this->apiHeaders())->assertOk();
        $this->assertSame(self::MARKER, $this->site->fresh()->settings['anthropic_api_key']);
        $this->assertSame('p{}', $this->site->fresh()->settings['custom_css']);

        // A new value replaces it; null/empty clears it.
        $this->actingAsOwner()->putJson($this->url(), ['settings' => ['anthropic_api_key' => 'sk-new']], $this->apiHeaders())->assertOk();
        $this->assertSame('sk-new', $this->site->fresh()->settings['anthropic_api_key']);
        $this->actingAsOwner()->putJson($this->url(), ['settings' => ['anthropic_api_key' => null]], $this->apiHeaders())->assertOk();
        $this->assertNull($this->site->fresh()->settings['anthropic_api_key'] ?? null);
    }

    public function test_backup_export_strips_nested_secrets(): void
    {
        $this->site->update(['settings' => [
            'anthropic_api_key' => self::MARKER,
            'popularity' => ['cloudflare' => ['api_token' => 'cf-' . self::MARKER, 'zone_tag' => 'zone1']],
        ]]);

        $res = $this->actingAsOwner()->getJson("{$this->url()}/backup", $this->apiHeaders())->assertOk();
        $this->assertStringNotContainsString(self::MARKER, $res->getContent());
        $this->assertStringContainsString('zone1', $res->getContent());
    }

    public function test_helper_masks_strips_and_merges(): void
    {
        $settings = ['a' => 1, 'anthropic_api_key' => 'k', 'nested' => ['api_token' => 't', 'keep' => 'v'], 'empty_key' => ['api_key' => '']];
        $masked = SiteSecrets::mask($settings);
        $this->assertSame(SiteSecrets::MASK, $masked['anthropic_api_key']);
        $this->assertSame(SiteSecrets::MASK, $masked['nested']['api_token']);
        $this->assertSame('v', $masked['nested']['keep']);
        $this->assertSame('', $masked['empty_key']['api_key']); // nothing configured → nothing to hide

        $stripped = SiteSecrets::strip($settings);
        $this->assertArrayNotHasKey('anthropic_api_key', $stripped);
        $this->assertArrayNotHasKey('api_token', $stripped['nested']);
        $this->assertSame('v', $stripped['nested']['keep']);

        $merged = SiteSecrets::mergeIncoming($settings, ['anthropic_api_key' => SiteSecrets::MASK, 'nested' => ['api_token' => SiteSecrets::MASK, 'keep' => 'new']]);
        $this->assertSame('k', $merged['anthropic_api_key']);
        $this->assertSame('t', $merged['nested']['api_token']);
        $this->assertSame('new', $merged['nested']['keep']);
    }
}
