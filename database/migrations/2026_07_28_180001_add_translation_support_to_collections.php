<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Translations of one logical record share a group; the default-
        // language member's slug is the canonical public slug for the group
        // (mirrors the pages convention where -en slugs publish at /en/{base}).
        Schema::table('records', function (Blueprint $table) {
            $table->uuid('translation_group_id')->nullable()->after('category_node_id');
            $table->index(['collection_id', 'translation_group_id']);
        });

        // One category tree for all languages: per-locale display names live
        // on the node; the slug is canonical and language-neutral.
        Schema::table('collection_category_nodes', function (Blueprint $table) {
            $table->jsonb('name_translations')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex(['collection_id', 'translation_group_id']);
            $table->dropColumn('translation_group_id');
        });
        Schema::table('collection_category_nodes', function (Blueprint $table) {
            $table->dropColumn('name_translations');
        });
    }
};
