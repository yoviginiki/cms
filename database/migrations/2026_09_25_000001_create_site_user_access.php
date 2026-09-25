<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site access. A user with `restricted_to_sites = false` (the default,
 * i.e. every existing user) keeps tenant-wide access with `users.role`.
 * A restricted user only sees the sites listed in `site_user`, and on each
 * of them acts with that row's `role` (see User::effectiveRole()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('restricted_to_sites')->default(false)->after('role');
        });

        Schema::create('site_user', function (Blueprint $table) {
            $table->uuid('site_id');
            $table->uuid('user_id');
            $table->string('role', 20)->default('editor');
            $table->timestamps();

            $table->primary(['site_id', 'user_id']);
            $table->index('user_id');
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('restricted_to_sites');
        });
    }
};
