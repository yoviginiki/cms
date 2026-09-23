<?php

namespace Tests\Feature\Security;

use App\Domain\Sites\Support\SiteSecrets;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F08 (encryption at rest) — secret settings are ciphertext in the database,
 * plaintext for the code that uses them, and legacy plaintext rows keep working.
 */
class SiteSecretsAtRestTest extends TestCase
{
    public function test_secrets_are_encrypted_in_the_row_and_transparent_to_readers(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => [
            'anthropic_api_key' => 'sk-RAW-123',
            'popularity' => ['cloudflare' => ['api_token' => 'cf-RAW', 'zone_tag' => 'z1']],
            'custom_css' => 'body{}',
        ]]);

        $raw = (string) DB::table('sites')->where('id', $site->id)->value('settings');
        $this->assertStringNotContainsString('sk-RAW-123', $raw);
        $this->assertStringNotContainsString('cf-RAW', $raw);
        $this->assertStringContainsString(SiteSecrets::ENC_PREFIX, $raw);
        $this->assertStringContainsString('body{}', $raw);   // non-secrets stay readable
        $this->assertStringContainsString('"z1"', $raw);

        $fresh = $site->fresh();
        $this->assertSame('sk-RAW-123', $fresh->settings['anthropic_api_key']);
        $this->assertSame('cf-RAW', $fresh->settings['popularity']['cloudflare']['api_token']);

        // re-saving does not double-encrypt
        $fresh->update(['name' => 'Renamed']);
        $this->assertSame('sk-RAW-123', $fresh->fresh()->settings['anthropic_api_key']);
    }

    public function test_legacy_plaintext_rows_read_and_get_encrypted_on_next_save(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        DB::table('sites')->where('id', $site->id)->update(['settings' => json_encode(['openai_api_key' => 'legacy-plain'])]);

        $this->assertSame('legacy-plain', $site->fresh()->settings['openai_api_key']);
        $site->fresh()->update(['settings' => array_merge($site->fresh()->settings, ['x' => 1])]);
        $this->assertStringNotContainsString('legacy-plain', (string) DB::table('sites')->where('id', $site->id)->value('settings'));
        $this->assertSame('legacy-plain', $site->fresh()->settings['openai_api_key']);
    }

    public function test_undecryptable_value_reads_as_null_instead_of_breaking_the_site(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        DB::table('sites')->where('id', $site->id)->update(['settings' => json_encode(['openai_api_key' => SiteSecrets::ENC_PREFIX . 'garbage', 'custom_css' => 'a{}'])]);

        $settings = $site->fresh()->settings;
        $this->assertNull($settings['openai_api_key']);
        $this->assertSame('a{}', $settings['custom_css']);
    }
}
