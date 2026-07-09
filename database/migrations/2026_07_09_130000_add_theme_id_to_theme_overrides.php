<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T1.2 theme-engine hardening: scope theme_overrides to a theme.
 *
 * theme_overrides carried no theme_id, so a token override authored while
 * theme A was active re-applied under theme B (cross-theme bleed in the
 * resolve/Studio path). Add a nullable theme_id: saveOverrides stamps the
 * theme being customized; the resolver loads only overrides for the resolved
 * theme (legacy NULL rows still apply, for backward compat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_overrides', function (Blueprint $table) {
            $table->uuid('theme_id')->nullable()->after('site_id');
            $table->index(['tenant_id', 'theme_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::table('theme_overrides', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'theme_id', 'mode']);
            $table->dropColumn('theme_id');
        });
    }
};
