<?php

namespace Tests;

use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Per-request static caches must not leak across tests in the long-lived
        // test process. A build that sets a deploy target and then throws would
        // otherwise leave it set, making later tests self-host fonts (breaking
        // @import assertions) or resolve stale asset URLs. reset() clears both
        // the publish cache and the deploy target.
        \App\Domain\Publishing\Services\AssetPublisher::reset();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->owner()->create(['tenant_id' => $this->tenant->id]);
    }

    protected function actingAsOwner(): static
    {
        return $this->actingAs($this->owner, 'sanctum');
    }

    protected function actingAsAdmin(): static
    {
        $admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        return $this->actingAs($admin, 'sanctum');
    }

    protected function actingAsEditor(): static
    {
        $editor = User::factory()->editor()->create(['tenant_id' => $this->tenant->id]);
        return $this->actingAs($editor, 'sanctum');
    }

    protected function setTenantScope(User $user): void
    {
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $user->tenant_id);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");
    }

    protected function createSiteWithPages(int $pageCount = 3): Site
    {
        $this->setTenantScope($this->owner);

        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        for ($i = 0; $i < $pageCount; $i++) {
            $page = Page::factory()->published()->create([
                'site_id' => $site->id,
                'sort_order' => $i,
            ]);

            Block::factory()->hero()->create([
                'blockable_id' => $page->id,
                'blockable_type' => 'page',
                'order' => 0,
            ]);

            Block::factory()->create([
                'blockable_id' => $page->id,
                'blockable_type' => 'page',
                'order' => 1,
            ]);
        }

        return $site;
    }

    protected function apiHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Origin' => 'https://sys.ensodo.eu',
            'Referer' => 'https://sys.ensodo.eu/',
        ];
    }
}
