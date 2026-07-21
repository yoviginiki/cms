<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('published_at');
            $table->timestamp('unpublish_at')->nullable()->after('publish_at');
            $table->jsonb('seo_meta')->default('{}');

            $table->index(['status', 'publish_at']);
            $table->index(['status', 'unpublish_at']);
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex(['status', 'publish_at']);
            $table->dropIndex(['status', 'unpublish_at']);
            $table->dropColumn(['publish_at', 'unpublish_at', 'seo_meta']);
        });
    }
};
