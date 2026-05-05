<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Theme Engine — Additive migration.
 *
 * Adds new tables for the W3C Design Tokens-based theme engine:
 * - theme_assignments: which theme is active per site/mode
 * - theme_overrides: sparse per-scope token deltas
 * - theme_versions: immutable snapshots for publish/rollback
 *
 * Also amends existing tables:
 * - themes: adds `document` (W3C tokens JSON), `modes`, `schema_version` columns
 * - page_versions: adds `theme_version_id` link
 *
 * Does NOT drop or alter existing columns — purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Amend themes table ───
        Schema::table('themes', function (Blueprint $table) {
            if (!Schema::hasColumn('themes', 'document')) {
                $table->jsonb('document')->nullable()->after('config');
            }
            if (!Schema::hasColumn('themes', 'modes')) {
                $table->jsonb('modes')->nullable()->after('document');
            }
            if (!Schema::hasColumn('themes', 'schema_version')) {
                $table->string('schema_version', 16)->default('1.0.0')->after('modes');
            }
            if (!Schema::hasColumn('themes', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('themes', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('themes', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // ─── theme_assignments ───
        if (!Schema::hasTable('theme_assignments')) {
            Schema::create('theme_assignments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('site_id')->nullable();
                $table->uuid('theme_id');
                $table->string('mode')->default('light');
                $table->timestamps();

                $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
                $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();

                $table->unique(['tenant_id', 'site_id', 'mode']);
                $table->index(['tenant_id', 'site_id']);
            });
        }

        // ─── theme_overrides ───
        if (!Schema::hasTable('theme_overrides')) {
            Schema::create('theme_overrides', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('site_id')->nullable();
                $table->uuid('page_id')->nullable();
                $table->uuid('block_id')->nullable();
                $table->string('scope'); // tenant, site, page, block
                $table->string('mode')->default('light');
                $table->string('token_path');
                $table->jsonb('value');
                $table->timestamps();

                $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
                $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
                $table->foreign('block_id')->references('id')->on('blocks')->cascadeOnDelete();

                $table->index(['tenant_id', 'site_id', 'mode']);
                $table->index(['tenant_id', 'page_id', 'mode']);
                $table->index(['tenant_id', 'block_id', 'mode']);
            });
        }

        // ─── theme_versions ───
        if (!Schema::hasTable('theme_versions')) {
            Schema::create('theme_versions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('theme_id');
                $table->uuid('site_id')->nullable();
                $table->string('mode');
                $table->jsonb('resolved_document');
                $table->string('content_hash', 64);
                $table->string('css_artifact_path')->nullable();
                $table->unsignedBigInteger('css_artifact_size')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('theme_id')->references('id')->on('themes')->cascadeOnDelete();
                $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

                $table->index(['tenant_id', 'site_id', 'mode']);
                $table->index('content_hash');
            });
        }

        // ─── Amend page_versions ───
        Schema::table('page_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('page_versions', 'theme_version_id')) {
                $table->uuid('theme_version_id')->nullable()->after('id');
                $table->foreign('theme_version_id')->references('id')->on('theme_versions')->nullOnDelete();
            }
        });

        // ─── RLS policies (PostgreSQL only) ───
        if (DB::connection()->getDriverName() === 'pgsql') {
            // theme_assignments
            DB::statement('ALTER TABLE theme_assignments ENABLE ROW LEVEL SECURITY');
            DB::statement("
                DO $$ BEGIN
                    CREATE POLICY tenant_isolation ON theme_assignments
                        USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
                EXCEPTION WHEN duplicate_object THEN NULL;
                END $$;
            ");

            // theme_overrides
            DB::statement('ALTER TABLE theme_overrides ENABLE ROW LEVEL SECURITY');
            DB::statement("
                DO $$ BEGIN
                    CREATE POLICY tenant_isolation ON theme_overrides
                        USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
                EXCEPTION WHEN duplicate_object THEN NULL;
                END $$;
            ");

            // theme_versions
            DB::statement('ALTER TABLE theme_versions ENABLE ROW LEVEL SECURITY');
            DB::statement("
                DO $$ BEGIN
                    CREATE POLICY tenant_isolation ON theme_versions
                        USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
                EXCEPTION WHEN duplicate_object THEN NULL;
                END $$;
            ");
        }
    }

    public function down(): void
    {
        // Drop RLS policies
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON theme_assignments');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON theme_overrides');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON theme_versions');
        }

        // Remove page_versions amendment
        Schema::table('page_versions', function (Blueprint $table) {
            if (Schema::hasColumn('page_versions', 'theme_version_id')) {
                $table->dropForeign(['theme_version_id']);
                $table->dropColumn('theme_version_id');
            }
        });

        Schema::dropIfExists('theme_versions');
        Schema::dropIfExists('theme_overrides');
        Schema::dropIfExists('theme_assignments');

        // Remove added columns from themes (don't drop the table)
        Schema::table('themes', function (Blueprint $table) {
            $cols = ['document', 'modes', 'schema_version', 'description', 'created_by', 'deleted_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('themes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
