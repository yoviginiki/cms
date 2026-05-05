<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->uuid('author_id')->nullable()->after('category_id');
            $table->timestamp('scheduled_at')->nullable()->after('published_at');

            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn(['author_id', 'scheduled_at']);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
