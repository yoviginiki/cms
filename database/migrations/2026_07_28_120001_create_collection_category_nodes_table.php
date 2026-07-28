<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collections category tree (per-node schema). A hierarchical, arbitrary-depth
 * category system that lives *alongside* a collection's flat schema:
 *
 *  - `parent_id` (nullable, self-FK) gives the tree its shape. A NULL parent is
 *    a root node. Single-parent today; multi-parent is a deliberate future
 *    extension (see the class docblock on CollectionCategoryNode) — a
 *    `collection_category_node_parents` pivot can be added later without
 *    touching this column, which stays the canonical/primary parent.
 *  - `schema` holds the node's OWN field definitions ({ fields: [...] }) — the
 *    per-node schema. A record's effective schema is the collection's base
 *    fields merged with its node's ancestor chain (root→leaf), most-specific
 *    key wins. Validated by CollectionSchemaValidator::validateNodeFields().
 *
 * Additive + opt-in: a collection with no nodes behaves exactly as before.
 * Site-scoped RLS mirrors the records/collections shape (owner-only; system
 * rows never carry category nodes so there is no NULL-site read branch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_category_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('collection_id');
            $table->uuid('site_id');
            $table->uuid('parent_id')->nullable();
            $table->string('name', 160);
            $table->string('slug', 160);
            $table->integer('sort_order')->default(0);
            $table->integer('depth')->default(0); // 0 = root; denormalized for cheap ordering
            $table->jsonb('schema')->default('{}'); // { fields: [...] } — the node's own fields
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->index(['collection_id', 'parent_id']);
            $table->index(['site_id']);
        });

        // Self-referencing FK added after the table exists so the primary key
        // it points at is already in place. A parent delete reparents/relocates
        // via the service; this FK is the safety net (children fall to root).
        Schema::table('collection_category_nodes', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('collection_category_nodes')->nullOnDelete();
        });

        // Sibling slugs are unique within a collection (roots share the NULL
        // parent bucket via a sentinel so the URL path stays unambiguous).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                CREATE UNIQUE INDEX uq_cat_nodes_sibling_slug
                ON collection_category_nodes
                (collection_id, COALESCE(parent_id, '00000000-0000-0000-0000-000000000000'::uuid), slug)
            ");
        } else {
            Schema::table('collection_category_nodes', function (Blueprint $table) {
                $table->unique(['collection_id', 'parent_id', 'slug']);
            });
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE collection_category_nodes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE collection_category_nodes FORCE ROW LEVEL SECURITY');
        $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
        DB::statement("
            CREATE POLICY tenant_isolation ON collection_category_nodes
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON collection_category_nodes');
        }
        Schema::dropIfExists('collection_category_nodes');
    }
};
