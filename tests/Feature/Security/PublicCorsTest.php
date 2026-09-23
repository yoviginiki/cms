<?php

namespace Tests\Feature\Security;

use App\Models\Site;
use Tests\TestCase;

/**
 * H02 (audit 2026-09-22) — confirmed: the global CORS handler answered the
 * preflight of a custom-domain form POST without Access-Control-Allow-Origin,
 * so browsers blocked the published contact form on every custom domain.
 * Public per-site endpoints now get per-site CORS (no credentials); the
 * credentialed admin CORS is unchanged.
 */
class PublicCorsTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'client.test']);
    }

    private function preflight(string $uri, string $origin, string $method = 'POST')
    {
        return $this->call('OPTIONS', $uri, [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-requested-with',
        ]);
    }

    public function test_form_preflight_from_the_sites_own_domain_is_allowed_without_credentials(): void
    {
        foreach (["/api/v1/sites/{$this->site->id}/forms/submit", "/api/v1/sites/{$this->site->id}/forms/contact-1/submit"] as $uri) {
            $r = $this->preflight($uri, 'https://client.test');
            $this->assertSame(204, $r->getStatusCode());
            $this->assertSame('https://client.test', $r->headers->get('Access-Control-Allow-Origin'));
            $this->assertStringContainsString('POST', (string) $r->headers->get('Access-Control-Allow-Methods'));
            $this->assertStringContainsStringIgnoringCase('x-requested-with', (string) $r->headers->get('Access-Control-Allow-Headers'));
            $this->assertNull($r->headers->get('Access-Control-Allow-Credentials'));
        }
        $this->assertSame('https://www.client.test', $this->preflight("/api/v1/sites/{$this->site->id}/forms/submit", 'https://www.client.test')->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_foreign_origins_and_methods_get_no_cors_grant(): void
    {
        $r = $this->preflight("/api/v1/sites/{$this->site->id}/forms/submit", 'https://evil.test');
        $this->assertNull($r->headers->get('Access-Control-Allow-Origin'));

        // another site's domain cannot use this site's endpoint
        $other = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'other.test']);
        $this->assertNull($this->preflight("/api/v1/sites/{$this->site->id}/forms/submit", 'https://other.test')->headers->get('Access-Control-Allow-Origin'));

        $this->assertNull($this->preflight("/api/v1/sites/{$this->site->id}/forms/submit", 'https://client.test', 'DELETE')->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_actual_post_carries_the_site_origin_and_no_credentials(): void
    {
        $r = $this->call('POST', "/api/v1/sites/{$this->site->id}/forms/submit", ['name' => 'a'], [], [], [
            'HTTP_ORIGIN' => 'https://client.test', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'fetch',
        ]);
        $this->assertSame('https://client.test', $r->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($r->headers->get('Access-Control-Allow-Credentials'));

        $s = $this->call('GET', "/api/v1/sites/{$this->site->id}/search?q=ab", [], [], [], ['HTTP_ORIGIN' => 'https://client.test']);
        $this->assertSame('https://client.test', $s->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_admin_api_keeps_credentialed_cors_for_the_cms_origin_only(): void
    {
        $admin = $this->call('OPTIONS', '/api/v1/sites', [], [], [], [
            'HTTP_ORIGIN' => 'https://sys.ensodo.eu', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $this->assertSame('https://sys.ensodo.eu', $admin->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $admin->headers->get('Access-Control-Allow-Credentials'));

        $foreign = $this->call('OPTIONS', '/api/v1/sites', [], [], [], [
            'HTTP_ORIGIN' => 'https://client.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $this->assertNull($foreign->headers->get('Access-Control-Allow-Origin'));

        // the admin wizard search is NOT treated as a public endpoint
        $wiz = $this->call('OPTIONS', "/api/v1/sites/{$this->site->id}/wizard/search", [], [], [], [
            'HTTP_ORIGIN' => 'https://client.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $this->assertNull($wiz->headers->get('Access-Control-Allow-Origin'));
    }
}
