<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('featured_image');
            $table->string('thumbnail')->nullable()->after('video_url');
            $table->string('post_format', 20)->default('standard')->after('thumbnail');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'thumbnail', 'post_format']);
        });
    }
};
