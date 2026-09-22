<?php

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * F02 (audit 2026-09-22) — POST system/updates/apply used to let any tenant
 * admin feed the installation an arbitrary ZIP (own URL + own checksum) that
 * was copied over base_path() and migrated. Web-applied updates are now OFF
 * by default; when an operator enables them they are limited to the owner of
 * the operator tenant, an allow-listed source host and a release signature
 * verified with an independent public key — all before anything is fetched.
 */
class SystemUpdateAccessTest extends TestCase
{
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->payload = [
            'version' => '9.9.9',
            'download_url' => 'https://updates.example.test/releases/cms-9.9.9.zip',
            'checksum' => 'sha256:' . str_repeat('ab', 32),
            'signature' => base64_encode(str_repeat("\0", 64)),
        ];
        config([
            'cms.updates.server' => 'https://updates.example.test',
            'cms.updates.web_apply_enabled' => false,
            'cms.updates.operator_tenant_id' => null,
            'cms.updates.public_key' => null,
        ]);
    }

    public function test_web_apply_is_refused_by_default_for_every_tenant_role(): void
    {
        foreach (['owner', 'admin'] as $role) {
            $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => $role]);
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/system/updates/apply', $this->payload, $this->apiHeaders())
                ->assertForbidden();
        }
        Http::assertNothingSent();
        $this->assertDirectoryDoesNotExist(storage_path('app/updates/extracted'));
    }

    public function test_enabled_but_not_operator_tenant_is_refused(): void
    {
        $operator = Tenant::factory()->create();
        config([
            'cms.updates.web_apply_enabled' => true,
            'cms.updates.operator_tenant_id' => $operator->id,
            'cms.updates.public_key' => base64_encode(str_repeat("\1", 32)),
        ]);

        $this->actingAsOwner()
            ->postJson('/api/v1/system/updates/apply', $this->payload, $this->apiHeaders())
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_enabled_requires_owner_role_and_a_configured_public_key(): void
    {
        config([
            'cms.updates.web_apply_enabled' => true,
            'cms.updates.operator_tenant_id' => $this->tenant->id,
            'cms.updates.public_key' => null,
        ]);

        // owner of the operator tenant, but no release key configured -> refused
        $this->actingAsOwner()
            ->postJson('/api/v1/system/updates/apply', $this->payload, $this->apiHeaders())
            ->assertForbidden();

        config(['cms.updates.public_key' => base64_encode(str_repeat("\1", 32))]);
        // admin (not owner) of the operator tenant -> refused
        $this->actingAsAdmin()
            ->postJson('/api/v1/system/updates/apply', $this->payload, $this->apiHeaders())
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_source_host_version_and_signature_are_checked_before_any_download(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        config([
            'cms.updates.web_apply_enabled' => true,
            'cms.updates.operator_tenant_id' => $this->tenant->id,
            'cms.updates.public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
        ]);

        // foreign host
        $this->actingAsOwner()
            ->postJson('/api/v1/system/updates/apply', array_merge($this->payload, [
                'download_url' => 'https://evil.example.test/cms.zip',
            ]), $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('download_url');

        // plain http on the right host
        $this->actingAsOwner()
            ->postJson('/api/v1/system/updates/apply', array_merge($this->payload, [
                'download_url' => 'http://updates.example.test/cms.zip',
            ]), $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('download_url');

        // version used in a file name
        $this->actingAsOwner()
            ->postJson('/api/v1/system/updates/apply', array_merge($this->payload, [
                'version' => '../../etc/passwd',
            ]), $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');

        // signature not made with the release key
        $this->actingAsOwner()
            ->postJson('/api/v1/system/updates/apply', $this->payload, $this->apiHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('signature');

        Http::assertNothingSent();
    }

    public function test_a_correctly_signed_request_reaches_the_download_step(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        config([
            'cms.updates.web_apply_enabled' => true,
            'cms.updates.operator_tenant_id' => $this->tenant->id,
            'cms.updates.public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
        ]);
        Http::fake(['https://updates.example.test/*' => Http::response('not a zip', 500)]);

        $sig = sodium_crypto_sign_detached($this->payload['checksum'], sodium_crypto_sign_secretkey($keypair));
        $this->actingAsOwner()
            ->postJson('/api/v1/system/updates/apply', array_merge($this->payload, [
                'signature' => base64_encode($sig),
            ]), $this->apiHeaders())
            ->assertStatus(500); // download failed — but only after every gate passed

        Http::assertSentCount(1);
        File::deleteDirectory(storage_path('app/updates'));
    }
}
