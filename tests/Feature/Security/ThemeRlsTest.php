<?php

namespace Tests\Feature\Security;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T1.2 theme-engine hardening: the themes RLS WITH CHECK closes the fake
 * system-theme injection vector. A tenant session may write only themes
 * scoped to its own sites — never a global (site_id NULL / is_system) row,
 * nor another tenant's theme.
 */
class ThemeRlsTest extends TestCase
{
    public function test_tenant_cannot_insert_a_fake_system_theme(): void
    {
        $this->setTenantScope($this->owner);

        // A site_id=NULL, is_system=true row would surface in EVERY tenant's
        // picker (the USING clause exposes system themes to all). The WITH
        // CHECK must reject the write.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('themes')->insert([
            'id' => (string) Str::uuid(),
            'site_id' => null,
            'name' => 'Fake System',
            'slug' => 'fake-system-' . Str::random(4),
            'version' => '1.0.0',
            'config' => json_encode([]),
            'is_system' => true,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_tenant_cannot_write_theme_into_another_tenants_site(): void
    {
        $this->setTenantScope($this->owner);
        $siteA = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->owner()->create(['tenant_id' => $tenantB->id]);
        $this->setTenantScope($userB);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('themes')->insert([
            'id' => (string) Str::uuid(),
            'site_id' => $siteA->id,
            'name' => 'Cross Tenant',
            'slug' => 'cross-' . Str::random(4),
            'version' => '1.0.0',
            'config' => json_encode([]),
            'is_system' => false,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_tenant_can_write_its_own_site_theme(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        $theme = Theme::create([
            'site_id' => $site->id,
            'name' => 'Mine',
            'slug' => 'mine-' . Str::random(4),
            'version' => '1.0.0',
            'config' => [],
            'manifest_json' => [],
            'template_path' => '',
            'document' => ['$metadata' => ['name' => 'Mine']],
            'modes' => ['light'],
            'schema_version' => '1.0.0',
        ]);

        $this->assertNotNull(Theme::find($theme->id));
        $this->assertFalse((bool) $theme->is_system);
    }

    public function test_all_theme_tables_have_rls_forced(): void
    {
        $tables = ['themes', 'theme_versions', 'theme_assignments', 'theme_overrides', 'theme_customizations', 'theme_templates'];
        foreach ($tables as $t) {
            $row = DB::selectOne(
                "SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = ? AND relkind = 'r'",
                [$t]
            );
            $this->assertNotNull($row, "table {$t} missing");
            $this->assertTrue((bool) $row->relrowsecurity, "RLS not enabled on {$t}");
            $this->assertTrue((bool) $row->relforcerowsecurity, "RLS not FORCED on {$t}");
        }
    }
}
