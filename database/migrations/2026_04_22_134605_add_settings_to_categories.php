<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->after('description');
            $table->uuid('grid_id')->nullable()->after('is_public');

            $table->foreign('grid_id')->references('id')->on('grids')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['grid_id']);
            $table->dropColumn(['is_public', 'grid_id']);
        });
    }
};
