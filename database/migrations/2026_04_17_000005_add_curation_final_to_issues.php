<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->jsonb('curation_final')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->dropColumn('curation_final');
        });
    }
};
