<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\Deploy\SshDeployStrategy;
use App\Domain\Publishing\Services\Deploy\SshTarget;
use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * H01 (audit 2026-09-22) — confirmed reachable: CreateSiteRequest accepted
 * free-form settings, so a tenant admin could store a non-numeric SSH port
 * that was interpolated into a shell string. Now every SSH field is
 * validated (write side + deploy side) and rsync runs as an argv.
 */
class SshDeployHardeningTest extends TestCase
{
    private string $keys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->keys = storage_path('framework/testing/ssh-keys-' . uniqid());
        File::ensureDirectoryExists($this->keys);
        File::put("{$this->keys}/site_ed25519", 'KEY');
        config(['publishing.ssh_keys_path' => $this->keys]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->keys);
        parent::tearDown();
    }

    private function valid(array $over = []): array
    {
        return array_merge([
            'deploy_method' => 'ssh', 'deploy_ssh_host' => 'deploy.example.com', 'deploy_ssh_user' => 'deployer',
            'deploy_ssh_port' => 2222, 'deploy_ssh_path' => '/var/www/site-a/', 'deploy_ssh_key' => 'site_ed25519',
        ], $over);
    }

    public static function badSettings(): array
    {
        return [
            'port shell' => [['deploy_ssh_port' => '22; touch /tmp/pwned'], 'deploy_ssh_port'],
            'port range' => [['deploy_ssh_port' => 70000], 'deploy_ssh_port'],
            'user option' => [['deploy_ssh_user' => '-oProxyCommand=sh'], 'deploy_ssh_user'],
            'user space' => [['deploy_ssh_user' => 'a b'], 'deploy_ssh_user'],
            'host option' => [['deploy_ssh_host' => '-oProxyCommand=x'], 'deploy_ssh_host'],
            'host shell' => [['deploy_ssh_host' => 'a.com;id'], 'deploy_ssh_host'],
            'empty path' => [['deploy_ssh_path' => ''], 'deploy_ssh_path'],
            'root path' => [['deploy_ssh_path' => '/'], 'deploy_ssh_path'],
            'system path' => [['deploy_ssh_path' => '/etc/'], 'deploy_ssh_path'],
            'relative path' => [['deploy_ssh_path' => 'www'], 'deploy_ssh_path'],
            'traversal' => [['deploy_ssh_path' => '/var/www/../../etc'], 'deploy_ssh_path'],
            'server identity' => [['deploy_ssh_key' => '/home/cytechno/.ssh/id_rsa'], 'deploy_ssh_key'],
            'key escape' => [['deploy_ssh_key' => '../../../etc/passwd'], 'deploy_ssh_key'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badSettings')]
    public function test_invalid_ssh_settings_are_rejected(array $override, string $field): void
    {
        $this->assertArrayHasKey($field, SshTarget::errors($this->valid($override)));
    }

    public function test_valid_settings_build_an_argv_without_a_shell(): void
    {
        $t = SshTarget::fromSettings($this->valid());
        $cmd = $t->rsyncCommand('/staging/build/');
        $this->assertSame('rsync', $cmd[0]);
        $this->assertContains('--', $cmd);
        $this->assertSame('deployer@deploy.example.com:/var/www/site-a/', end($cmd));
        $e = $cmd[array_search('-e', $cmd, true) + 1];
        $this->assertStringContainsString('-p 2222', $e);
        $this->assertStringContainsString('-i ' . realpath("{$this->keys}/site_ed25519"), $e);
    }

    public function test_create_request_refuses_a_shell_port(): void
    {
        $this->actingAsOwner()->postJson('/api/v1/sites', [
            'name' => 'Evil', 'settings' => $this->valid(['deploy_ssh_port' => '22; touch /tmp/pwned']),
        ], $this->apiHeaders())->assertStatus(422)->assertJsonValidationErrors('settings.deploy_ssh_port');
    }

    public function test_update_accepts_the_masked_key_round_trip(): void
    {
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'settings' => $this->valid()]);
        $this->actingAsOwner()->putJson("/api/v1/sites/{$site->id}", [
            'settings' => $this->valid(['deploy_ssh_key' => \App\Domain\Sites\Support\SiteSecrets::MASK]),
        ], $this->apiHeaders())->assertOk();
        $this->assertSame('site_ed25519', $site->fresh()->settings['deploy_ssh_key']);
    }

    public function test_deploy_layer_refuses_legacy_rows_and_runs_argv(): void
    {
        Process::fake();
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $site->forceFill(['settings' => $this->valid(['deploy_ssh_port' => '22; touch /tmp/pwned'])])->save();
        $dep = Deployment::create(['site_id' => $site->id, 'type' => 'full', 'status' => 'deploying', 'triggered_by' => $this->owner->id, 'metadata' => []]);

        try {
            (new SshDeployStrategy())->deploy('/staging/x', $site->fresh()->settings, $dep);
            $this->fail('expected refusal');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('deploy_ssh_port', $e->getMessage());
        }
        Process::assertNothingRan();

        (new SshDeployStrategy())->deploy('/staging/x', $this->valid(), $dep);
        Process::assertRan(fn ($p) => is_array($p->command) && $p->command[0] === 'rsync');
    }
}
