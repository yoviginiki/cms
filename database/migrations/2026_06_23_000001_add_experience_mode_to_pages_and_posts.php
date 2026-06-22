<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('experience_mode', 20)->default('standard')->after('editor_mode');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('experience_mode', 20)->default('standard')->after('editor_mode');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('experience_mode');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('experience_mode');
        });
    }
};
