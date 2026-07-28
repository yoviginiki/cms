<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a record to a category-tree node (Collections category tree). Nullable
 * and additive: records/collections that don't use the tree keep a NULL node
 * and behave exactly as before. On node delete the FK nulls out (the service
 * reassigns to the parent first; this is the safety net).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->uuid('category_node_id')->nullable()->after('collection_id');
            $table->foreign('category_node_id')
                ->references('id')->on('collection_category_nodes')->nullOnDelete();
            $table->index(['category_node_id']);
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropForeign(['category_node_id']);
            $table->dropIndex(['category_node_id']);
            $table->dropColumn('category_node_id');
        });
    }
};
