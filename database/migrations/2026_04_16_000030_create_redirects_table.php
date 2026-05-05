<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('source_path');
            $table->string('target_url');
            $table->integer('status_code')->default(301);
            $table->boolean('is_regex')->default(false);
            $table->integer('hit_count')->default(0);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->unique(['site_id', 'source_path']);
        });

        // Add folder to assets
        Schema::table('assets', function (Blueprint $table) {
            $table->string('folder')->nullable()->after('alt_text');
            $table->index(['site_id', 'folder']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'folder']);
            $table->dropColumn('folder');
        });
    }
};
