<?php

namespace Tests\Feature\Modules;

use App\Models\Module;
use App\Models\ModuleApiLog;
use App\Models\ModuleTenant;
use App\Models\ModuleToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleTokenAuthTest extends TestCase
{
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['module.token:drafts:create', 'module:culture-engine'])
            ->post('/_test/culture/drafts', fn () => response()->json(['ok' => true]));

        $this->setTenantScope($this->owner);
    }

    private function seedModule(bool $globalEnabled = true, bool $tenantEnabled = true): void
    {
        $this->module = Module::create([
            'key' => 'culture-engine',
            'name' => 'Culture Engine',
            'enabled_globally' => $globalEnabled,
        ]);

        ModuleTenant::create([
            'module_id' => $this->module->id,
            'tenant_id' => $this->tenant->id,
            'enabled' => $tenantEnabled,
        ]);
    }

    /** @return string plaintext token */
    private function issueToken(array $abilities = ['drafts:create'], bool $revoked = false): string
    {
        [$token, $plaintext] = ModuleToken::issue([
            'module_id' => $this->module->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Test token',
            'abilities' => $abilities,
            'revoked_at' => $revoked ? now() : null,
        ]);

        return $plaintext;
    }

    public function test_valid_token_passes(): void
    {
        $this->seedModule();
        $plaintext = $this->issueToken();

        $this->withToken($plaintext)->postJson('/_test/culture/drafts')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_missing_token_is_401(): void
    {
        $this->seedModule();

        $this->postJson('/_test/culture/drafts')->assertStatus(401);
    }

    public function test_revoked_token_is_401(): void
    {
        $this->seedModule();
        $plaintext = $this->issueToken(revoked: true);

        $this->withToken($plaintext)->postJson('/_test/culture/drafts')->assertStatus(401);
    }

    public function test_wrong_ability_is_403(): void
    {
        $this->seedModule();
        $plaintext = $this->issueToken(abilities: ['something:else']);

        $this->withToken($plaintext)->postJson('/_test/culture/drafts')->assertStatus(403);
    }

    public function test_disabled_module_is_403_even_with_valid_token(): void
    {
        $this->seedModule(globalEnabled: true, tenantEnabled: false);
        $plaintext = $this->issueToken();

        $this->withToken($plaintext)->postJson('/_test/culture/drafts')
            ->assertStatus(403)
            ->assertJson(['error' => 'module_disabled']);
    }

    public function test_every_request_is_audited(): void
    {
        $this->seedModule();
        $plaintext = $this->issueToken();

        $this->withToken($plaintext)->postJson('/_test/culture/drafts')->assertOk();

        // withToken() persists the header for the rest of the test — clear it so
        // the next call genuinely arrives without credentials.
        $this->flushHeaders();
        $this->postJson('/_test/culture/drafts')->assertStatus(401); // missing token

        $this->assertDatabaseHas('module_api_logs', [
            'decision' => ModuleApiLog::GRANTED,
            'ability' => 'drafts:create',
            'status_code' => 200,
        ]);
        $this->assertDatabaseHas('module_api_logs', [
            'decision' => ModuleApiLog::DENIED_AUTH,
            'status_code' => 401,
        ]);
    }

    public function test_star_ability_grants_any(): void
    {
        $this->seedModule();
        $plaintext = $this->issueToken(abilities: ['*']);

        $this->withToken($plaintext)->postJson('/_test/culture/drafts')->assertOk();
    }
}
