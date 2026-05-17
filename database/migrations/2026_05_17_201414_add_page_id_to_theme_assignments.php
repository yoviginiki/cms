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
        Schema::table('theme_assignments', function (Blueprint $table) {
            $table->uuid('page_id')->nullable()->after('site_id');
            $table->index(['site_id', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::table('theme_assignments', function (Blueprint $table) {
            $table->dropIndex(['theme_assignments_site_id_page_id_index']);
            $table->dropColumn('page_id');
        });
    }
};
