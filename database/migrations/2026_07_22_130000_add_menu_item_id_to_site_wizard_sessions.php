<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site Wizard "import into existing site" mode: when a build lands inside an
 * existing site, the imported pages hang as a submenu under ONE new parent
 * item in the site's existing header menu. That parent's id is what abandon
 * needs to remove the submenu (and nothing else) — the site, its menu, and
 * its theme are not the wizard's to delete in this mode.
 *
 * Plain uuid (no FK) for the same reason as theme_id/menu_id: the menus RLS
 * policies use the strict current_setting() variant, which errors during FK
 * validation scans where no tenant is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_wizard_sessions', function (Blueprint $table) {
            $table->uuid('menu_item_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_wizard_sessions', function (Blueprint $table) {
            $table->dropColumn('menu_item_id');
        });
    }
};
