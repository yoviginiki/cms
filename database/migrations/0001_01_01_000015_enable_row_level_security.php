<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $directTenantTables = ['sites'];

    private array $siteScopedTables = [
        'pages', 'posts', 'categories', 'assets',
        'deployments', 'themes', 'block_templates',
    ];

    private array $deepScopedTables = [
        'blocks' => 'blockable_id',
        'page_versions' => null,
        'deploy_artifacts' => 'deployment_id',
    ];

    public function up(): void
    {
        // Enable RLS on sites (direct tenant_id)
        DB::statement('ALTER TABLE sites ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE sites FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON sites
            FOR ALL
            USING (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
            WITH CHECK (tenant_id = current_setting('app.current_tenant_id', true)::uuid)
        ");

        // Enable RLS on site-scoped tables
        foreach ($this->siteScopedTables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                FOR ALL
                USING (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
                WITH CHECK (site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid))
            ");
        }

        // Blocks: polymorphic, scope through pages or posts -> sites
        DB::statement('ALTER TABLE blocks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE blocks FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON blocks
            FOR ALL
            USING (
                (blockable_type = 'page' AND blockable_id IN (
                    SELECT id FROM pages WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ))
                OR
                (blockable_type = 'post' AND blockable_id IN (
                    SELECT id FROM posts WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ))
            )
            WITH CHECK (
                (blockable_type = 'page' AND blockable_id IN (
                    SELECT id FROM pages WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ))
                OR
                (blockable_type = 'post' AND blockable_id IN (
                    SELECT id FROM posts WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ))
            )
        ");

        // Page versions: scope through page_id or post_id
        DB::statement('ALTER TABLE page_versions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE page_versions FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON page_versions
            FOR ALL
            USING (
                (page_id IS NOT NULL AND page_id IN (
                    SELECT id FROM pages WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ))
                OR
                (post_id IS NOT NULL AND post_id IN (
                    SELECT id FROM posts WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ))
            )
            WITH CHECK (
                (page_id IS NOT NULL AND page_id IN (
                    SELECT id FROM pages WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ))
                OR
                (post_id IS NOT NULL AND post_id IN (
                    SELECT id FROM posts WHERE site_id IN (
                        SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                    )
                ))
            )
        ");

        // Deploy artifacts: scope through deployments -> sites
        DB::statement('ALTER TABLE deploy_artifacts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE deploy_artifacts FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON deploy_artifacts
            FOR ALL
            USING (deployment_id IN (
                SELECT id FROM deployments WHERE site_id IN (
                    SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                )
            ))
            WITH CHECK (deployment_id IN (
                SELECT id FROM deployments WHERE site_id IN (
                    SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid
                )
            ))
        ");
    }

    public function down(): void
    {
        $allTables = array_merge(
            $this->directTenantTables,
            $this->siteScopedTables,
            ['blocks', 'page_versions', 'deploy_artifacts']
        );

        foreach ($allTables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
