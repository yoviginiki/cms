<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ─── layouts table ───
        Schema::create('layouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('parent_layout_id')->nullable();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('wrapper_blade_view');
            $table->jsonb('supports')->default('{}');
            $table->jsonb('allowed_block_types')->nullable();
            $table->jsonb('promoted_block_types')->nullable();
            $table->jsonb('default_block_stack')->nullable();
            $table->jsonb('assets')->nullable();
            $table->jsonb('config')->nullable();
            $table->boolean('is_system')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });

        // ─── Add layout_id to pages ───
        Schema::table('pages', function (Blueprint $table) {
            $table->uuid('layout_id')->nullable()->after('site_id');
            $table->foreign('layout_id')->references('id')->on('layouts')->nullOnDelete();
        });

        // ─── Add layout_id to posts ───
        Schema::table('posts', function (Blueprint $table) {
            $table->uuid('layout_id')->nullable()->after('site_id');
            $table->foreign('layout_id')->references('id')->on('layouts')->nullOnDelete();
        });

        // ─── Add default_layout_id to categories ───
        Schema::table('categories', function (Blueprint $table) {
            $table->uuid('default_layout_id')->nullable()->after('parent_id');
            $table->foreign('default_layout_id')->references('id')->on('layouts')->nullOnDelete();
        });

        // ─── RLS (PostgreSQL only) ───
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE layouts ENABLE ROW LEVEL SECURITY');
            DB::statement("
                DO \$\$ BEGIN
                    CREATE POLICY tenant_isolation ON layouts
                        USING (tenant_id IS NULL OR tenant_id IN (
                            SELECT id FROM tenants WHERE id = current_setting('app.current_tenant_id', true)::uuid
                        ));
                EXCEPTION WHEN duplicate_object THEN NULL;
                END \$\$;
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON layouts');
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['default_layout_id']);
            $table->dropColumn('default_layout_id');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['layout_id']);
            $table->dropColumn('layout_id');
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['layout_id']);
            $table->dropColumn('layout_id');
        });
        Schema::dropIfExists('layouts');
    }
};
