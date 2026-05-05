<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('editor_mode', 10)->default('block')->after('status');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('editor_mode', 10)->default('block')->after('status');
        });

        // Add style column to blocks for shared styling props
        if (!Schema::hasColumn('blocks', 'style')) {
            Schema::table('blocks', function (Blueprint $table) {
                $table->jsonb('style')->nullable()->after('data');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('editor_mode');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('editor_mode');
        });
        if (Schema::hasColumn('blocks', 'style')) {
            Schema::table('blocks', function (Blueprint $table) {
                $table->dropColumn('style');
            });
        }
    }
};
