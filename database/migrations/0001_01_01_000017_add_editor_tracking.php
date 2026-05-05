<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->uuid('last_edited_by')->nullable()->after('order');
            $table->timestamp('last_edited_at')->nullable()->after('last_edited_by');

            $table->foreign('last_edited_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('active_editors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('page_id')->nullable();
            $table->uuid('post_id')->nullable();
            $table->timestamp('last_heartbeat');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();

            $table->unique(['user_id', 'page_id', 'post_id']);
            $table->index('last_heartbeat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_editors');

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropForeign(['last_edited_by']);
            $table->dropColumn(['last_edited_by', 'last_edited_at']);
        });
    }
};
