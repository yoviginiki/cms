<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * URL capture now runs on the queue worker (the web pool disables proc_open, so
 * the Playwright screenshot can't be spawned from a request). The session starts
 * in a `capturing` status and flips to `drafting` or `capture_failed`; this
 * column carries the failure message the wizard shows when it can't read a site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_wizard_sessions', function (Blueprint $table) {
            $table->text('error')->nullable()->after('theme_id');
        });
    }

    public function down(): void
    {
        Schema::table('theme_wizard_sessions', function (Blueprint $table) {
            $table->dropColumn('error');
        });
    }
};
