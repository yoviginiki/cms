<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            // Block level in the 4-level hierarchy: section → row → column → module
            // Default 'module' is safe for all existing blocks (they're all leaf-level content)
            $table->string('level', 10)->default('module')->after('type');

            // Optional: which preset created this block (e.g., 'hero', 'cta')
            $table->string('preset_id', 64)->nullable()->after('level');

            // Composite index for efficient tree queries
            $table->index(['blockable_id', 'parent_block_id', 'order'], 'idx_block_tree');
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropIndex('idx_block_tree');
            $table->dropColumn(['level', 'preset_id']);
        });
    }
};
