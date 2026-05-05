<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->jsonb('wizard_brief')->nullable();
        });

        Schema::table('mag_pages', function (Blueprint $table) {
            $table->text('spread_role')->nullable();
            $table->text('spread_density')->nullable();
            $table->text('spread_tension')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->dropColumn('wizard_brief');
        });

        Schema::table('mag_pages', function (Blueprint $table) {
            $table->dropColumn(['spread_role', 'spread_density', 'spread_tension']);
        });
    }
};
