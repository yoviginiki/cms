<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Free-form per-category settings; first tenant: name_translations
        // ({locale: name}) consumed by the archive builder and categorylist
        // block on multilingual sites.
        Schema::table('categories', function (Blueprint $table) {
            $table->jsonb('settings')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
